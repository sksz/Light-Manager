<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Module\Kubernetes\Application\Port\KubernetesStatePort;
use LightManager\Module\Kubernetes\Domain\Exception\InvalidClusterNameException;
use LightManager\Module\Kubernetes\Domain\ValueObject\ClusterPlace;
use LightManager\Module\Kubernetes\Domain\ValueObject\ClusterProfile;
use LightManager\Module\Kubernetes\Domain\ValueObject\ContextName;
use LightManager\Module\Kubernetes\Domain\ValueObject\NamespaceName;

/**
 * Klastry — **jedno miejsce, w którym „z którym klastrem" jest daną** (krok 59,
 * D96).
 *
 * Odpowiednik `Environments` z kroku 58: trzyma to, co widać (spis z dwóch
 * źródeł, wybór bieżącego), i prowadzi to, co trwa (odczyt kontekstów z plików)
 * — a rysowaniem i mówieniem do użytkownika nie zajmuje się wcale.
 *
 * **Dwa źródła jednej listy** (D96 nr 3) scala się tu wedle trzech reguł planu:
 * pochodzenie jest widoczne, przy zbieżnej nazwie wygrywa wpis własny, a wpisu
 * czytanego nie da się z aplikacji skasować — bo moduł **do `kubeconfig` nie
 * pisze** i to zdanie z kroku 52 zostaje w mocy.
 *
 * **Tożsamością miejsca jest nazwa wiersza, nie nazwa kontekstu** (D96 nr 4).
 * Konteksty tej samej nazwy w dwóch plikach dostają przez to nazwy różne:
 * pierwszy zachowuje swoją, drugi bierze przyrostek z nazwą pliku. Bez tego
 * dwa wiersze byłyby jednym miejscem — czyli dokładnie tym błędem, który ten
 * krok usuwa.
 */
final class Clusters
{
    /** Domyślny plik konfiguracyjny klienta — ten, który ma każdy. */
    public const DEFAULT_CONFIG = '.kube/config';

    /**
     * Wpisy własne — **podane z zewnątrz**, nie czytane stąd (krok 60).
     *
     * Mieszkają w książce adresowej, której z warstwy `Application` nie widać
     * i nie ma po co: czyta ją fasada modułu i podaje tu gotową listę raz na
     * takt. Ta sama droga, co w module Dockera, i z tego samego powodu.
     *
     * @var list<ClusterProfile>
     */
    private array $entries = [];

    /**
     * Zapisy do książki — **zamówienia, nie zapisy** (krok 60).
     *
     * Koordynator nie ma jak pisać po książce: pisze się do niej komendami,
     * a te leżą w `Presentation`. Zostawia więc zamówienie, które moduł zabiera
     * w takcie — wzorem `takeSwitched()`.
     *
     * @var list<array{string, string, string}> identyfikator wpisu, pole, wartość
     */
    private array $pendingWrites = [];

    /** Ile razy zmieniła się odpowiedź — pokolenie kwerendy `k8s.clusters`. */
    private int $revision = 0;

    /** Znacznik przełączenia — zabiera go stan klastra, żeby zapytać o wersje. */
    private bool $switched = false;

    /** Odcisk stanu z poprzedniego taktu — po nim poznaje się, że coś się zmieniło. */
    private string $fingerprint = '';

    public function __construct(
        private readonly KubernetesStatePort $storage,
        private readonly ConfigCatalog $configs,
        private readonly ClusterSession $session,
    ) {
    }

    /**
     * Podaje wpisy własne przeczytane z książki — **raz na takt, przed
     * wszystkim innym** (krok 60).
     *
     * Pokolenie rośnie po **treści**, nie po samym wywołaniu: lista przychodzi
     * trzydzieści razy na sekundę i prawie zawsze jest ta sama.
     *
     * @param list<ClusterProfile> $entries
     */
    public function useEntries(array $entries): void
    {
        if (self::fingerprintOf($entries) === self::fingerprintOf($this->entries)) {
            return;
        }

        $this->entries = $entries;
        $this->configs->want($this->paths());
        ++$this->revision;
    }

    /** @param list<ClusterProfile> $entries */
    private static function fingerprintOf(array $entries): string
    {
        $parts = [];

        foreach ($entries as $entry) {
            $parts[] = $entry->id . ':' . $entry->name . ':' . $entry->kubeconfig
                . ':' . $entry->context . ':' . $entry->namespace . ':' . ($entry->timeoutSeconds ?? 0);
        }

        return implode('|', $parts);
    }

    /**
     * Zabiera zamówione zapisy — **zabierane, nie oglądane** (wzorem
     * `takeSwitched()`).
     *
     * @return list<array{string, string, string}>
     */
    public function takePendingWrites(): array
    {
        $writes = $this->pendingWrites;
        $this->pendingWrites = [];

        return $writes;
    }

    /** Świeży odczyt wszystkich plików — ekran woła to przy wejściu na spis i na `Ctrl`+`R`. */
    public function refresh(): void
    {
        $this->configs->refresh($this->paths());
        ++$this->revision;
    }

    /**
     * Takt: posunięcie odczytu plików i podbicie pokolenia, gdy odpowiedź na
     * cokolwiek się zmieniła.
     */
    public function tick(): void
    {
        $this->configs->want($this->paths());
        $this->configs->advance();

        $fingerprint = $this->currentName()
            . '|' . count($this->configs->known())
            . '|' . ($this->configs->isReading() ? 'r' : '-')
            . '|' . ($this->configs->problemKey() ?? '');

        if ($fingerprint !== $this->fingerprint) {
            $this->fingerprint = $fingerprint;
            ++$this->revision;
        }
    }

    public function revision(): int
    {
        return $this->revision;
    }

    /** Znacznik przełączenia — **zabierany, nie oglądany** (wzorem `takeOutcome()`). */
    public function takeSwitched(): bool
    {
        $switched = $this->switched;
        $this->switched = false;

        return $switched;
    }

    public function currentName(): string
    {
        return $this->storage->current();
    }

    /** Wpis własny wskazany identyfikatorem albo nazwą — spis jest krótki. */
    public function find(string $key): ?ClusterProfile
    {
        foreach ($this->entries as $entry) {
            if ($entry->id === $key || $entry->name === $key) {
                return $entry;
            }
        }

        return null;
    }

    /** Wiersz spisu o podanej nazwie — z obu źródeł, wedle reguły pierwszeństwa. */
    /**
     * Wiersz spisu wskazany **identyfikatorem albo nazwą** — z obu źródeł,
     * wedle reguły pierwszeństwa.
     *
     * Identyfikator wygrywa, bo jest tożsamością (krok 60); nazwa zostaje drogą
     * dla kontekstów czytanych z plików, które identyfikatora nie mają, i dla
     * wskazań sprzed migracji.
     */
    public function row(string $key): ?ClusterRow
    {
        foreach ($this->rows() as $row) {
            if ($row->id !== '' && $row->id === $key) {
                return $row;
            }
        }

        foreach ($this->rows() as $row) {
            if ($row->name === $key && !$row->shadowed) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Wybiera klaster bieżący. Oddaje klucz powodu, gdy wybrać się nie da.
     *
     * Zapisuje wybór w książce, żeby przeżył uruchomienie — nawet gdy wybrano
     * wiersz **czytany**, bo nazwa wiersza jest jego tożsamością tak samo, jak
     * nazwa wpisu własnego.
     */
    public function select(string $name): ?string
    {
        $row = $this->row($name);

        if ($row === null) {
            return 'module.k8s.cluster.problem.unknown';
        }

        try {
            $this->session->usePlace(
                ClusterPlace::of($row->kubeconfig, ContextName::of($row->context)),
                $row->name,
                $row->entry?->timeoutSeconds,
            );
        } catch (InvalidClusterNameException) {
            return 'module.k8s.cluster.problem.unknown';
        }

        $this->applyNamespace($row);

        $this->storage->makeCurrent($row->id === '' ? $row->name : $row->id);
        $this->switched = true;
        ++$this->revision;

        return null;
    }

    /**
     * Wybiera miejsce na starcie: zapamiętane, a w jego braku — bieżący kontekst
     * domyślnego pliku.
     *
     * Kolejność jest taka, a nie odwrotna, bo zapamiętane jest **wyborem
     * użytkownika zrobionym w tej aplikacji**, a bieżące — wyborem zrobionym
     * gdzie indziej. Zapamiętane, którego już nie ma, ustępuje bieżącemu:
     * klaster bywa kasowany, a moduł nie ma się przez to zaciąć.
     *
     * Oddaje `false`, gdy nie ma czego wybrać — wtedy rozstrzyga `ClusterStage`.
     */
    public function chooseCurrent(): bool
    {
        $current = $this->currentName();

        if ($current !== '' && $this->select($current) === null) {
            return true;
        }

        $default = self::defaultConfigPath();
        $configured = $this->configs->currentOf($default);

        foreach ($this->rows() as $row) {
            if ($row->shadowed || $row->kubeconfig !== $default || $row->context !== $configured) {
                continue;
            }

            return $this->select($row->name) === null;
        }

        return false;
    }

    /**
     * Spis z obu źródeł: wpisy własne, potem konteksty czytane z plików.
     *
     * @return list<ClusterRow>
     */
    public function rows(): array
    {
        $current = $this->currentName();
        $rows = [];
        $taken = [];

        foreach ($this->entries as $entry) {
            $taken[$entry->name] = true;
            $rows[] = new ClusterRow(
                $entry->id,
                $entry->name,
                $entry->kubeconfig,
                $entry->context,
                $entry->namespace,
                ClusterOrigin::Own,
                // Bieżący poznaje się po **identyfikatorze**, a nazwa zostaje
                // drogą zapasową dla wskazań sprzed migracji (krok 60).
                $current === $entry->id || $current === $entry->name,
                shadowed: false,
                entry: $entry,
            );
        }

        $default = self::defaultConfigPath();

        foreach ($this->configs->known() as $path) {
            $origin = $path === $default ? ClusterOrigin::DefaultConfig : ClusterOrigin::EnvConfig;

            foreach ($this->configs->contextsOf($path) as $context) {
                // Nazwa zajęta znaczy **inne miejsce o tej samej nazwie**, więc
                // wiersz bierze przyrostek z nazwą pliku; wpis własny o tej
                // nazwie przysłania kontekst czytany, jak w kroku 58.
                $shadowed = isset($taken[$context->value]) && $this->find($context->value) !== null;
                $name = $shadowed ? $context->value : self::freeName($context->value, $path, $taken);
                $taken[$name] = true;

                $rows[] = new ClusterRow(
                    '',
                    $name,
                    $path,
                    $context->value,
                    '',
                    $origin,
                    !$shadowed && $current === $name,
                    $shadowed,
                    entry: null,
                );
            }
        }

        return $rows;
    }

    public function view(): ClustersView
    {
        return new ClustersView(
            $this->rows(),
            $this->currentName(),
            $this->storage->location(),
            $this->configs->isReading(),
            $this->configs->problemKey(),
        );
    }

    /** Czy odczyt któregoś z plików jeszcze trwa. */
    public function isReading(): bool
    {
        return $this->configs->isReading();
    }

    /**
     * Konteksty pliku miejsca bieżącego — materiał okna wyboru **wewnątrz
     * wpisu** (plan, punkt 5: plik ma zwykle więcej niż jeden kontekst).
     *
     * Bez miejsca bieżącego — konteksty pliku domyślnego, bo to jedyny, o który
     * ma sens pytać, zanim cokolwiek wybrano.
     *
     * @return list<ContextName>
     */
    public function contextsOfCurrentFile(): array
    {
        $path = $this->session->place()->kubeconfig ?? self::defaultConfigPath();

        return $this->configs->contextsOf($path);
    }

    /**
     * Przestawia się na inny kontekst **w tym samym pliku** — droga wewnątrz
     * wpisu.
     *
     * Wpis książki zmienia przez to swój kontekst i zapisuje się; miejsce
     * czytane staje się nowym wierszem czytanym, bo do `kubeconfig` moduł nie
     * pisze i wybór ma gdzie przeżyć wyłącznie we własnej książce.
     */
    public function selectContext(ContextName $context): ?string
    {
        $place = $this->session->place();
        $path = $place->kubeconfig ?? self::defaultConfigPath();
        $entry = $this->find($this->currentName());

        if ($entry !== null && $entry->id !== '') {
            // Wpis własny przestawia się **zamówieniem**, bo pisze się do niego
            // komendą książki (krok 60). Wybór idzie od razu — zapis dojdzie
            // w tym samym takcie, a spis i tak czyta się na nowo.
            $this->pendingWrites[] = [$entry->id, 'kubeconfig', $path];
            $this->pendingWrites[] = [$entry->id, 'context', $context->value];

            return $this->select($entry->id);
        }

        foreach ($this->rows() as $row) {
            if (!$row->shadowed && $row->kubeconfig === $path && $row->context === $context->value) {
                return $this->select($row->name);
            }
        }

        return 'module.k8s.cluster.problem.unknown';
    }

    /**
     * Etap miejsca bieżącego, o ile rozstrzyga go **plik**, a nie klaster.
     *
     * `null` znaczy „z plikiem wszystko w porządku" — wtedy o etapie mówi
     * odpowiedź klastra (`ClusterState`). Odpowiedź pada dopiero, gdy plik jest
     * przeczytany: dopóki trwa odczyt, nie wiadomo jeszcze nic.
     */
    public function fileStage(): ?ClusterStage
    {
        $place = $this->session->place();

        if ($place === null || !$this->configs->knows($place->kubeconfig)) {
            return null;
        }

        if ($this->configs->isMissing($place->kubeconfig)) {
            return ClusterStage::MissingFile;
        }

        $context = $place->context?->value;

        if ($context !== null && !$this->configs->hasContext($place->kubeconfig, $context)) {
            return ClusterStage::UnknownContext;
        }

        return null;
    }

    /**
     * Parametry zdania o etapie — ścieżka i kontekst, czyli to, co poprawić.
     *
     * @return array<string, string>
     */
    public function stageParameters(): array
    {
        $place = $this->session->place();

        return [
            'path' => $place->kubeconfig ?? '',
            'context' => $place->context->value ?? '',
        ];
    }

    /**
     * Ścieżki wszystkich znanych plików: domyślny, te z `KUBECONFIG` i te
     * z wpisów książki.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        $paths = [self::defaultConfigPath()];

        foreach (self::envPaths() as $path) {
            $paths[] = $path;
        }

        foreach ($this->entries as $entry) {
            $paths[] = $entry->kubeconfig;
        }

        return array_values(array_unique($paths));
    }

    /** Domyślny `~/.kube/config`; katalog domowy tą samą drogą, co w konfiguracji. */
    public static function defaultConfigPath(): string
    {
        $home = getenv('HOME');

        if (!is_string($home) || $home === '') {
            $working = getcwd();
            $home = $working === false ? '.' : $working;
        }

        return rtrim($home, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::DEFAULT_CONFIG;
    }

    /**
     * Ścieżki ze zmiennej `KUBECONFIG` — **standard narzędzia**, rozdzielone
     * dwukropkami (plan, punkt 2).
     *
     * @return list<string>
     */
    public static function envPaths(): array
    {
        $value = getenv('KUBECONFIG');

        if (!is_string($value) || $value === '') {
            return [];
        }

        $paths = [];

        foreach (explode(PATH_SEPARATOR, $value) as $path) {
            $trimmed = trim($path);

            if ($trimmed !== '') {
                $paths[] = $trimmed;
            }
        }

        return $paths;
    }

    /**
     * Przestrzeń nazw wybranego miejsca — **trzy źródła w ustalonej kolejności**:
     * własna wpisu, ta zapisana przy kontekście w pliku, a na końcu `default`.
     *
     * Środkowe źródło jest zdaniem z kroku 52 („propozycja, nie nakaz”)
     * i zostaje w mocy: wpis z pustą przestrzenią bierze to, co mówi plik, więc
     * `kubeconfig` przygotowany przez cudze narzędzie działa bez poprawiania.
     */
    private function applyNamespace(ClusterRow $row): void
    {
        $namespace = $row->namespace !== ''
            ? $row->namespace
            : $this->configs->namespaceOf($row->kubeconfig, $row->context);

        try {
            $this->session->useNamespace(
                $namespace === null || $namespace === ''
                    ? NamespaceName::fallback()
                    : NamespaceName::of($namespace),
            );
        } catch (InvalidClusterNameException) {
            $this->session->useNamespace(NamespaceName::fallback());
        }
    }

    /**
     * Zapamiętuje przestrzeń nazw **przy wpisie**, gdy miejsce bieżące jest
     * wpisem własnym (krok 59).
     *
     * To jest droga, którą przestrzeń zmieniona klawiszem `n` przeżywa
     * uruchomienie — łańcuch okien o nią nie pyta, bo pusta wartość ma
     * znaczenie, a `PromptOverlay` pustego pola nie zatwierdza (krok 41).
     * Wiersz czytany z cudzego pliku przestrzeni nie zapamiętuje: nie ma gdzie,
     * a do `kubeconfig` moduł nie pisze.
     */
    /**
     * Zamawia zapamiętanie przestrzeni nazw przy bieżącym wpisie.
     *
     * **Zamawia, a nie zapisuje** (krok 60): wpisy mieszkają w książce, a pisze
     * się do niej komendami, których z tej warstwy nie widać. Moduł zabiera
     * zamówienie w takcie i wykonuje je tą samą drogą, co użytkownik w oknie
     * komend. Kontekstu czytanego z pliku to nie dotyczy — nie ma wpisu, więc
     * nie ma czego zapamiętać.
     */
    public function rememberNamespace(string $namespace): void
    {
        $entry = $this->find($this->currentName());

        if ($entry === null || $entry->id === '' || $entry->namespace === $namespace) {
            return;
        }

        $this->pendingWrites[] = [$entry->id, 'namespace', $namespace];
    }

    /**
     * Wolna nazwa dla kontekstu czytanego: własna, a przy kolizji — z nazwą
     * pliku w nawiasie.
     *
     * @param array<string, true> $taken
     */
    private static function freeName(string $context, string $path, array $taken): string
    {
        if (!isset($taken[$context])) {
            return $context;
        }

        $candidate = $context . ' (' . basename($path) . ')';

        if (!isset($taken[$candidate])) {
            return $candidate;
        }

        // Dwa pliki o tej samej nazwie w różnych katalogach: rozstrzyga pełna
        // ścieżka, bo ona jest jedyną rzeczą, która na pewno się różni.
        return $context . ' (' . $path . ')';
    }
}
