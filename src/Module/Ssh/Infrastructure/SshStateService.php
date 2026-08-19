<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Infrastructure;

use LightManager\Application\Port\StateDocumentPort;
use LightManager\Infrastructure\Config\StateDocumentService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Ssh\Application\HostBook;
use LightManager\Module\Ssh\Application\Port\HostBookPort;
use LightManager\Module\Ssh\Application\Port\LoadedHostBook;
use LightManager\Module\Ssh\Domain\Exception\InvalidHostProfileException;
use LightManager\Module\Ssh\Domain\ValueObject\AuthMethod;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;

/**
 * Stan modułu sesji zdalnej — sekcja `ssh` dokumentu stanu (krok 48; od kroku
 * 59 w `~/.light-manager/state.json`, D103).
 *
 * Usłudze została **treść sekcji**: co znaczą klucze `hosts` i `directories`,
 * jak wiersz staje się profilem i którędy wraca. Mechanizm — plik, zapis
 * tymczasowy z `rename()`, przetrwanie nieznanych kluczy i sekcji, migracja ze
 * starego `ssh.json` — mieszka od kroku 59 za rdzeniowym `StateDocumentPort`
 * i nie jest tu powtórzony ani wierszem (wynik przeglądu 15e).
 *
 * **Żadna ścieżka nie rzuca** (zasada portu). Wyjątek samowalidacji profilu jest
 * tu łapany celowo: wiersz nie do przyjęcia **wypada, a sekcja zostaje** — ta
 * sama reguła, co przy pozycji playlisty bez ścieżki, i z tego samego powodu.
 * Jeden zepsuty wpis nie ma prawa odebrać użytkownikowi całej książki.
 */
final class SshStateService extends AbstractSingleton implements HostBookPort
{
    private const SECTION = 'ssh';

    /** Klucz książki w sekcji; obok niego stoi klucz kroku 49. */
    private const HOSTS_KEY = 'hosts';

    /** Ostatni oglądany katalog, po jednym na wpis książki (krok 49). */
    private const DIRECTORIES_KEY = 'directories';

    private const NAME_KEY = 'name';

    private const HOST_KEY = 'host';

    private const PORT_KEY = 'port';

    private const USER_KEY = 'user';

    private const AUTH_KEY = 'auth';

    private const KEY_PATH_KEY = 'keyPath';

    private const REMOTE_DIRECTORY_KEY = 'directory';

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

    public function load(): LoadedHostBook
    {
        $section = $this->section();

        if ($section === null) {
            return new LoadedHostBook(new HostBook(), 'module.ssh.book.unreadable');
        }

        if ($section === [] && !$this->documents()->hasSection(self::SECTION)) {
            return new LoadedHostBook(new HostBook(), null, fresh: true);
        }

        $stored = $section[self::HOSTS_KEY] ?? [];

        if (!is_array($stored)) {
            return new LoadedHostBook(new HostBook(), 'module.ssh.book.unreadable');
        }

        return new LoadedHostBook(new HostBook(self::profilesFrom($stored)));
    }

    public function save(HostBook $book): void
    {
        $section = $this->section() ?? [];
        $section[self::HOSTS_KEY] = self::documentOf($book);
        $this->section = $section;
        $this->documents()->saveSection(self::SECTION, $section);
    }

    public function location(): string
    {
        return $this->documents()->location();
    }

    public function lastDirectory(string $hostName): ?string
    {
        // Sekcja nieczytelna (`null`) traktowana jak pusta: brak zapamiętanego
        // katalogu jest tu stanem zwykłym, a powód nieczytelności pokazuje już
        // odczyt książki i drugi raz nie ma po co go powtarzać.
        $stored = ($this->section() ?? [])[self::DIRECTORIES_KEY] ?? null;

        if (!is_array($stored)) {
            return null;
        }

        $path = $stored[$hostName] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * Zapis, który niczego nie zmienia, **nie dotyka dysku** i to nie jest
     * mikrooptymalizacja: metoda woła się przy każdym przyjęciu listy, więc bez
     * tego warunku odświeżenie katalogu klawiszem `F5` przepisywałoby plik za
     * każdym razem.
     */
    public function rememberDirectory(string $hostName, string $path): void
    {
        $section = $this->section() ?? [];
        $stored = $section[self::DIRECTORIES_KEY] ?? [];

        if (!is_array($stored)) {
            $stored = [];
        }

        if (($stored[$hostName] ?? null) === $path) {
            return;
        }

        /** @var array<string, mixed> $stored */
        $stored[$hostName] = $path;
        $section[self::DIRECTORIES_KEY] = $stored;
        $this->section = $section;
        $this->documents()->saveSection(self::SECTION, $section);
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
