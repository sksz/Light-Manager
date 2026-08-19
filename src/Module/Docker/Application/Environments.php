<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Module\Docker\Application\Port\ContextCatalogPort;
use LightManager\Module\Docker\Application\Port\DockerStatePort;
use LightManager\Module\Docker\Application\Port\TunnelPort;
use LightManager\Module\Docker\Domain\ValueObject\DockerEnvironment;
use LightManager\Module\Docker\Domain\ValueObject\EnvironmentKind;

/**
 * Środowiska Dockera — **jedno miejsce, w którym „z którym demonem" jest daną**
 * (krok 58, D96).
 *
 * Klasa jest odpowiednikiem `SshSession` z kroku 48: trzyma to, co widać
 * (spis z dwóch źródeł, wybór bieżącego, stan tunelu), i prowadzi to, co trwa
 * (odczyt kontekstów klienta, podnoszenie tunelu) — a rysowaniem i mówieniem
 * do użytkownika nie zajmuje się wcale.
 *
 * **Dwa źródła jednej listy** (D96 nr 3) scala się tu wedle trzech reguł
 * planu: pochodzenie jest widoczne, przy zbieżnej nazwie wygrywa wpis własny
 * (kolizja zostaje w spisie jako wiersz przysłonięty, nie znika po cichu),
 * a brak klienta `docker` nie jest awarią — lista schodzi wtedy do wpisów
 * własnych plus gniazda lokalnego.
 *
 * **Punkt końcowy rozmowy liczy się tutaj**, bo tylko tu widać wszystkie trzy
 * składniki naraz: wpis, stan tunelu i konteksty. Usługa gniazda dostaje
 * gotową daną (`DockerEndpoint`) i nie wie, skąd się wzięła — to jest całe
 * „przestaje być stałą" z punktu 3 planu.
 */
final class Environments
{
    /**
     * Wpisy własne — **podane z zewnątrz**, nie czytane stąd (krok 60).
     *
     * Mieszkają w książce adresowej, a książki nie widać z warstwy `Application`
     * i nie ma po co: czyta ją fasada modułu i **podaje tu gotową listę raz na
     * takt**. Koordynator nie wie, że książka istnieje — dostaje wpisy tak samo,
     * jak dostawał je z własnego pliku, i tyle się dla niego zmieniło.
     *
     * @var list<DockerEnvironment>
     */
    private array $entries = [];

    /** Ile razy zmieniła się odpowiedź — pokolenie kwerendy `docker.environments`. */
    private int $revision = 0;

    /** Znacznik przełączenia — zabiera go takt modułu, żeby unieważnić listy. */
    private bool $switched = false;

    /** Cel tunelu rozstrzygnięty przy wyborze — trzy napisy z `ssh.hosts` albo wpis wprost. */
    private ?string $resolvedTarget = null;

    private int $resolvedPort = DockerEnvironment::DEFAULT_TUNNEL_PORT;

    /** Odcisk stanu z poprzedniego taktu — po nim poznaje się, że coś się zmieniło. */
    private string $fingerprint = '';

    public function __construct(
        private readonly DockerStatePort $storage,
        private readonly ContextCatalogPort $contexts,
        private readonly TunnelPort $tunnel,
    ) {
    }

    /**
     * Podaje wpisy własne przeczytane z książki — **raz na takt, przed
     * wszystkim innym** (krok 60).
     *
     * Podbicie pokolenia zależy od **treści**, a nie od samego wywołania:
     * lista przychodzi trzydzieści razy na sekundę i prawie zawsze jest ta
     * sama, a pokolenie, które rośnie co klatkę, unieważniałoby pamięć rejestru
     * kwerend przy każdym rysowaniu.
     *
     * @param list<DockerEnvironment> $entries
     */
    public function useEntries(array $entries): void
    {
        if (self::fingerprintOf($entries) === self::fingerprintOf($this->entries)) {
            return;
        }

        $this->entries = $entries;
        ++$this->revision;
    }

    /** @param list<DockerEnvironment> $entries */
    private static function fingerprintOf(array $entries): string
    {
        $parts = [];

        foreach ($entries as $entry) {
            $parts[] = $entry->id . ':' . $entry->name . ':' . $entry->kind->value
                . ':' . $entry->target . ':' . $entry->port . ':' . $entry->socketPath;
        }

        return implode('|', $parts);
    }

    /** Zamawia świeży odczyt kontekstów klienta — ekran woła to przy otwarciu i na `Ctrl`+`R`. */
    public function refresh(): void
    {
        $this->contexts->refresh();
    }

    /**
     * Takt: posunięcie odczytu kontekstów i tunelu oraz podbicie pokolenia,
     * gdy odpowiedź na cokolwiek się zmieniła.
     */
    public function tick(): void
    {
        $this->contexts->advance();
        $this->tunnel->advance();

        $tunnel = $this->tunnel->state();
        $fingerprint = $this->currentName()
            . '|' . count($this->contexts->all())
            . '|' . ($this->contexts->isReading() ? 'r' : '-')
            . '|' . ($this->contexts->problemKey() ?? '')
            . '|' . $tunnel->stage->value
            . '|' . ($tunnel->problemKey ?? '');

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

    /**
     * Wskazanie bieżącego środowiska: **identyfikator wpisu albo nazwa
     * kontekstu** (krok 60).
     *
     * Dwa znaczenia jednego napisu, bo bieżącym bywa wpis książki (ma
     * identyfikator) albo kontekst czytany z cudzego pliku (ma samą nazwę).
     * Rozstrzyga o tym spis złożony z obu źródeł, nie ten napis.
     */
    public function currentName(): string
    {
        $current = $this->storage->current();

        return $current === '' ? DockerEnvironment::DEFAULT_NAME_LOCAL : $current;
    }

    public function tunnel(): TunnelState
    {
        return $this->tunnel->state();
    }

    /** Wpis własny wskazany identyfikatorem albo nazwą — spis jest krótki, więc szuka się po obu. */
    public function find(string $key): ?DockerEnvironment
    {
        foreach ($this->entries as $entry) {
            if ($entry->id === $key || $entry->name === $key) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Wiersz spisu wskazany **identyfikatorem albo nazwą** — z obu źródeł,
     * wedle reguły pierwszeństwa.
     *
     * Identyfikator wygrywa, bo jest tożsamością; nazwa zostaje drogą dla
     * kontekstów klienta, które identyfikatora nie mają.
     */
    public function row(string $key): ?EnvironmentRow
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
     * Wybiera środowisko bieżące. Oddaje klucz powodu, gdy wybrać się nie da.
     *
     * @param ?string $resolvedTarget cel tunelu rozstrzygnięty przez wołającego
     *                                (kwerendą `ssh.hosts` albo z wpisu wprost) —
     *                                wymagany wyłącznie dla wpisu rodzaju tunel
     * @param ?string $password       hasło do celu tunelu — `null` znaczy klucz
     *                                albo agent (D102 nr 4). Znika razem z tym
     *                                wywołaniem: koordynator go nie pamięta
     */
    public function select(
        string $name,
        ?string $resolvedTarget = null,
        ?int $resolvedPort = null,
        ?string $password = null,
    ): ?string {
        $row = $this->row($name);

        if ($row === null) {
            return 'module.docker.env.problem.unknown';
        }

        $entry = $row->entry;

        if ($row->origin !== EnvironmentOrigin::Own && $row->socketPath === null) {
            // Kontekst klienta o adresie, którym moduł nie umie rozmawiać
            // (`ssh://`, `npipe://`): droga `docker -H ssh://` została odrzucona
            // twardo (D96 nr 2), więc wybór odmawia zdaniem, a nie milczy.
            return 'module.docker.env.problem.unusableContext';
        }

        // Tunel poprzedniego środowiska schodzi zawsze — razem z gniazdem.
        $this->tunnel->close();
        $this->resolvedTarget = null;
        $this->resolvedPort = DockerEnvironment::DEFAULT_TUNNEL_PORT;

        if ($entry !== null && $entry->kind === EnvironmentKind::SshTunnel) {
            if ($resolvedTarget === null || $resolvedTarget === '') {
                return 'module.docker.env.problem.unknownHost';
            }

            $this->resolvedTarget = $resolvedTarget;
            $this->resolvedPort = $resolvedPort ?? $entry->port;
            $this->tunnel->open($entry->name, $this->resolvedTarget, $this->resolvedPort, $entry->socketPath, $password);
        }

        $this->storage->makeCurrent($row->id === '' ? $row->name : $row->id);
        $this->switched = true;
        ++$this->revision;

        return null;
    }

    /**
     * Spis z obu źródeł: wpisy własne, konteksty klienta, a gdy kontekstów nie
     * ma — gniazdo lokalne dopisane przez moduł.
     *
     * @return list<EnvironmentRow>
     */
    public function rows(): array
    {
        $current = $this->currentName();
        $rows = [];
        $ownNames = [];

        foreach ($this->entries as $entry) {
            $ownNames[$entry->name] = true;
            $rows[] = $this->ownRow($entry, $current);
        }

        $contexts = $this->contexts->all();

        foreach ($contexts as $context) {
            $rows[] = self::contextRow($context, $current, isset($ownNames[$context->name]));
        }

        if ($contexts === []) {
            // Brak klienta nie jest awarią (D96 nr 3): gniazdo lokalne stoi
            // w spisie zawsze, żeby było do czego wrócić.
            $rows[] = new EnvironmentRow(
                '',
                DockerEnvironment::DEFAULT_NAME_LOCAL,
                EnvironmentKind::LocalSocket->value,
                DockerEnvironment::DEFAULT_SOCKET,
                DockerEnvironment::DEFAULT_SOCKET,
                EnvironmentOrigin::Default,
                $current === DockerEnvironment::DEFAULT_NAME_LOCAL && !isset($ownNames[DockerEnvironment::DEFAULT_NAME_LOCAL]),
                shadowed: isset($ownNames[DockerEnvironment::DEFAULT_NAME_LOCAL]),
                entry: null,
                socketPath: DockerEnvironment::DEFAULT_SOCKET,
            );
        }

        return $rows;
    }

    public function view(): EnvironmentBookView
    {
        return new EnvironmentBookView(
            $this->rows(),
            $this->currentName(),
            $this->storage->location(),
            $this->tunnel->state(),
            $this->contexts->isReading(),
            $this->contexts->problemKey(),
        );
    }

    /**
     * Punkt końcowy rozmowy z demonem — dla usługi gniazda, raz na takt.
     *
     * Tunel, który jeszcze nie stoi, oddaje powód zamiast adresu: pytanie
     * zadane w tej chwili nie ma dokąd pójść, a gniazdo po nieżyjącym tunelu
     * jest gorsze od braku gniazda — `connect()` w nie trafia i wisi.
     */
    public function endpoint(): DockerEndpoint
    {
        $row = $this->row($this->currentName());

        if ($row === null) {
            // Nazwa bieżąca wskazuje donikąd (kontekst zniknął z pliku klienta,
            // wpis skasowany ręcznie): rozmowa idzie gniazdem lokalnym, czyli
            // stanem sprzed pierwszego wyboru.
            return DockerEndpoint::unixSocket(DockerEnvironment::DEFAULT_SOCKET);
        }

        $entry = $row->entry;

        if ($entry === null) {
            return $row->socketPath !== null
                ? DockerEndpoint::unixSocket($row->socketPath)
                : DockerEndpoint::notReady('module.docker.env.problem.unusableContext');
        }

        return match ($entry->kind) {
            EnvironmentKind::LocalSocket => DockerEndpoint::unixSocket($entry->socketPath),
            EnvironmentKind::Tcp => DockerEndpoint::tls(
                $entry->target,
                $entry->port,
                $entry->certPath ?? '',
                $entry->keyPath ?? '',
                $entry->caPath ?? '',
            ),
            EnvironmentKind::SshTunnel => $this->tunnelEndpoint(),
        };
    }

    /**
     * Przedrostek wiersza polecenia dla compose — zmienna środowiskowa idzie
     * **przedrostkiem, a nie tablicą `env`**, bo port pracy tłowej bierze napis
     * (czwarta trudność planu).
     *
     * `DOCKER_CERT_PATH` to **katalog**, a nie trzy pliki: tak czyta go klient.
     * Bierze się go z katalogu certyfikatu klienta, więc komplet dla compose
     * musi leżeć w jednym miejscu pod nazwami `cert.pem`/`key.pem`/`ca.pem` —
     * rozmowa po gnieździe używa trzech ścieżek wpisu wprost i tego wymogu
     * nie ma.
     */
    public function composePrefix(): string
    {
        $endpoint = $this->endpoint();

        if ($endpoint->socketPath !== null) {
            return 'DOCKER_HOST=' . escapeshellarg('unix://' . $endpoint->socketPath) . ' ';
        }

        if ($endpoint->isTls()) {
            return 'DOCKER_HOST=' . escapeshellarg('tcp://' . ($endpoint->host ?? '') . ':' . $endpoint->port)
                . ' DOCKER_TLS_VERIFY=1'
                . ' DOCKER_CERT_PATH=' . escapeshellarg(dirname($endpoint->certPath ?? '/')) . ' ';
        }

        return '';
    }

    /** Czy środowisko bieżące jest czymś innym niż gniazdem tej maszyny. */
    public function isRemote(): bool
    {
        $entry = $this->row($this->currentName())?->entry;

        return $entry !== null && $entry->kind !== EnvironmentKind::LocalSocket;
    }

    private function tunnelEndpoint(): DockerEndpoint
    {
        $state = $this->tunnel->state();

        return match ($state->stage) {
            TunnelStage::Up => DockerEndpoint::unixSocket($state->socketPath ?? ''),
            TunnelStage::Starting => DockerEndpoint::notReady('module.docker.tunnel.waiting'),
            TunnelStage::Failed => DockerEndpoint::notReady(
                $state->problemKey ?? 'module.docker.tunnel.failedShort',
                $state->problemParameters,
            ),
            TunnelStage::None => DockerEndpoint::notReady('module.docker.tunnel.down'),
        };
    }

    private function ownRow(DockerEnvironment $entry, string $current): EnvironmentRow
    {
        $publicAddress = match ($entry->kind) {
            EnvironmentKind::LocalSocket => $entry->socketPath,
            // Cel SSH do wierszy kwerendy nie wchodzi (reguła 11w) — adresem
            // publicznym tunelu jest gniazdo lokalne, gdy stoi.
            EnvironmentKind::SshTunnel => $this->tunnel->state()->socketPath ?? '',
            EnvironmentKind::Tcp => 'https://' . $entry->target . ':' . $entry->port,
        };

        return new EnvironmentRow(
            $entry->id,
            $entry->name,
            $entry->kind->value,
            $entry->label(),
            $publicAddress,
            EnvironmentOrigin::Own,
            // Bieżący poznaje się po **identyfikatorze**, a nazwa zostaje drogą
            // zapasową dla wskazań sprzed migracji (krok 60).
            $current === $entry->id || $current === $entry->name,
            shadowed: false,
            entry: $entry,
            socketPath: null,
        );
    }

    private static function contextRow(ContextEntry $context, string $current, bool $shadowed): EnvironmentRow
    {
        $socket = $context->socketPath();
        $kind = $socket !== null
            ? EnvironmentKind::LocalSocket->value
            : (string) strstr($context->endpoint, ':', before_needle: true);

        return new EnvironmentRow(
            '',
            $context->name,
            $kind === '' ? 'context' : $kind,
            $context->endpoint,
            $context->endpoint,
            EnvironmentOrigin::Client,
            !$shadowed && $current === $context->name,
            $shadowed,
            entry: null,
            socketPath: $socket,
        );
    }
}
