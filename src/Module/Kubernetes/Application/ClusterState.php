<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Application\Dto\BackgroundStage;
use LightManager\Module\Kubernetes\Application\Port\KubectlPort;
use LightManager\Module\Kubernetes\Domain\Exception\InvalidClusterNameException;
use LightManager\Module\Kubernetes\Domain\ValueObject\ClusterVersion;
use LightManager\Module\Kubernetes\Domain\ValueObject\ContextName;
use LightManager\Module\Kubernetes\Domain\ValueObject\NamespaceName;
use LightManager\Module\Kubernetes\Infrastructure\ClusterInfoParser;

/**
 * Gdzie jesteśmy i czy klaster odpowiada (krok 52).
 *
 * **Stan „nie ma klastra” jest tu stanem zwykłym, a nie awarią** — i to on, a nie
 * widok pełen podów, jest pierwszym, który krok musi narysować poprawnie
 * (zastrzeżenie startowe planu, potwierdzone na maszynie projektu: jedyny
 * kontekst `ca-dev` nie jest bieżący, a minikube nie istnieje). Stąd
 * rozróżnienie na **trzy** stany zamiast dwóch:
 *
 * - **bez kontekstu** — plik konfiguracyjny nie wskazuje żadnego jako bieżącego;
 *   ekran ma powiedzieć, co wybrać, a nie „connection refused”;
 * - **nieosiągalny** — kontekst jest, ale klaster nie odpowiada; powód pochodzi
 *   ze strumienia błędów, a nie z domysłu;
 * - **gotowy** — obie wersje znane, można pytać o zasoby.
 *
 * Spis kontekstów czyta się **z pliku, nie z sieci** (`config view`), więc pada
 * także wtedy, gdy nie ma czego zapytać — to jest cała przyczyna, dla której
 * pierwszy z tych stanów w ogóle daje się narysować.
 */
final class ClusterState
{
    /** @var list<ContextName> */
    private array $contexts = [];

    private ?ContextName $current = null;

    private ?ClusterVersion $versions = null;

    private ClusterStage $stage = ClusterStage::Unknown;

    private ?string $problemKey = null;

    /** @var array<string, string|int|float> */
    private array $problemParameters = [];

    private readonly KubectlWork $configWork;

    private readonly KubectlWork $versionWork;

    /** Przestrzeń nazw zapisana przy kontekście w `kubeconfig` — propozycja, nie nakaz. */
    private ?string $configuredNamespace = null;

    private string $configOutput = '';

    /**
     * Kontekst zapamiętany w ustawieniach modułu — **wybór z poprzedniego
     * uruchomienia**.
     *
     * Napis, a nie `ContextName`, bo pochodzi z pliku konfiguracyjnego aplikacji
     * i może wskazywać klaster, którego już nie ma. Obiektem wartości staje się
     * dopiero wtedy, gdy znajdzie się na liście z `kubeconfig`.
     */
    private string $rememberedContext = '';

    public function __construct(
        private readonly KubectlPort $kubectl,
        private readonly ClusterSession $session,
    ) {
        $this->configWork = new KubectlWork($kubectl);
        $this->versionWork = new KubectlWork($kubectl);
    }

    /** @return list<ContextName> */
    public function contexts(): array
    {
        return $this->contexts;
    }

    public function current(): ?ContextName
    {
        return $this->current;
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
        return $this->configWork->isWorking() || $this->versionWork->isWorking();
    }

    /**
     * Pyta plik konfiguracyjny o konteksty — **pierwsze wywołanie modułu**.
     *
     * Zaczyna się od pliku, a nie od klastra, bo bez kontekstu żadne pytanie do
     * klastra nie ma adresu. Zamówienie jest **leniwe**: pada, kiedy ktoś otworzy
     * ekran, a nie przy uruchomieniu aplikacji — start nie ma prawa kosztować
     * procesu potomnego.
     */
    public function begin(): void
    {
        $this->stage = ClusterStage::Reading;
        $this->configWork->begin(KubectlCall::contexts(), $this->session->timeoutSeconds());
    }

    /**
     * Podaje wybór z poprzedniego uruchomienia — **zanim** padnie pierwsze
     * pytanie.
     *
     * Napis idzie prosto z ustawień modułu i nie jest tu sprawdzany: sprawdzi go
     * porównanie z listą z `kubeconfig`, a nazwa, której na tej liście nie ma,
     * i tak do niczego nie posłuży.
     */
    public function remember(string $context): void
    {
        $this->rememberedContext = $context;
    }

    /**
     * Przestawia się na wskazany kontekst i pyta go o wersję.
     *
     * **Pliku konfiguracyjnego nie zmieniamy** — `kubectl config use-context`
     * zapisałby wybór poza aplikacją, a wybór zrobiony w menadżerze plików nie ma
     * prawa zmieniać tego, co zastanie użytkownik w swoim terminalu. Kontekst
     * jedzie odtąd argumentem `--context` przy każdym wywołaniu, a zapamiętuje go
     * pozycja ustawień modułu.
     */
    public function useContext(ContextName $context): void
    {
        $this->current = $context;
        $this->session->useContext($context);
        $this->applyConfiguredNamespace();
        $this->askForVersions();
    }

    /**
     * Posuwa oba pytania. Wołane raz na takt, jak wszystko w tym module.
     */
    public function advance(): void
    {
        $this->advanceConfig();
        $this->advanceVersions();
    }

    public function stop(): void
    {
        $this->configWork->stop();
        $this->versionWork->stop();
    }

    /**
     * Wybiera kontekst na starcie: zapamiętany, a w jego braku — bieżący z pliku.
     *
     * Kolejność jest taka, a nie odwrotna, bo zapamiętany jest **wyborem
     * użytkownika zrobionym w tej aplikacji**, a bieżący — wyborem zrobionym
     * gdzie indziej. Zapamiętany, którego w pliku już nie ma, ustępuje bieżącemu:
     * klaster bywa kasowany, a moduł nie ma się przez to zaciąć.
     */
    private function chooseContext(string $remembered): void
    {
        foreach ($this->contexts as $context) {
            if ($context->value === $remembered) {
                $this->useContext($context);

                return;
            }
        }

        $current = ClusterInfoParser::currentContext($this->configOutput);

        if ($current !== null) {
            $this->useContext($current);

            return;
        }

        // Ani zapamiętany, ani bieżący — to jest ten stan, który plan kroku każe
        // narysować jako miejsce z wyborem.
        $this->stage = ClusterStage::NoContext;
    }

    private function advanceConfig(): void
    {
        $state = $this->configWork->advance();

        if ($state === null) {
            return;
        }

        if ($state->stage === BackgroundStage::Failed) {
            $this->fail($state->problemKey ?? 'module.k8s.problem.config', $state->problemParameters);

            return;
        }

        $this->configOutput = $state->output;
        $this->contexts = ClusterInfoParser::contexts($state->output);

        if ($this->contexts === []) {
            $this->stage = ClusterStage::NoContext;

            return;
        }

        $this->chooseContext($this->rememberedContext);
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

        $this->fail('module.k8s.problem.unreachable', ['reason' => self::reasonOf($state->errorOutput)]);
    }

    private function askForVersions(): void
    {
        $this->stage = ClusterStage::Reading;
        $this->versionWork->begin(
            KubectlCall::version($this->session->context()),
            $this->session->timeoutSeconds(),
        );
    }

    /** @param array<string, string|int|float> $parameters */
    private function fail(string $problemKey, array $parameters): void
    {
        $this->stage = ClusterStage::Unreachable;
        $this->problemKey = $problemKey;
        $this->problemParameters = $parameters;
    }

    private function applyConfiguredNamespace(): void
    {
        $context = $this->current;

        if ($context === null) {
            return;
        }

        $this->configuredNamespace = ClusterInfoParser::namespaceOf($this->configOutput, $context);

        try {
            $this->session->useNamespace(
                $this->configuredNamespace === null
                    ? NamespaceName::fallback()
                    : NamespaceName::of($this->configuredNamespace),
            );
        } catch (InvalidClusterNameException) {
            $this->session->useNamespace(NamespaceName::fallback());
        }
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
