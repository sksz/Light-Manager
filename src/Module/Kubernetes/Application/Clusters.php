<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Module\Kubernetes\Application\Port\ClusterBookPort;
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

    private ?ClusterBook $book = null;

    private ?string $bookProblem = null;

    /** Czy sekcji jeszcze nigdzie nie było — warunek jednorazowej migracji z ustawień. */
    private bool $fresh = false;

    /** Ile razy zmieniła się odpowiedź — pokolenie kwerendy `k8s.clusters`. */
    private int $revision = 0;

    /** Znacznik przełączenia — zabiera go stan klastra, żeby zapytać o wersje. */
    private bool $switched = false;

    /** Odcisk stanu z poprzedniego taktu — po nim poznaje się, że coś się zmieniło. */
    private string $fingerprint = '';

    public function __construct(
        private readonly ClusterBookPort $bookPort,
        private readonly ConfigCatalog $configs,
        private readonly ClusterSession $session,
    ) {
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
        return $this->loaded()->current();
    }

    public function find(string $name): ?ClusterProfile
    {
        return $this->loaded()->find($name);
    }

    /** Wiersz spisu o podanej nazwie — z obu źródeł, wedle reguły pierwszeństwa. */
    public function row(string $name): ?ClusterRow
    {
        foreach ($this->rows() as $row) {
            if ($row->name === $name && !$row->shadowed) {
                return $row;
            }
        }

        return null;
    }

    /** Dopisuje albo zastępuje wpis własny i zapisuje książkę. */
    public function add(ClusterProfile $entry): void
    {
        $book = $this->loaded();
        $book->add($entry);
        $this->bookPort->save($book);
        $this->configs->want([$entry->kubeconfig]);
        ++$this->revision;
    }

    /** Usuwa wpis własny; wpisu czytanego nie ma jak usunąć — nie jest w książce. */
    public function remove(string $name): bool
    {
        $book = $this->loaded();
        $removed = $book->remove($name);

        if ($removed) {
            $this->bookPort->save($book);
            ++$this->revision;

            if ($this->session->key() === $name) {
                // Bieżący właśnie zniknął: miejsce schodzi do niczego, a stan
                // klastra przy najbliższym takcie wybierze je od nowa.
                $this->session->usePlace(null);
                $this->switched = true;
            }
        }

        return $removed;
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

        $book = $this->loaded();
        $book->makeCurrent($name);
        $this->bookPort->save($book);
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

        foreach ($this->loaded()->all() as $entry) {
            $taken[$entry->name] = true;
            $rows[] = new ClusterRow(
                $entry->name,
                $entry->kubeconfig,
                $entry->context,
                $entry->namespace,
                ClusterOrigin::Own,
                $current === $entry->name,
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
                $shadowed = isset($taken[$context->value]) && $this->loaded()->find($context->value) !== null;
                $name = $shadowed ? $context->value : self::freeName($context->value, $path, $taken);
                $taken[$name] = true;

                $rows[] = new ClusterRow(
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
        $this->loaded();

        return new ClustersView(
            $this->rows(),
            $this->currentName(),
            $this->bookPort->location(),
            $this->configs->isReading(),
            $this->bookProblem ?? $this->configs->problemKey(),
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

        if ($entry !== null) {
            $this->add(ClusterProfile::of($entry->name, $path, $context->value, $entry->namespace, $entry->timeoutSeconds));

            return $this->select($entry->name);
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
     * Przenosi zapamiętane miejsce z pozycji ustawień do książki (plan, punkt 7).
     *
     * Pada **raz**, przy pierwszym wczytaniu książki — bo `LoadedClusterBook`
     * odróżnia „nie ma sekcji" od „sekcja jest i jest pusta". Wartości nie giną,
     * a stara pozycja zostaje w `settings.json` nietknięta: nikt jej już nie
     * czyta, a jej skasowanie nie ma odbiorcy.
     */
    public function migrate(string $context, string $namespace): void
    {
        if ($context === '') {
            return;
        }

        $book = $this->loaded();

        if ($book->find($context) !== null) {
            return;
        }

        try {
            $book->add(ClusterProfile::of($context, self::defaultConfigPath(), $context, $namespace));
        } catch (InvalidClusterNameException) {
            // Zapamiętana nazwa nie do przyjęcia: migracja milczy, bo i tak nie
            // dałoby się jej użyć — a wpis, którego nie ma, nie zaskakuje.
            return;
        }

        $book->makeCurrent($context);
        $this->bookPort->save($book);
        ++$this->revision;
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

        foreach ($this->loaded()->all() as $entry) {
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
    public function rememberNamespace(string $namespace): void
    {
        $entry = $this->find($this->currentName());

        if ($entry === null || $entry->namespace === $namespace) {
            return;
        }

        try {
            $this->add(ClusterProfile::of(
                $entry->name,
                $entry->kubeconfig,
                $entry->context,
                $namespace,
                $entry->timeoutSeconds,
            ));
        } catch (InvalidClusterNameException) {
            // Przestrzeń już przeszła samowalidację po stronie ekranu, więc tu
            // wyjątek znaczy wyłącznie „wpis zmienił się pod nami” — cisza jest
            // właściwa, zapisu po prostu nie ma.
            return;
        }
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

    private function loaded(): ClusterBook
    {
        if ($this->book === null) {
            $loaded = $this->bookPort->load();
            $this->book = $loaded->book;
            $this->bookProblem = $loaded->problemKey;
            $this->fresh = $loaded->fresh;
        }

        return $this->book;
    }

    /** Czy książki jeszcze nie ma — warunek migracji z ustawień. */
    public function isFresh(): bool
    {
        return $this->loaded()->count() === 0 && $this->fresh;
    }
}
