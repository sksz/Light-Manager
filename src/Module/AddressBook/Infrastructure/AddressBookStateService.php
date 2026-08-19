<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Infrastructure;

use LightManager\Application\Port\StateDocumentPort;
use LightManager\Infrastructure\Config\StateDocumentService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\Port\AddressBookPort;
use LightManager\Module\AddressBook\Application\Port\LoadedAddressBook;
use LightManager\Module\AddressBook\Domain\Exception\InvalidAddressEntryException;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;

/**
 * Wpisy książki — sekcja `address-book` dokumentu stanu (krok 60).
 *
 * Usłudze została **treść sekcji**: klucz `entries` oraz zamiana wiersza na
 * wpis i z powrotem. Mechanizm — plik, zapis tymczasowy z `rename()`,
 * przetrwanie nieznanych kluczy i cudzych sekcji — mieszka za rdzeniowym
 * `StateDocumentPort` (krok 59, D103).
 *
 * **Wpis leży tu w całości**, z wartościami wszystkich rozdziałów i z polami
 * maskowanymi (D104 nr 6). Rozdziałów usługa **nie zna i nie zapisuje**:
 * deklaracje powstają przy każdym uruchomieniu, więc na dysku zostają wyłącznie
 * wartości — a wartość rozdziału, którego dziś nikt nie deklaruje, przeżywa
 * nieobecność deklarującego i wraca razem z nim.
 *
 * **Cudzych sekcji ta usługa nie czyta** — nie wolno jej (granica
 * `StateDocumentPort`) i nie ma po co: migrację trzech starych książek robią
 * ich właściciele, komendami książki.
 *
 * **Żadna ścieżka nie rzuca** (zasada portu). Wpis nie do przyjęcia wypada,
 * a reszta zostaje.
 */
final class AddressBookStateService extends AbstractSingleton implements AddressBookPort
{
    private const SECTION = AddressBookSettings::ID;

    private const ENTRIES_KEY = 'entries';

    private const ID_KEY = 'id';

    private const NAME_KEY = 'name';

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

    /** Podstawienie dokumentu stanu — **wyłącznie dla testów** (szew jak w `KubernetesStateService`). */
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
            return new LoadedAddressBook([], AddressBookSettings::key('book.unreadable'));
        }

        $stored = $section[self::ENTRIES_KEY] ?? [];

        if (!is_array($stored)) {
            return new LoadedAddressBook([], AddressBookSettings::key('book.unreadable'));
        }

        return new LoadedAddressBook(self::entriesFrom($stored));
    }

    public function save(array $entries): void
    {
        $section = $this->section() ?? [];
        $section[self::ENTRIES_KEY] = self::documentOf($entries);
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

        if (!is_string($id) || !is_string($name)) {
            return null;
        }

        try {
            return new AddressEntry($id, $name, self::valuesFrom($item[self::VALUES_KEY] ?? []));
        } catch (InvalidAddressEntryException) {
            // Wpis nie do przyjęcia wypada; reszta książki jest w porządku i nie
            // ma powodu jej tracić. Port nie rzuca (reguła 8).
            return null;
        }
    }

    /**
     * Wartości rozdziałów; **wiersz nie do rozczytania wypada w ciszy**, wpis
     * zostaje.
     *
     * Tolerancja idzie tu dalej niż przy wpisie, bo rozdział bywa cudzy
     * i starszy: pole o wartości, której ta wersja nie zna (tablica, obiekt),
     * nie ma prawa zabrać wpisu jego właścicielowi.
     *
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

            foreach ($fields as $field => $value) {
                if (is_string($field) && (is_string($value) || is_int($value) || is_bool($value))) {
                    $values[$chapter][$field] = $value;
                }
            }
        }

        return $values;
    }

    /**
     * @param list<AddressEntry> $entries
     *
     * @return list<array<string, mixed>>
     */
    private static function documentOf(array $entries): array
    {
        $stored = [];

        foreach ($entries as $entry) {
            $item = [self::ID_KEY => $entry->id, self::NAME_KEY => $entry->name];

            // Pustych rozdziałów nie zapisujemy: sekcja ma się dać przeczytać
            // oczami, a `"values": {}` w każdym wpisie tylko ją zaśmieca.
            if ($entry->values !== []) {
                $item[self::VALUES_KEY] = $entry->values;
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
