<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Application\Dto\BackgroundStage;
use LightManager\Module\Kubernetes\Application\Port\KubectlPort;
use LightManager\Module\Kubernetes\Domain\ValueObject\ContextName;
use LightManager\Module\Kubernetes\Infrastructure\ClusterInfoParser;

/**
 * Konteksty **wszystkich znanych plików `kubeconfig`** (krok 59).
 *
 * Do kroku 59 moduł czytał jeden plik — domyślny — bo miejsce miało jedną
 * współrzędną. Odtąd czyta ich tyle, ile trzeba: domyślny, ścieżki z `KUBECONFIG`
 * (standard narzędzia, rozdzielone dwukropkami — czytamy je, bo użytkownik już
 * je ma) oraz pliki wskazane wpisami książki.
 *
 * **Pliki czyta się po jednym, po kolei** i to jest wprost reguła 11d: każdy
 * odczyt to proces potomny, a port pracy tłowej prowadzi ich kilka naraz
 * wyłącznie po to, żeby nie ubijały się nawzajem — nie po to, żeby moduł
 * uruchamiał ich pięć w jednej klatce. Kolejka rusza na żądanie
 * (`refresh()`), a posuwa się w takcie.
 *
 * **Plik nieobecny nie jest awarią**, tylko odpowiedzią: zapamiętujemy, że go
 * nie ma, i mówi o tym stan wpisu (`ClusterStage::MissingFile`). Sprawdzamy to
 * **przed** uruchomieniem potomka, bo `kubectl` odpowiada wtedy pustą
 * konfiguracją i kodem zero — czyli nie do odróżnienia od pliku bez kontekstów.
 */
final class ConfigCatalog
{
    /** @var list<string> ścieżki czekające na odczyt, w kolejności zgłoszenia */
    private array $queue = [];

    /** @var array<string, list<ContextName>> konteksty pod ścieżką pliku */
    private array $contexts = [];

    /** @var array<string, ?string> kontekst bieżący pliku (`current-context`) */
    private array $current = [];

    /**
     * Przestrzeń nazw zapisana przy kontekście w pliku — **propozycja, nie
     * nakaz** (zdanie z kroku 52).
     *
     * Klucz jest parą: ścieżka i nazwa kontekstu. Tożsamością miejsca jest
     * wprawdzie nazwa wpisu, ale ta propozycja pochodzi z pliku, więc i klucz
     * musi być tym, czym plik ją opisuje.
     *
     * @var array<string, string>
     */
    private array $namespaces = [];

    /** @var array<string, true> pliki, których nie ma na dysku */
    private array $missing = [];

    /** Ścieżka, której odczyt właśnie trwa. */
    private ?string $reading = null;

    private ?string $problemKey = null;

    private readonly KubectlWork $work;

    public function __construct(
        KubectlPort $kubectl,
        private readonly ClusterSession $session,
    ) {
        $this->work = new KubectlWork($kubectl);
    }

    /**
     * Zamawia odczyt wskazanych plików — **te, których jeszcze nie znamy**.
     *
     * @param list<string> $paths
     */
    public function want(array $paths): void
    {
        foreach ($paths as $path) {
            // Plik **czytany właśnie w tej chwili** też jest zamówiony, choć nie
            // stoi ani w kolejce, ani w odpowiedziach: bez tego warunku takt
            // dokładałby go z powrotem do kolejki co klatkę, a drugi odczyt
            // nadpisywałby wynik pierwszego.
            if ($path === '' || $path === $this->reading || $this->knows($path)) {
                continue;
            }

            if (!in_array($path, $this->queue, true)) {
                $this->queue[] = $path;
            }
        }
    }

    /**
     * Zamawia odczyt **od nowa** — `Ctrl`+`R` i wejście na spis.
     *
     * @param list<string> $paths
     */
    public function refresh(array $paths): void
    {
        $this->contexts = [];
        $this->current = [];
        $this->missing = [];
        $this->namespaces = [];
        $this->queue = [];
        $this->problemKey = null;
        $this->want($paths);
    }

    /** Posuwa kolejkę: odbiera to, co przyszło, i zamawia następny plik. */
    public function advance(): void
    {
        $state = $this->work->advance();

        if ($state !== null && $this->reading !== null) {
            $path = $this->reading;
            $this->reading = null;

            if ($state->stage === BackgroundStage::Failed) {
                // Plik jest, a mimo to nie da się go przeczytać: uszkodzony YAML
                // albo brak praw. Zapamiętujemy pustkę, żeby pytanie nie wracało
                // w każdym takcie, a powód idzie do paska stanu.
                $this->contexts[$path] = [];
                $this->current[$path] = null;
                $this->problemKey = $state->problemKey ?? 'module.k8s.problem.config';
            } else {
                $this->contexts[$path] = ClusterInfoParser::contexts($state->output);
                $this->current[$path] = ClusterInfoParser::currentContext($state->output)?->value;

                foreach ($this->contexts[$path] as $context) {
                    $namespace = ClusterInfoParser::namespaceOf($state->output, $context);

                    if ($namespace !== null) {
                        $this->namespaces[$path . '|' . $context->value] = $namespace;
                    }
                }
            }
        }

        $this->start();
    }

    public function stop(): void
    {
        $this->work->stop();
        $this->reading = null;
    }

    public function isReading(): bool
    {
        return $this->reading !== null || $this->queue !== [];
    }

    public function problemKey(): ?string
    {
        return $this->problemKey;
    }

    /** Czy odpowiedź o ten plik już mamy. */
    public function knows(string $path): bool
    {
        return isset($this->contexts[$path]) || isset($this->missing[$path]);
    }

    public function isMissing(string $path): bool
    {
        return isset($this->missing[$path]);
    }

    /**
     * Konteksty pliku — pusta lista znaczy „nie ma ich" **albo** „jeszcze nie
     * wiemy"; rozróżnia to `knows()`.
     *
     * @return list<ContextName>
     */
    public function contextsOf(string $path): array
    {
        return $this->contexts[$path] ?? [];
    }

    /** Czy plik zna kontekst o tej nazwie — odpowiedź na `UnknownContext`. */
    public function hasContext(string $path, string $context): bool
    {
        foreach ($this->contextsOf($path) as $known) {
            if ($known->value === $context) {
                return true;
            }
        }

        return false;
    }

    /**
     * Przestrzeń nazw zapisana przy kontekście w pliku; `null` — plik jej nie
     * podaje, a wtedy rozstrzyga `default` (tak przyjmuje Kubernetes).
     */
    public function namespaceOf(string $path, string $context): ?string
    {
        return $this->namespaces[$path . '|' . $context] ?? null;
    }

    /** Kontekst wskazany w pliku jako bieżący; `null`, gdy żaden nim nie jest. */
    public function currentOf(string $path): ?string
    {
        return $this->current[$path] ?? null;
    }

    /** @return list<string> ścieżki, o które już pytano — w kolejności odpowiedzi */
    public function known(): array
    {
        return array_keys($this->contexts);
    }

    private function start(): void
    {
        if ($this->reading !== null || $this->queue === [] || $this->work->isWorking()) {
            return;
        }

        $path = array_shift($this->queue);

        // Brak pliku rozstrzyga się **bez procesu potomnego**: `kubectl` oddaje
        // wtedy pustą konfigurację z kodem zero, czyli odpowiedź nie do
        // odróżnienia od pliku bez kontekstów — a to są dwie różne rady.
        if (!is_file($path)) {
            $this->missing[$path] = true;

            return;
        }

        $this->reading = $path;
        $this->work->begin(KubectlCall::contexts($path), $this->session->timeoutSeconds());
    }
}
