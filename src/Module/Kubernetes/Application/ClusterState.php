<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Module\Kubernetes\Application\Port\KubectlPort;
use LightManager\Module\Kubernetes\Domain\ValueObject\ClusterVersion;
use LightManager\Module\Kubernetes\Domain\ValueObject\ContextName;
use LightManager\Module\Kubernetes\Infrastructure\ClusterInfoParser;

/**
 * Gdzie jesteśmy i czy klaster odpowiada (krok 52; miejsce i dwa nowe stany —
 * krok 59).
 *
 * **Stan „nie ma klastra” jest tu stanem zwykłym, a nie awarią** — i to on, a nie
 * widok pełen podów, jest pierwszym, który krok musi narysować poprawnie
 * (zastrzeżenie startowe planu kroku 52, potwierdzone na maszynie projektu:
 * jedyny kontekst `ca-dev` nie jest bieżący, a minikube nie istnieje). Stanów
 * „nie ma podów" jest odtąd **pięć** i różnią się tym, co użytkownik ma z tym
 * zrobić:
 *
 * - **bez klastra** — spis nie wskazuje żadnego jako bieżącego; ekran ma
 *   powiedzieć, co wybrać, a nie „connection refused”;
 * - **nie ma pliku** i **nie ma w nim kontekstu** (krok 59) — miejsce ma dwie
 *   współrzędne i każda umie być nie tak; rozstrzyga o nich `Clusters`, bo to
 *   on czyta pliki;
 * - **nieosiągalny** — miejsce jest, klaster nie odpowiada; powód pochodzi ze
 *   strumienia błędów, a nie z domysłu;
 * - **gotowy** — obie wersje znane, można pytać o zasoby.
 *
 * Spis kontekstów czyta się **z plików, nie z sieci** (`config view`), więc pada
 * także wtedy, gdy nie ma czego zapytać — to jest cała przyczyna, dla której
 * pierwsze cztery z tych stanów w ogóle dają się narysować. Od kroku 59 robi to
 * `ConfigCatalog`, dla wszystkich plików, a nie dla jednego domyślnego.
 */
final class ClusterState
{
    private ?ClusterVersion $versions = null;

    private ClusterStage $stage = ClusterStage::Unknown;

    private ?string $problemKey = null;

    /** @var array<string, string|int|float> */
    private array $problemParameters = [];

    private readonly KubectlWork $versionWork;

    /** Czy zamówiono już pierwszy odczyt plików — start ma być leniwy. */
    private bool $started = false;

    /**
     * Pokolenie sesji, dla którego padło pytanie o wersje.
     *
     * Bez tego licznika pytanie wracałoby **co takt** przy klastrze, który
     * wersji serwera nie podaje — czyli dokładnie przy tym nieosiągalnym,
     * o który chodzi najbardziej. Jedno miejsce, jedno pytanie.
     */
    private int $askedGeneration = -1;

    public function __construct(
        KubectlPort $kubectl,
        private readonly ClusterSession $session,
        private readonly Clusters $clusters,
    ) {
        $this->versionWork = new KubectlWork($kubectl);
    }

    /** @return list<ContextName> konteksty pliku miejsca bieżącego — dla okna wyboru */
    public function contexts(): array
    {
        return $this->clusters->contextsOfCurrentFile();
    }

    public function current(): ?ContextName
    {
        return $this->session->context();
    }

    public function versions(): ?ClusterVersion
    {
        return $this->versions;
    }

    public function stage(): ClusterStage
    {
        return $this->stage;
    }

    public function problemKey(): ?string
    {
        return $this->problemKey;
    }

    /** @return array<string, string|int|float> */
    public function problemParameters(): array
    {
        return $this->problemParameters;
    }

    public function isWorking(): bool
    {
        return $this->versionWork->isWorking();
    }

    /**
     * Pyta pliki o konteksty — **pierwsze wywołanie modułu**.
     *
     * Zaczyna się od plików, a nie od klastra, bo bez miejsca żadne pytanie do
     * klastra nie ma adresu. Zamówienie jest **leniwe**: pada, kiedy ktoś otworzy
     * ekran, a nie przy uruchomieniu aplikacji — start nie ma prawa kosztować
     * procesu potomnego.
     */
    public function begin(): void
    {
        $this->started = true;
        $this->stage = ClusterStage::Reading;
        $this->clusters->refresh();
    }

    /** Przestawia się na wskazany kontekst **w pliku miejsca bieżącego**. */
    public function useContext(ContextName $context): ?string
    {
        return $this->clusters->selectContext($context);
    }

    /**
     * Posuwa pytanie o wersje i rozstrzyga etap.
     *
     * Kolejność jest tu regułą: **najpierw plik, potem klaster**. Wpis
     * wskazujący nieistniejący plik nie ma czego zapytać po sieci, a zdanie
     * „klaster nie odpowiada" schowałoby literówkę w ścieżce.
     */
    public function advance(): void
    {
        if (!$this->started) {
            return;
        }

        $fileStage = $this->clusters->fileStage();

        if ($fileStage !== null) {
            // Znacznik przełączenia **zdejmujemy**, choć o wersje nie pytamy:
            // pytanie do klastra, którego pliku nie ma, nie ma dokąd pójść,
            // a znacznik zostawiony na później wystrzeliłby je w chwili, gdy
            // użytkownik akurat poprawia ścieżkę.
            $this->clusters->takeSwitched();
            $this->versionWork->stop();
            $this->versions = null;
            $this->stage = $fileStage;
            $this->problemKey = $fileStage->labelKey();
            $this->problemParameters = $this->clusters->stageParameters();

            return;
        }

        if ($this->clusters->takeSwitched()) {
            $this->askForVersions();
        }

        if (!$this->session->isTargeted()) {
            $this->chooseIfPossible();

            return;
        }

        // Miejsce jest, a pytania o nie jeszcze nie było: tak wygląda powrót
        // pliku, który przed chwilą był nieobecny — przełączenia nie było, więc
        // pytanie musi paść stąd. Pokolenie pilnuje, żeby padło **raz**.
        if ($this->askedGeneration !== $this->session->generation() && !$this->versionWork->isWorking()) {
            $this->askForVersions();

            return;
        }

        $this->advanceVersions();
    }

    public function stop(): void
    {
        $this->versionWork->stop();
    }

    /**
     * Wybiera miejsce, gdy jeszcze żadne nie stoi — dopiero po tym, jak pliki
     * odpowiedziały.
     *
     * Dopóki odczyt trwa, etapem jest `Reading`: „nie ma klastra" powiedziane,
     * zanim przeczytano pliki, byłoby zdaniem o tym, czego jeszcze nie
     * sprawdzono.
     */
    private function chooseIfPossible(): void
    {
        if ($this->clusters->isReading()) {
            $this->stage = ClusterStage::Reading;

            return;
        }

        if ($this->clusters->chooseCurrent()) {
            // Pytanie o wersje pada **w następnym takcie**, bo wybór podniósł
            // znacznik przełączenia — a ten odbiera się na początku `advance()`.
            // Jedna droga do pytania zamiast dwóch: klawisz i start miejsca
            // wyglądają odtąd tak samo.
            $this->stage = ClusterStage::Reading;

            return;
        }

        // Ani zapamiętanego, ani bieżącego — to jest ten stan, który plan kroku
        // 52 każe narysować jako miejsce z wyborem.
        $this->stage = ClusterStage::NoContext;
    }

    private function advanceVersions(): void
    {
        $state = $this->versionWork->advance();

        if ($state === null) {
            return;
        }

        // Wersję klienta `kubectl` wypisuje **także wtedy, gdy nie ma klastra**,
        // a kończy się wtedy kodem niezerowym. Czytamy więc wyjście niezależnie
        // od kodu i dopiero brak wersji serwera rozstrzyga o nieosiągalności.
        $this->versions = ClusterInfoParser::versions($state->output) ?? $this->versions;

        if ($this->versions?->knowsServer() === true) {
            $this->stage = ClusterStage::Ready;
            $this->problemKey = null;
            $this->problemParameters = [];

            return;
        }

        $this->stage = ClusterStage::Unreachable;
        $this->problemKey = 'module.k8s.problem.unreachable';
        $this->problemParameters = ['reason' => self::reasonOf($state->errorOutput)];
    }

    private function askForVersions(): void
    {
        $this->versions = null;
        $this->askedGeneration = $this->session->generation();
        $this->stage = ClusterStage::Reading;
        $this->versionWork->begin(
            KubectlCall::version($this->session->place()),
            $this->session->timeoutSeconds(),
        );
    }

    /**
     * Powód z pierwszego wiersza strumienia błędów.
     *
     * `kubectl` pisze tam zdania długie i techniczne („The connection to the
     * server localhost:8080 was refused - did you specify the right host or
     * port?”), a pasek stanu ma jeden wiersz. Bierzemy pierwszy wiersz, bo drugi
     * i dalsze są podpowiedziami, a nie powodem.
     */
    private static function reasonOf(string $errorOutput): string
    {
        $first = strtok(trim($errorOutput), "\n");

        return $first === false ? '' : $first;
    }
}
