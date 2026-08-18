<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Infrastructure;

use LightManager\Application\Port\StateDocumentPort;
use LightManager\Infrastructure\Config\StateDocumentService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Ssh\Application\Port\SshStatePort;
use LightManager\Module\Ssh\Domain\ValueObject\AuthMethod;
use LightManager\Module\Ssh\Domain\ValueObject\HostCredentials;

/**
 * Stan modułu sesji zdalnej — sekcja `ssh` dokumentu stanu (krok 48; od kroku
 * 59 w `~/.light-manager/state.json`; kształt z kroku 60).
 *
 * Usłudze została **treść sekcji**: co znaczą klucze i którędy wiersz staje się
 * poświadczeniem. Mechanizm — plik, zapis tymczasowy z `rename()`, przetrwanie
 * nieznanych kluczy i sekcji — mieszka od kroku 59 za rdzeniowym
 * `StateDocumentPort` i nie jest tu powtórzony ani wierszem (wynik przeglądu 15e).
 *
 * **Klucz `hosts` przestał być czytany w kroku 60 i nie jest już zapisywany** —
 * adresy przeniosły się do książki adresowej. Zostaje jednak na dysku
 * **nietknięty** i wciąż ma jedno zadanie: jest **drogą awaryjną odczytu**
 * poświadczeń wpisów sprzed migracji, których nie da się odnaleźć po
 * identyfikatorze, bo ten powstał losowo dopiero przy przenosinach. To ta sama
 * zasada, co przy migracji plików modułów w kroku 59: stare zostaje, nowe rośnie
 * obok.
 */
final class SshStateService extends AbstractSingleton implements SshStatePort
{
    private const SECTION = 'ssh';

    /** Poświadczenia po identyfikatorze wpisu książki (krok 60). */
    private const CREDENTIALS_KEY = 'credentials';

    /** Ostatni oglądany katalog, po jednym na wpis (krok 49; od kroku 60 po `id`). */
    private const DIRECTORIES_KEY = 'directories';

    /** Książka hostów sprzed kroku 60 — **czytana wyłącznie awaryjnie**, nigdy zapisywana. */
    private const LEGACY_HOSTS_KEY = 'hosts';

    private const NAME_KEY = 'name';

    private const AUTH_KEY = 'auth';

    private const KEY_PATH_KEY = 'keyPath';

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

    public function credentials(string $entryId, string $entryName = ''): HostCredentials
    {
        $credentials = ($this->section() ?? [])[self::CREDENTIALS_KEY] ?? null;
        $stored = is_array($credentials) ? ($credentials[$entryId] ?? null) : null;

        if (is_array($stored)) {
            return self::credentialsFrom($stored);
        }

        return $this->legacyCredentials($entryName);
    }

    public function saveCredentials(string $entryId, HostCredentials $credentials): void
    {
        if ($entryId === '') {
            return;
        }

        $section = $this->section() ?? [];
        $entry = [self::AUTH_KEY => $credentials->auth->value];

        if ($credentials->keyPath !== null) {
            $entry[self::KEY_PATH_KEY] = $credentials->keyPath;
        }

        $stored = $section[self::CREDENTIALS_KEY] ?? [];
        $section[self::CREDENTIALS_KEY] = is_array($stored) ? $stored : [];
        $section[self::CREDENTIALS_KEY][$entryId] = $entry;
        $this->section = $section;
        $this->documents()->saveSection(self::SECTION, $section);
    }

    public function lastDirectory(string $entryId, string $entryName = ''): ?string
    {
        // Sekcja nieczytelna (`null`) traktowana jak pusta: brak zapamiętanego
        // katalogu jest tu stanem zwykłym, a powód nieczytelności pokazuje już
        // odczyt książki i drugi raz nie ma po co go powtarzać.
        $stored = ($this->section() ?? [])[self::DIRECTORIES_KEY] ?? null;

        if (!is_array($stored)) {
            return null;
        }

        foreach ([$entryId, $entryName] as $key) {
            $directory = $key === '' ? null : ($stored[$key] ?? null);

            if (is_string($directory) && $directory !== '') {
                return $directory;
            }
        }

        return null;
    }

    public function rememberDirectory(string $entryId, string $path): void
    {
        if ($entryId === '' || $path === '') {
            return;
        }

        $section = $this->section() ?? [];
        $stored = $section[self::DIRECTORIES_KEY] ?? [];
        $section[self::DIRECTORIES_KEY] = is_array($stored) ? $stored : [];
        $section[self::DIRECTORIES_KEY][$entryId] = $path;
        $this->section = $section;
        $this->documents()->saveSection(self::SECTION, $section);
    }

    public function location(): string
    {
        return $this->documents()->location();
    }

    /**
     * Poświadczenia wpisu sprzed kroku 60 — po nazwie, z nietkniętego klucza
     * `hosts`.
     */
    private function legacyCredentials(string $entryName): HostCredentials
    {
        $hosts = ($this->section() ?? [])[self::LEGACY_HOSTS_KEY] ?? null;

        if ($entryName === '' || !is_array($hosts)) {
            return new HostCredentials();
        }

        foreach ($hosts as $host) {
            if (is_array($host) && ($host[self::NAME_KEY] ?? null) === $entryName) {
                return self::credentialsFrom($host);
            }
        }

        return new HostCredentials();
    }

    /** @param array<mixed> $stored */
    private static function credentialsFrom(array $stored): HostCredentials
    {
        $auth = $stored[self::AUTH_KEY] ?? null;
        $keyPath = $stored[self::KEY_PATH_KEY] ?? null;

        return new HostCredentials(
            (is_string($auth) ? AuthMethod::of($auth) : null) ?? AuthMethod::Agent,
            is_string($keyPath) && $keyPath !== '' ? $keyPath : null,
        );
    }

    /** @return array<string, mixed>|null */
    private function section(): ?array
    {
        if (!$this->sectionRead) {
            $this->section = $this->documents()->section(self::SECTION);
            $this->sectionRead = true;
        }

        return $this->section;
    }

    private function documents(): StateDocumentPort
    {
        return $this->documents ??= StateDocumentService::getInstance();
    }
}
