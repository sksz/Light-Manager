<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Infrastructure;

use LightManager\Application\Port\StateDocumentPort;
use LightManager\Infrastructure\Config\StateDocumentService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\AddressBook\Application\AddressBook;
use LightManager\Module\AddressBook\Application\Port\AddressBookPort;
use LightManager\Module\AddressBook\Application\Port\LoadedAddressBook;
use LightManager\Module\AddressBook\Domain\Exception\InvalidAddressEntryException;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;

/**
 * Stan książki adresowej — sekcja `address-book` dokumentu `state.json`
 * (krok 60; mechanizm zapisu z kroku 59).
 *
 * Usłudze została **treść sekcji**: co znaczą klucze i którędy wiersz staje się
 * wpisem. Mechanizmu — pliku, zapisu tymczasowego, przetrwania nieznanych kluczy
 * i cudzych sekcji — nie ma tu ani wiersza (wynik przeglądu 15e, D103).
 *
 * **Migracja z książki hostów jest jednorazowa, leniwa i nieniszcząca.** Sekcja
 * nieobecna każe przeczytać `hosts` z sekcji `ssh` i zamienić każdy wpis na
 * adres: nazwa zostaje nazwą, `user@host` — adresem, a `port`, `user` i `auth`
 * wchodzą do **rozdziału `ssh`**, bo to jego pola (D105 nr 3). Stare klucze
 * zostają na dysku nietknięte — nikt ich już nie czyta, a ich skasowanie nie ma
 * odbiorcy (to samo zdanie, co przy migracji plików modułów w kroku 59).
 *
 * **Ścieżki klucza prywatnego migracja nie przenosi** i to jest granica 11w,
 * nie przeoczenie: materiał uwierzytelnienia zostaje w sekcji modułu, który się
 * nim przedstawia, bo kwerenda książki jest czytelna dla każdego modułu.
 */
final class AddressBookStateService extends AbstractSingleton implements AddressBookPort
{
    private const SECTION = 'address-book';

    /** Sekcja, z której migruje książka hostów (krok 48). */
    private const LEGACY_SECTION = 'ssh';

    private const LEGACY_HOSTS_KEY = 'hosts';

    /** Rozdział, do którego wchodzą pola migrowanych hostów. */
    private const SSH_CHAPTER = 'ssh';

    private const ENTRIES_KEY = 'entries';

    private const ID_KEY = 'id';

    private const NAME_KEY = 'name';

    private const ADDRESS_KEY = 'address';

    private const VALUES_KEY = 'values';

    private ?StateDocumentPort $documents = null;

    /**
     * Ostatnio wczytana sekcja — po to, żeby zapis nie skasował kluczy, których
     * ta wersja nie zna.
     *
     * @var array<string, mixed>|null
     */
    private ?array $section = null;

    private bool $sectionRead = false;

    /** Podstawienie dokumentu stanu — **wyłącznie dla testów** (szew jak w `DockerStateService`). */
    public function useSeam(StateDocumentPort $documents): void
    {
        $this->documents = $documents;
        $this->section = null;
        $this->sectionRead = false;
    }

    public function load(): LoadedAddressBook
    {
        $section = $this->section();

        if ($section === null) {
            return new LoadedAddressBook(new AddressBook(), 'module.address-book.book.unreadable');
        }

        if (!array_key_exists(self::ENTRIES_KEY, $section)) {
            return new LoadedAddressBook(new AddressBook($this->migrated()));
        }

        $stored = $section[self::ENTRIES_KEY];

        if (!is_array($stored)) {
            return new LoadedAddressBook(new AddressBook(), 'module.address-book.book.unreadable');
        }

        return new LoadedAddressBook(new AddressBook(self::entriesFrom($stored)));
    }

    public function save(AddressBook $book): void
    {
        $section = $this->section() ?? [];
        $section[self::ENTRIES_KEY] = self::documentOf($book);
        $this->section = $section;
        $this->documents()->saveSection(self::SECTION, $section);
    }

    public function location(): string
    {
        return $this->documents()->location();
    }

    /**
     * Wpisy z książki hostów sprzed tego kroku.
     *
     * @return list<AddressEntry>
     */
    private function migrated(): array
    {
        $legacy = $this->documents()->section(self::LEGACY_SECTION);
        $hosts = is_array($legacy) ? ($legacy[self::LEGACY_HOSTS_KEY] ?? null) : null;

        if (!is_array($hosts)) {
            return [];
        }

        $entries = [];

        foreach ($hosts as $host) {
            if (!is_array($host)) {
                continue;
            }

            $entry = self::migratedEntry($host);

            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /** @param array<mixed> $host */
    private static function migratedEntry(array $host): ?AddressEntry
    {
        $name = $host['name'] ?? null;
        $address = $host['host'] ?? null;

        if (!is_string($name) || !is_string($address) || $address === '') {
            return null;
        }

        $values = [];

        // **Port i login — tak; sposób uwierzytelnienia i ścieżka klucza — nie.**
        // Pierwsze dwa opisują, *gdzie i jako kto*, więc wolno im leżeć w spisie
        // czytanym przez każdy moduł; dwa pozostałe opisują, *czym się
        // przedstawiamy*, i zostają w sekcji modułu Ssh (11w). Wpis migrowany
        // znajdzie je tam po nazwie — po to nietknięty klucz `hosts` zostaje na
        // dysku.
        foreach (['port' => 'port', 'user' => 'user'] as $from => $to) {
            $value = $host[$from] ?? null;

            if (is_string($value) || is_int($value) || is_bool($value)) {
                $values[self::SSH_CHAPTER][$to] = $value;
            }
        }

        try {
            return new AddressEntry(AddressEntry::newId(), $name, $address, $values);
        } catch (InvalidAddressEntryException) {
            // Wpis nie do przyjęcia **wypada, a reszta książki zostaje** — ta
            // sama reguła, co przy pozycji playlisty bez ścieżki i przy wierszu
            // książki hostów: jeden zepsuty wpis nie ma prawa odebrać
            // użytkownikowi całego spisu.
            return null;
        }
    }

    /**
     * @param array<mixed> $stored
     *
     * @return list<AddressEntry>
     */
    private static function entriesFrom(array $stored): array
    {
        $entries = [];

        foreach ($stored as $item) {
            if (!is_array($item)) {
                continue;
            }

            $entry = self::entryFrom($item);

            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /** @param array<mixed> $item */
    private static function entryFrom(array $item): ?AddressEntry
    {
        $id = $item[self::ID_KEY] ?? null;
        $name = $item[self::NAME_KEY] ?? '';
        $address = $item[self::ADDRESS_KEY] ?? '';

        if (!is_string($id) || !is_string($name) || !is_string($address)) {
            return null;
        }

        try {
            return new AddressEntry($id, $name, $address, self::valuesFrom($item[self::VALUES_KEY] ?? []));
        } catch (InvalidAddressEntryException) {
            return null;
        }
    }

    /**
     * @return array<string, array<string, string|int|bool>>
     */
    private static function valuesFrom(mixed $stored): array
    {
        if (!is_array($stored)) {
            return [];
        }

        $values = [];

        foreach ($stored as $chapter => $fields) {
            if (!is_string($chapter) || !is_array($fields)) {
                continue;
            }

            foreach ($fields as $key => $value) {
                if (is_string($key) && (is_string($value) || is_int($value) || is_bool($value))) {
                    $values[$chapter][$key] = $value;
                }
            }
        }

        return $values;
    }

    /** @return list<array<string, mixed>> */
    private static function documentOf(AddressBook $book): array
    {
        $stored = [];

        foreach ($book->all() as $entry) {
            $item = [
                self::ID_KEY => $entry->id,
                self::NAME_KEY => $entry->name,
                self::ADDRESS_KEY => $entry->address,
            ];

            if ($entry->values !== []) {
                $item[self::VALUES_KEY] = $entry->values;
            }

            $stored[] = $item;
        }

        return $stored;
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
