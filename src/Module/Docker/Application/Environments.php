<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Module\Docker\Application\Port\ContextCatalogPort;
use LightManager\Module\Docker\Application\Port\EnvironmentBookPort;
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
    private ?EnvironmentBook $book = null;

    private ?string $bookProblem = null;

    /** Ile razy zmieniła się odpowiedź — pokolenie kwerendy `docker.environments`. */
    private int $revision = 0;

    /** Znacznik przełączenia — zabiera go takt modułu, żeby unieważnić listy. */
    private bool $switched = false;

    /** Cel tunelu rozstrzygnięty przy wyborze — wiersz `address-book.entry` albo wpis wprost. */
    private ?string $resolvedTarget = null;

    private int $resolvedPort = DockerEnvironment::DEFAULT_TUNNEL_PORT;

    /** Odcisk stanu z poprzedniego taktu — po nim poznaje się, że coś się zmieniło. */
    private string $fingerprint = '';

    public function __construct(
        private readonly EnvironmentBookPort $bookPort,
        private readonly ContextCatalogPort $contexts,
        private readonly TunnelPort $tunnel,
    ) {
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

    public function currentName(): string
    {
        return $this->loaded()->current();
    }

    public function tunnel(): TunnelState
    {
        return $this->tunnel->state();
    }

    public function find(string $name): ?DockerEnvironment
    {
        return $this->loaded()->find($name);
    }

    /** Wiersz spisu o podanej nazwie — z obu źródeł, wedle reguły pierwszeństwa. */
    public function row(string $name): ?EnvironmentRow
    {
        foreach ($this->rows() as $row) {
            if ($row->name === $name && !$row->shadowed) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Dopisuje albo zastępuje wpis własny i zapisuje książkę.
     *
     * Zmiana wpisu bieżącego **nie przełącza niczego w locie**: adres demona
     * zmienia się dopiero przy następnym wyborze, bo cicha podmiana rozmówcy
     * pod trwającą listą byłaby dokładnie tym zaskoczeniem, przed którym ten
     * krok ma chronić.
     */
    public function add(DockerEnvironment $entry): void
    {
        $book = $this->loaded();
        $book->add($entry);
        $this->bookPort->save($book);
        ++$this->revision;
    }

    /** Usuwa wpis własny; wpisu czytanego od klienta nie ma jak usunąć — nie jest w książce. */
    public function remove(string $name): bool
    {
        $book = $this->loaded();
        $removed = $book->remove($name);

        if ($removed) {
            $this->bookPort->save($book);
            ++$this->revision;
        }

        return $removed;
    }

    /**
     * Wybiera środowisko bieżące. Oddaje klucz powodu, gdy wybrać się nie da.
     *
     * @param ?string $resolvedTarget cel tunelu rozstrzygnięty przez wołającego
     *                                (kwerendą `address-book.entry` albo z wpisu wprost) —
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

        $book = $this->loaded();
        $book->makeCurrent($name);
        $this->bookPort->save($book);
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

        foreach ($this->loaded()->all() as $entry) {
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
                EnvironmentBook::DEFAULT_NAME,
                EnvironmentKind::LocalSocket->value,
                DockerEnvironment::DEFAULT_SOCKET,
                DockerEnvironment::DEFAULT_SOCKET,
                EnvironmentOrigin::Default,
                $current === EnvironmentBook::DEFAULT_NAME && !isset($ownNames[EnvironmentBook::DEFAULT_NAME]),
                shadowed: isset($ownNames[EnvironmentBook::DEFAULT_NAME]),
                entry: null,
                socketPath: DockerEnvironment::DEFAULT_SOCKET,
            );
        }

        return $rows;
    }

    public function view(): EnvironmentBookView
    {
        $this->loaded();

        return new EnvironmentBookView(
            $this->rows(),
            $this->currentName(),
            $this->bookPort->location(),
            $this->tunnel->state(),
            $this->contexts->isReading(),
            $this->bookProblem ?? $this->contexts->problemKey(),
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
            $entry->name,
            $entry->kind->value,
            $entry->label(),
            $publicAddress,
            EnvironmentOrigin::Own,
            $current === $entry->name,
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

    private function loaded(): EnvironmentBook
    {
        if ($this->book === null) {
            $loaded = $this->bookPort->load();
            $this->book = $loaded->book;
            $this->bookProblem = $loaded->problemKey;
        }

        return $this->book;
    }
}
