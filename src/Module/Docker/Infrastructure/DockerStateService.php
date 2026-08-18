<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Infrastructure;

use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Docker\Application\EnvironmentBook;
use LightManager\Module\Docker\Application\Port\EnvironmentBookPort;
use LightManager\Module\Docker\Application\Port\LoadedEnvironmentBook;
use LightManager\Module\Docker\Domain\Exception\InvalidDockerEnvironmentException;
use LightManager\Module\Docker\Domain\ValueObject\DockerEnvironment;
use LightManager\Module\Docker\Domain\ValueObject\EnvironmentKind;

/**
 * Stan modułu Dockera w pliku `~/.light-manager/docker.json` (krok 58).
 *
 * **Plik stanu modułu, nie plik książki** — dokładnie tak, jak `ssh.json`
 * w kroku 48 i z tego samego powodu: krok 60 dopisze do tego samego dokumentu
 * książkę rejestrów **kluczami**, a nie drugim plikiem. Klucze, których ta
 * wersja nie zna, przeżywają zapis nietknięte.
 *
 * Droga zapisu ta sama, co wszędzie: plik tymczasowy i `rename()` w tym samym
 * katalogu, prawa `0600` — wpisy mówią, z jakimi maszynami użytkownik rozmawia
 * i gdzie leżą jego klucze TLS.
 *
 * **Żadna ścieżka nie rzuca** (zasada portu). Wiersz nie do przyjęcia wypada,
 * a plik zostaje — jeden zepsuty wpis nie odbiera użytkownikowi całej książki.
 */
final class DockerStateService extends AbstractSingleton implements EnvironmentBookPort
{
    private const DIRECTORY = '.light-manager';

    private const FILE = 'docker.json';

    private const TEMPORARY_PREFIX = '.docker-';

    /** Klucz książki w dokumencie; obok stanie klucz rejestrów z kroku 60. */
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

    private const FILE_MODE = 0o600;

    private const DIRECTORY_MODE = 0o700;

    /**
     * Ostatnio wczytany dokument — po to, żeby zapis nie skasował kluczy,
     * których ta wersja nie zna.
     *
     * @var array<string, mixed>
     */
    private array $document = [];

    private bool $documentRead = false;

    public function load(): LoadedEnvironmentBook
    {
        if (!is_file($this->location())) {
            return new LoadedEnvironmentBook(new EnvironmentBook());
        }

        $document = $this->document();

        if ($document === null) {
            return new LoadedEnvironmentBook(new EnvironmentBook(), 'module.docker.env.book.unreadable');
        }

        $stored = $document[self::ENVIRONMENTS_KEY] ?? [];
        $current = $document[self::CURRENT_KEY] ?? EnvironmentBook::DEFAULT_NAME;

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
        $this->document();
        $this->document[self::ENVIRONMENTS_KEY] = self::documentOf($book);
        $this->document[self::CURRENT_KEY] = $book->current();
        $this->write();
    }

    public function location(): string
    {
        return $this->directory() . DIRECTORY_SEPARATOR . self::FILE;
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
            // dać przeczytać oczami (wzorem `ssh.json`).
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
     * Dokument z dysku, przeczytany raz; `null` znaczy „nie da się go
     * przeczytać".
     *
     * @return array<string, mixed>|null
     */
    private function document(): ?array
    {
        if ($this->documentRead) {
            return $this->document;
        }

        $this->documentRead = true;
        $path = $this->location();

        if (!is_file($path)) {
            return $this->document;
        }

        $raw = @file_get_contents($path);
        /** @var mixed $decoded */
        $decoded = $raw === false ? null : json_decode($raw, true);

        if (!is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $this->document = $decoded;
    }

    private function write(): void
    {
        $directory = $this->directory();

        if (!is_dir($directory) && !@mkdir($directory, self::DIRECTORY_MODE, true) && !is_dir($directory)) {
            return;
        }

        $content = json_encode($this->document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($content === false) {
            return;
        }

        $temporary = $directory . DIRECTORY_SEPARATOR . self::TEMPORARY_PREFIX . getmypid() . '.tmp';

        if (@file_put_contents($temporary, $content . "\n") === false) {
            return;
        }

        @chmod($temporary, self::FILE_MODE);

        if (!@rename($temporary, $this->location())) {
            @unlink($temporary);
        }
    }

    /** Katalog domowy z `HOME`, a w jego braku — katalog roboczy (jak w konfiguracji). */
    private function directory(): string
    {
        $home = getenv('HOME');

        if (!is_string($home) || $home === '') {
            $working = getcwd();
            $home = $working === false ? '.' : $working;
        }

        return rtrim($home, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::DIRECTORY;
    }
}
