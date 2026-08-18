<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application;

use LightManager\Application\Event\EventRegistry;
use LightManager\Application\Port\SettingsPort;
use LightManager\Module\AddressBook\Application\Port\AddressBookPort;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;

/**
 * Książka, rozdziały i zapis widziane jako jedna rzecz — **jedna na moduł**
 * (krok 60).
 *
 * Wzorem `SshSession` z kroku 48 i `Environments` z kroku 58, i z tego samego
 * powodu: ekran, trzy komendy i dwie kwerendy muszą widzieć **ten sam** spis,
 * a trzy obiekty znaczyłyby trzy prawdy. Tu dochodzi powód drugi, którego tamte
 * nie miały: spis jest **cudzy dla wszystkich** — pyta o niego moduł sesji
 * zdalnej i moduł Dockera, więc rozjazd nie kończyłby się na jednym ekranie.
 *
 * Książkę czyta **leniwie**: uruchomienie aplikacji, w którym nikt o adres nie
 * zapytał, nie kosztuje ani jednego odczytu z dysku.
 *
 * **Rozdziały żyją tylko w pamięci** (opis w `AddressChapter`), a ich pola
 * dostaje z zewnątrz — czytanie cudzych kwerend należy do warstwy, która ma
 * rejestr kwerend, czyli do `Presentation` (wzorem fasad z kroku 53).
 */
final class Addresses
{
    private ?AddressBook $book = null;

    private ?string $problem = null;

    /** @var array<string, AddressChapter> rozdziały pod identyfikatorem właściciela */
    private array $chapters = [];

    /**
     * Ile razy książka się zmieniła — pokolenie obu kwerend.
     *
     * Licznik jest **prawdziwy**, nie `VOLATILE`: książka zmienia się w czterech
     * miejscach i wszystkie są tutaj, więc źródło umie powiedzieć, że się
     * zmieniło (warunek D93 nr 1). Zysk jest realny — spis rysuje się co klatkę,
     * a wiersze budują raz na zmianę.
     */
    private int $revision = 0;

    public function __construct(
        private readonly AddressBookPort $storage,
        private readonly SettingsPort $settings,
        private readonly EventRegistry $events,
    ) {
    }

    public function book(): AddressBook
    {
        if ($this->book === null) {
            $loaded = $this->storage->load();
            $this->book = $loaded->book;
            $this->problem = $loaded->problemKey;
            ++$this->revision;
        }

        return $this->book;
    }

    /** @return list<AddressEntry> wpisy w kolejności wybranej w ustawieniach */
    public function entries(): array
    {
        return $this->book()->sorted(AddressBookSettings::alphabeticalFrom($this->settings->current()));
    }

    public function find(string $id): ?AddressEntry
    {
        return $this->book()->find($id);
    }

    /**
     * Wpis wskazany identyfikatorem albo — dla zgodności wstecz — **jednoznaczną**
     * nazwą.
     *
     * Druga droga istnieje dla wpisów, które obcy zapisali u siebie przed tym
     * krokiem, gdy tożsamością była nazwa (książka hostów, krok 48). Nazwa
     * powtórzona daje `null`, bo zgadywanie, o który z dwóch wpisów chodzi,
     * byłoby gorsze od odpowiedzi „nie wiem".
     */
    public function resolve(string $reference): ?AddressEntry
    {
        return $this->book()->find($reference) ?? $this->book()->findByName($reference);
    }

    /** Klucz powodu, gdy dokumentu nie dało się przeczytać; `null`, gdy dobrze. */
    public function problem(): ?string
    {
        $this->book();

        return $this->problem;
    }

    public function location(): string
    {
        return $this->storage->location();
    }

    public function revision(): int
    {
        return $this->revision;
    }

    /** Nowy wpis — identyfikator nadaje książka, bo tylko ona widzi zajęte. */
    public function add(string $name, string $address): AddressEntry
    {
        $entry = new AddressEntry($this->book()->nextId(), $name, $address);
        $this->put($entry);

        return $entry;
    }

    /**
     * Dopisuje albo zmienia wpis — **łącznie z nazwą**, bo tożsamością jest
     * identyfikator (D105 nr 4).
     *
     * Które zdarzenie ogłosić, rozstrzyga **książka, a nie wołający**: pytanie
     * „czy ten identyfikator już tu stoi" ma jedną odpowiedź i stoi w jednym
     * miejscu, a wołający z własnym znacznikiem „to nowy wpis" prędzej czy
     * później pomyliłby się przy pierwszej poprawce (11n).
     */
    public function put(AddressEntry $entry): void
    {
        $known = $this->book()->find($entry->id) !== null;
        $this->book()->put($entry);
        $this->persist($known ? AddressBookEvent::EntryChanged : AddressBookEvent::EntryAdded);
    }

    public function remove(string $id): bool
    {
        if (!$this->book()->remove($id)) {
            return false;
        }

        $this->persist(AddressBookEvent::EntryRemoved);

        return true;
    }

    /**
     * Zakłada rozdział — czynność komendy `address-book.chapter` (D105 nr 3).
     *
     * Zakładanie jest **idempotentne**: moduł, który poprosi drugi raz, dostaje
     * ten sam rozdział bez pól, a pola i tak przyjdą z jego kwerendy. Wołający
     * nie musi pamiętać, czy już prosił — a przy leniwym składaniu modułów nie
     * miałby jak.
     */
    public function declareChapter(string $owner, string $query, string $labelKey): void
    {
        if ($owner === '' || $query === '') {
            return;
        }

        $this->chapters[$owner] = new AddressChapter($owner, $query, $labelKey);
    }

    /**
     * Wstawia pola rozdziału odczytane z cudzej kwerendy.
     *
     * @param list<ChapterField> $fields
     */
    public function useChapterFields(string $owner, array $fields): void
    {
        $chapter = $this->chapters[$owner] ?? null;

        if ($chapter !== null) {
            $this->chapters[$owner] = $chapter->withFields($fields);
        }
    }

    /** @return list<AddressChapter> rozdziały w kolejności zakładania */
    public function chapters(): array
    {
        return array_values($this->chapters);
    }

    public function chapter(string $owner): ?AddressChapter
    {
        return $this->chapters[$owner] ?? null;
    }

    private function persist(AddressBookEvent $event): void
    {
        ++$this->revision;
        $this->storage->save($this->book());
        $this->events->publish($event->value);
    }
}
