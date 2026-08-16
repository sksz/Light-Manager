<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Application\Dto\BackgroundStage;
use LightManager\Module\Kubernetes\Application\Port\KubectlPort;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceKind;
use LightManager\Module\Kubernetes\Infrastructure\ResourceJsonParser;

/**
 * Zasoby rodzajów, które użytkownik rozwinął (krok 52).
 *
 * **Jedna praca naraz, mimo że port prowadzi kilka** — i jest to
 * rozstrzygnięcie, nie ograniczenie. Drzewo pozwala rozwinąć dziesięć rodzajów
 * jednocześnie, a odświeżanie każdego z nich z zegara znaczyłoby dziesięć
 * procesów potomnych co odstęp. Zamiast tego: **czyta się gałąź, którą właśnie
 * rozwinięto, i odświeża tę, na której stoi kursor** — dokładnie wzorem kroku 31
 * („odczyt gałęzi na żądanie i najwyżej jeden na klatkę”), z tą różnicą, że tam
 * kosztował odczyt katalogu z dysku, a tutaj proces.
 *
 * Zapamiętane wiersze **przeżywają zwinięcie gałęzi**, ale nie przeżywają zmiany
 * kontekstu ani przestrzeni nazw: pody z innego klastra pokazane pod tą samą
 * nazwą rodzaju byłyby kłamstwem, którego nie widać.
 */
final class ResourceCache
{
    /** @var array<string, list<ResourceRow>> adres rodzaju → jego wiersze */
    private array $rows = [];

    /** @var array<string, float> adres rodzaju → chwila ostatniego odczytu */
    private array $readAt = [];

    private readonly KubectlWork $work;

    /** Rodzaj, na który czekamy — `null`, gdy nie czekamy na nic. */
    private ?ResourceKind $pending = null;

    private ?string $problemKey = null;

    /** @var array<string, string|int|float> */
    private array $problemParameters = [];

    private int $generation = -1;

    public function __construct(
        KubectlPort $kubectl,
        private readonly ClusterSession $session,
    ) {
        $this->work = new KubectlWork($kubectl);
    }

    /**
     * Wiersze rodzaju — puste, dopóki nikt o nie nie poprosił.
     *
     * @return list<ResourceRow>
     */
    public function rowsOf(ResourceKind $kind): array
    {
        $this->forgetOnGenerationChange();

        return $this->rows[$kind->address()] ?? [];
    }

    /**
     * Przestrzenie nazw stojące w wierszach, które już wczytano — **źródło
     * kwerendy `k8s.namespaces`** (krok 54).
     *
     * Nie pyta klastra o nic i pytać nie ma prawa: to jest odpowiedź złożona
     * z tego, co i tak leży w pamięci po obejrzeniu zasobów. Rodzaje bez
     * przestrzeni (węzły, przestrzenie same) nie wnoszą nic, bo ich wiersze mają
     * `namespace === null`.
     *
     * @return list<string> alfabetycznie, bez powtórzeń
     */
    public function namespacesSeen(): array
    {
        $this->forgetOnGenerationChange();

        $names = [];

        foreach ($this->rows as $rows) {
            foreach ($rows as $row) {
                if ($row->namespace !== null && $row->namespace !== '') {
                    $names[$row->namespace] = true;
                }
            }
        }

        $names = array_keys($names);
        sort($names);

        return $names;
    }

    public function knows(ResourceKind $kind): bool
    {
        $this->forgetOnGenerationChange();

        return array_key_exists($kind->address(), $this->rows);
    }

    public function isWorking(): bool
    {
        return $this->work->isWorking();
    }

    /** Rodzaj, na którego odpowiedź czekamy — ekran mówi o nim w nagłówku. */
    public function pending(): ?ResourceKind
    {
        return $this->pending;
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

    /**
     * Zamawia odczyt rodzaju — **jeśli nie mamy go już świeżego**.
     *
     * `force` znaczy „użytkownik nacisnął `Ctrl`+`R`” i pomija zarówno pamięć,
     * jak i odstęp: prośba wprost jest zawsze ważniejsza od oszczędności.
     */
    public function load(ResourceKind $kind, bool $force = false): void
    {
        $this->forgetOnGenerationChange();

        if (!$this->session->isTargeted() || $this->work->isWorking()) {
            return;
        }

        if (!$force && $this->knows($kind)) {
            return;
        }

        $this->pending = $kind;
        $this->work->begin(
            KubectlCall::list($kind, $this->session->namespace(), $this->session->context()),
            $this->session->timeoutSeconds(),
        );
    }

    /**
     * Odświeżenie z zegara — **wyłącznie dla rodzaju wskazanego przez wołającego**.
     *
     * Wołającym jest ekran i podaje rodzaj, na którym stoi kursor; zegar chodzi
     * tylko wtedy, gdy ekran jest widoczny (D91 nr 7). Rodzaje rozwinięte, ale
     * nieoglądane, zostają takie, jakie były — i mówi o tym wiek listy pokazany
     * w nagłówku, żeby nikt nie wziął starych wierszy za świeże.
     */
    public function refreshDue(?ResourceKind $kind, float $now, int $everySeconds): void
    {
        if ($kind === null || $this->work->isWorking()) {
            return;
        }

        $last = $this->readAt[$kind->address()] ?? 0.0;

        if ($now - $last < $everySeconds) {
            return;
        }

        $this->load($kind, force: true);
    }

    /** Kiedy ostatnio czytaliśmy ten rodzaj — `null`, gdy nigdy. */
    public function readAt(ResourceKind $kind): ?float
    {
        return $this->readAt[$kind->address()] ?? null;
    }

    public function advance(float $now): void
    {
        $state = $this->work->advance();
        $kind = $this->pending;

        if ($state === null || $kind === null) {
            return;
        }

        $this->pending = null;

        if ($state->stage === BackgroundStage::Failed) {
            $this->fail($state->problemKey ?? 'module.' . KubernetesSettings::ID . '.problem.list', []);

            return;
        }

        if (($state->exitCode ?? 0) !== 0) {
            // Kod niezerowy przy `get` znaczy zwykle „nie masz prawa” albo „nie
            // ma takiego rodzaju w tej wersji” — powód stoi na strumieniu błędów
            // i jest jedyną rzeczą, którą warto pokazać.
            $this->fail(
                'module.' . KubernetesSettings::ID . '.problem.rejected',
                ['reason' => self::reasonOf($state->errorOutput)],
            );

            return;
        }

        $this->rows[$kind->address()] = ResourceJsonParser::rows($state->output, $kind);
        $this->readAt[$kind->address()] = $now;
        $this->problemKey = null;
        $this->problemParameters = [];
    }

    public function stop(): void
    {
        $this->work->stop();
        $this->pending = null;
    }

    /** Zapomina wszystko, co przyszło z poprzedniego miejsca. */
    private function forgetOnGenerationChange(): void
    {
        if ($this->generation === $this->session->generation()) {
            return;
        }

        $this->generation = $this->session->generation();
        $this->rows = [];
        $this->readAt = [];
    }

    /** @param array<string, string|int|float> $parameters */
    private function fail(string $problemKey, array $parameters): void
    {
        $this->problemKey = $problemKey;
        $this->problemParameters = $parameters;
    }

    private static function reasonOf(string $errorOutput): string
    {
        $first = strtok(trim($errorOutput), "\n");

        return $first === false ? '' : $first;
    }
}
