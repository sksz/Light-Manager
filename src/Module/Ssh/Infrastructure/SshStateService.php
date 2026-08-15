<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Infrastructure;

use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Ssh\Application\HostBook;
use LightManager\Module\Ssh\Application\Port\HostBookPort;
use LightManager\Module\Ssh\Application\Port\LoadedHostBook;
use LightManager\Module\Ssh\Domain\Exception\InvalidHostProfileException;
use LightManager\Module\Ssh\Domain\ValueObject\AuthMethod;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;

/**
 * Stan modułu sesji zdalnej w pliku `~/.light-manager/ssh.json` (krok 48).
 *
 * **Plik stanu modułu, nie plik książki hostów** — dokładnie tak, jak
 * `audio.json` w kroku 45 (D82 nr 3): kroki 49 i 50 dopiszą **kluczami**, a nie
 * drugim plikiem, więc dokument ma od pierwszego dnia kształt, który to
 * uniesie. Klucze, których ta wersja nie zna, przeżywają zapis nietknięte.
 *
 * Droga zapisu ta sama, co w historii komend, w konfiguracji i w pliku dźwięku:
 * plik tymczasowy i `rename()` w tym samym katalogu, więc przerwany zapis
 * zostawia poprzednią, poprawną wersję zamiast obciętej. Prawa `0600`, bo wpisy
 * mówią, do jakich maszyn i jako kto użytkownik się loguje.
 *
 * **Żadna ścieżka nie rzuca** (zasada portu). Wyjątek samowalidacji profilu jest
 * tu łapany celowo: wiersz nie do przyjęcia **wypada, a plik zostaje** — ta sama
 * reguła, co przy pozycji playlisty bez ścieżki, i z tego samego powodu. Jeden
 * zepsuty wpis nie ma prawa odebrać użytkownikowi całej książki.
 */
final class SshStateService extends AbstractSingleton implements HostBookPort
{
    private const DIRECTORY = '.light-manager';

    private const FILE = 'ssh.json';

    private const TEMPORARY_PREFIX = '.ssh-';

    /** Klucz książki w dokumencie; obok niego staną klucze kroków 49 i 50. */
    private const HOSTS_KEY = 'hosts';

    private const NAME_KEY = 'name';

    private const HOST_KEY = 'host';

    private const PORT_KEY = 'port';

    private const USER_KEY = 'user';

    private const AUTH_KEY = 'auth';

    private const KEY_PATH_KEY = 'keyPath';

    private const REMOTE_DIRECTORY_KEY = 'directory';

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

    public function load(): LoadedHostBook
    {
        if (!is_file($this->location())) {
            return new LoadedHostBook(new HostBook(), null, fresh: true);
        }

        $document = $this->document();

        if ($document === null) {
            return new LoadedHostBook(new HostBook(), 'module.ssh.book.unreadable');
        }

        $stored = $document[self::HOSTS_KEY] ?? [];

        if (!is_array($stored)) {
            return new LoadedHostBook(new HostBook(), 'module.ssh.book.unreadable');
        }

        return new LoadedHostBook(new HostBook(self::profilesFrom($stored)));
    }

    public function save(HostBook $book): void
    {
        $this->document();
        $this->document[self::HOSTS_KEY] = self::documentOf($book);
        $this->write();
    }

    public function location(): string
    {
        return $this->directory() . DIRECTORY_SEPARATOR . self::FILE;
    }

    /**
     * @param array<mixed> $stored
     *
     * @return list<HostProfile>
     */
    private static function profilesFrom(array $stored): array
    {
        $profiles = [];

        foreach ($stored as $item) {
            if (!is_array($item)) {
                continue;
            }

            $profile = self::profileFrom($item);

            if ($profile !== null) {
                $profiles[] = $profile;
            }
        }

        return $profiles;
    }

    /** @param array<mixed> $item */
    private static function profileFrom(array $item): ?HostProfile
    {
        $name = $item[self::NAME_KEY] ?? null;
        $host = $item[self::HOST_KEY] ?? null;

        if (!is_string($name) || !is_string($host)) {
            return null;
        }

        $port = $item[self::PORT_KEY] ?? HostProfile::DEFAULT_PORT;
        $user = $item[self::USER_KEY] ?? '';
        $auth = $item[self::AUTH_KEY] ?? null;
        $keyPath = $item[self::KEY_PATH_KEY] ?? null;
        $directory = $item[self::REMOTE_DIRECTORY_KEY] ?? null;

        try {
            return new HostProfile(
                $name,
                $host,
                is_int($port) ? $port : HostProfile::DEFAULT_PORT,
                is_string($user) ? $user : '',
                is_string($auth) ? AuthMethod::of($auth) ?? AuthMethod::Agent : AuthMethod::Agent,
                is_string($keyPath) && $keyPath !== '' ? $keyPath : null,
                is_string($directory) && $directory !== '' ? $directory : null,
            );
        } catch (InvalidHostProfileException) {
            // Wpis nie do przyjęcia wypada; reszta książki jest w porządku i nie
            // ma powodu jej tracić. Port nie rzuca (reguła 8).
            return null;
        }
    }

    /** @return list<array<string, bool|int|string>> */
    private static function documentOf(HostBook $book): array
    {
        $stored = [];

        foreach ($book->all() as $profile) {
            $entry = [
                self::NAME_KEY => $profile->name,
                self::HOST_KEY => $profile->host,
                self::PORT_KEY => $profile->port,
                self::USER_KEY => $profile->user,
                self::AUTH_KEY => $profile->auth->value,
            ];

            // Pól pustych nie zapisujemy: dokument ma się dać przeczytać oczami,
            // a `"keyPath": null` w każdym wpisie tylko go zaśmieca.
            if ($profile->keyPath !== null) {
                $entry[self::KEY_PATH_KEY] = $profile->keyPath;
            }

            if ($profile->remoteDirectory !== null) {
                $entry[self::REMOTE_DIRECTORY_KEY] = $profile->remoteDirectory;
            }

            $stored[] = $entry;
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
