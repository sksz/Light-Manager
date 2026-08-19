<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Infrastructure;

use LightManager\Application\Port\StateDocumentPort;
use LightManager\Infrastructure\Config\StateDocumentService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Ssh\Application\Port\SshStatePort;

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
final class SshStateService extends AbstractSingleton implements SshStatePort
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

    /** Znacznik przeniesienia starego spisu do książki adresowej (krok 60). */
    private const MIGRATED_KEY = 'migrated';

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

    /**
     * Stary spis hostów — **czytany, nigdy niekasowany** (krok 60).
     *
     * Wiersze wychodzą stąd jako tablice napisów i liczb, a nie jako profile:
     * przenosi je do książki **komendami** ten, kto je tu zostawił, a komenda
     * bierze napisy. Usługa nie musi przez to znać ani `HostProfile`, ani
     * książki — i nie zna.
     */
    public function legacyHosts(): array
    {
        $stored = ($this->section() ?? [])[self::HOSTS_KEY] ?? null;

        if (!is_array($stored)) {
            return [];
        }

        $hosts = [];

        foreach ($stored as $item) {
            if (!is_array($item)) {
                continue;
            }

            $host = self::legacyHostFrom($item);

            if ($host !== null) {
                $hosts[] = $host;
            }
        }

        return $hosts;
    }

    public function isMigrated(): bool
    {
        return (($this->section() ?? [])[self::MIGRATED_KEY] ?? false) === true;
    }

    public function markMigrated(): void
    {
        $section = $this->section() ?? [];
        $section[self::MIGRATED_KEY] = true;
        $this->section = $section;
        $this->documents()->saveSection(self::SECTION, $section);
    }

    public function location(): string
    {
        return $this->documents()->location();
    }

    /**
     * @param array<mixed> $item
     *
     * @return array<string, string|int>|null
     */
    private static function legacyHostFrom(array $item): ?array
    {
        $name = $item[self::NAME_KEY] ?? null;
        $host = $item[self::HOST_KEY] ?? null;

        if (!is_string($name) || $name === '' || !is_string($host) || $host === '') {
            return null;
        }

        $legacy = [self::NAME_KEY => $name, self::HOST_KEY => $host];

        foreach ([self::PORT_KEY, self::USER_KEY, self::AUTH_KEY, self::KEY_PATH_KEY] as $key) {
            $value = $item[$key] ?? null;

            if (is_string($value) && $value !== '') {
                $legacy[$key] = $value;
            }

            if (is_int($value)) {
                $legacy[$key] = $value;
            }
        }

        return $legacy;
    }

    public function lastDirectory(string $entryId): ?string
    {
        // Sekcja nieczytelna (`null`) traktowana jak pusta: brak zapamiętanego
        // katalogu jest tu stanem zwykłym, a powód nieczytelności pokazuje już
        // odczyt książki i drugi raz nie ma po co go powtarzać.
        $stored = ($this->section() ?? [])[self::DIRECTORIES_KEY] ?? null;

        if (!is_array($stored)) {
            return null;
        }

        $path = $stored[$entryId] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * Zapis, który niczego nie zmienia, **nie dotyka dysku** i to nie jest
     * mikrooptymalizacja: metoda woła się przy każdym przyjęciu listy, więc bez
     * tego warunku odświeżenie katalogu klawiszem `F5` przepisywałoby plik za
     * każdym razem.
     */
    public function rememberDirectory(string $entryId, string $path): void
    {
        $section = $this->section() ?? [];
        $stored = $section[self::DIRECTORIES_KEY] ?? [];

        if (!is_array($stored)) {
            $stored = [];
        }

        if (($stored[$entryId] ?? null) === $path) {
            return;
        }

        /** @var array<string, mixed> $stored */
        $stored[$entryId] = $path;
        $section[self::DIRECTORIES_KEY] = $stored;
        $this->section = $section;
        $this->documents()->saveSection(self::SECTION, $section);
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
