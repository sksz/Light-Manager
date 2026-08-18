<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Infrastructure;

use LightManager\Application\Port\StateDocumentPort;
use LightManager\Infrastructure\Config\StateDocumentService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Docker\Application\EnvironmentBook;
use LightManager\Module\Docker\Application\Port\EnvironmentBookPort;
use LightManager\Module\Docker\Application\Port\LoadedEnvironmentBook;
use LightManager\Module\Docker\Domain\Exception\InvalidDockerEnvironmentException;
use LightManager\Module\Docker\Domain\ValueObject\DockerEnvironment;
use LightManager\Module\Docker\Domain\ValueObject\EnvironmentKind;

/**
 * Stan modułu Dockera — sekcja `docker` dokumentu stanu (krok 58; od kroku 59
 * w `~/.light-manager/state.json`, D103).
 *
 * Usłudze została **treść sekcji**: klucze `environments` i `currentEnvironment`
 * oraz zamiana wiersza na wpis środowiska i z powrotem. Mechanizm — plik, zapis
 * tymczasowy z `rename()`, przetrwanie nieznanych kluczy, migracja ze starego
 * `docker.json` — mieszka od kroku 59 za rdzeniowym `StateDocumentPort` (wynik
 * przeglądu 15e). Krok 60 dopisze książkę rejestrów **kluczami tej samej
 * sekcji**, nie drugą sekcją.
 *
 * **Żadna ścieżka nie rzuca** (zasada portu). Wiersz nie do przyjęcia wypada,
 * a sekcja zostaje — jeden zepsuty wpis nie odbiera użytkownikowi całej książki.
 */
final class DockerStateService extends AbstractSingleton implements EnvironmentBookPort
{
    private const SECTION = 'docker';

    /** Klucz książki w sekcji; obok stanie klucz rejestrów z kroku 61. */
    private const ENVIRONMENTS_KEY = 'environments';

    /** Nazwa środowiska bieżącego — wybór przeżywa uruchomienie. */
    private const CURRENT_KEY = 'currentEnvironment';

    private const NAME_KEY = 'name';

    private const KIND_KEY = 'kind';

    private const SOCKET_KEY = 'socket';

    private const TARGET_KEY = 'target';

    private const PORT_KEY = 'port';

    private const CERT_KEY = 'cert';

    private const KEY_KEY = 'key';

    private const CA_KEY = 'ca';

    private ?StateDocumentPort $documents = null;

    /**
     * Ostatnio wczytana sekcja — po to, żeby zapis nie skasował kluczy,
     * których ta wersja nie zna.
     *
     * @var array<string, mixed>|null
     */
    private ?array $section = null;

    private bool $sectionRead = false;

    /** Podstawienie dokumentu stanu — **wyłącznie dla testów** (szew jak w `KubectlService`). */
    public function useSeam(StateDocumentPort $documents): void
    {
        $this->documents = $documents;
        $this->section = null;
        $this->sectionRead = false;
    }

    public function load(): LoadedEnvironmentBook
    {
        $section = $this->section();

        if ($section === null) {
            return new LoadedEnvironmentBook(new EnvironmentBook(), 'module.docker.env.book.unreadable');
        }

        $stored = $section[self::ENVIRONMENTS_KEY] ?? [];
        $current = $section[self::CURRENT_KEY] ?? EnvironmentBook::DEFAULT_NAME;

        if (!is_array($stored)) {
            return new LoadedEnvironmentBook(new EnvironmentBook(), 'module.docker.env.book.unreadable');
        }

        return new LoadedEnvironmentBook(new EnvironmentBook(
            self::environmentsFrom($stored),
            is_string($current) ? $current : EnvironmentBook::DEFAULT_NAME,
        ));
    }

    public function save(EnvironmentBook $book): void
    {
        $section = $this->section() ?? [];
        $section[self::ENVIRONMENTS_KEY] = self::documentOf($book);
        $section[self::CURRENT_KEY] = $book->current();
        $this->section = $section;
        $this->documents()->saveSection(self::SECTION, $section);
    }

    public function location(): string
    {
        return $this->documents()->location();
    }

    /**
     * @param array<mixed> $stored
     *
     * @return list<DockerEnvironment>
     */
    private static function environmentsFrom(array $stored): array
    {
        $environments = [];

        foreach ($stored as $item) {
            if (!is_array($item)) {
                continue;
            }

            $environment = self::environmentFrom($item);

            if ($environment !== null) {
                $environments[] = $environment;
            }
        }

        return $environments;
    }

    /** @param array<mixed> $item */
    private static function environmentFrom(array $item): ?DockerEnvironment
    {
        $name = $item[self::NAME_KEY] ?? null;
        $kind = $item[self::KIND_KEY] ?? null;

        if (!is_string($name) || !is_string($kind)) {
            return null;
        }

        $socket = $item[self::SOCKET_KEY] ?? DockerEnvironment::DEFAULT_SOCKET;
        $target = $item[self::TARGET_KEY] ?? '';
        $port = $item[self::PORT_KEY] ?? 0;

        try {
            return match (EnvironmentKind::of($kind)) {
                EnvironmentKind::LocalSocket => DockerEnvironment::localSocket(
                    $name,
                    is_string($socket) ? $socket : DockerEnvironment::DEFAULT_SOCKET,
                ),
                EnvironmentKind::SshTunnel => DockerEnvironment::sshTunnel(
                    $name,
                    is_string($target) ? $target : '',
                    is_int($port) && $port > 0 ? $port : DockerEnvironment::DEFAULT_TUNNEL_PORT,
                    is_string($socket) ? $socket : DockerEnvironment::DEFAULT_SOCKET,
                ),
                EnvironmentKind::Tcp => DockerEnvironment::tcp(
                    $name,
                    is_string($target) ? $target : '',
                    is_int($port) && $port > 0 ? $port : DockerEnvironment::DEFAULT_TLS_PORT,
                    self::pathFrom($item, self::CERT_KEY),
                    self::pathFrom($item, self::KEY_KEY),
                    self::pathFrom($item, self::CA_KEY),
                ),
                null => null,
            };
        } catch (InvalidDockerEnvironmentException) {
            // Wpis nie do przyjęcia wypada; reszta książki jest w porządku i nie
            // ma powodu jej tracić. Port nie rzuca (reguła 8).
            return null;
        }
    }

    /** @param array<mixed> $item */
    private static function pathFrom(array $item, string $key): string
    {
        $value = $item[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /** @return list<array<string, int|string>> */
    private static function documentOf(EnvironmentBook $book): array
    {
        $stored = [];

        foreach ($book->all() as $entry) {
            $item = [
                self::NAME_KEY => $entry->name,
                self::KIND_KEY => $entry->kind->value,
            ];

            // Pól bez znaczenia dla rodzaju nie zapisujemy — dokument ma się
            // dać przeczytać oczami (wzorem sekcji `ssh`).
            if ($entry->kind !== EnvironmentKind::Tcp) {
                $item[self::SOCKET_KEY] = $entry->socketPath;
            }

            if ($entry->kind !== EnvironmentKind::LocalSocket) {
                $item[self::TARGET_KEY] = $entry->target;
                $item[self::PORT_KEY] = $entry->port;
            }

            if ($entry->kind === EnvironmentKind::Tcp) {
                $item[self::CERT_KEY] = $entry->certPath ?? '';
                $item[self::KEY_KEY] = $entry->keyPath ?? '';
                $item[self::CA_KEY] = $entry->caPath ?? '';
            }

            $stored[] = $item;
        }

        return $stored;
    }

    /**
     * Sekcja z dokumentu stanu, przeczytana raz; `null` znaczy „nie da się jej
     * przeczytać".
     *
     * @return array<string, mixed>|null
     */
    private function section(): ?array
    {
        if (!$this->sectionRead) {
            $this->sectionRead = true;
            $this->section = $this->documents()->section(self::SECTION);
        }

        return $this->section;
    }

    private function documents(): StateDocumentPort
    {
        return $this->documents ?? StateDocumentService::getInstance();
    }
}
