<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application;

use LightManager\Application\Event\EventRegistry;
use LightManager\Application\State\Book;
use LightManager\Module\AddressBook\Application\Port\AddressBookPort;
use LightManager\Module\AddressBook\Domain\Exception\InvalidAddressEntryException;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;
use LightManager\Module\AddressBook\Domain\ValueObject\FieldKind;

/**
 * Rejestr wpisów i deklaracji — model książki adresowej (krok 60).
 *
 * **Jedno miejsce na wszystkie rozdziały i ani jednej drogi na skróty.** Model
 * widzą wyłącznie komendy i kwerendy modułu; ekran książki, jej łańcuch okien
 * i wszystkie moduły dobijają się tu **przez rejestry rdzenia** — bo dostęp jest
 * jednakowy dla wszystkich (D104 nr 1), a książka nie ma być wyjątkiem od
 * własnej reguły.
 *
 * Porządek i tożsamość niesie **rdzeniowa `Book`** (krok 59, D103), kluczowana
 * **identyfikatorem wpisu**, a nie jego nazwą — nazwa jest zwykłym polem, wolno
 * ją zmienić i powtórzyć.
 *
 * Trzy liczniki, każdy z innym odbiorcą: `revision()` jest pokoleniem kwerend
 * wpisów, `chapters()->revision()` — pokoleniem kwerend deklaracji,
 * a `lastAddedId()` odpowiada kwerendzie `address-book.last`, bez której
 * deklarujący nie poznałby identyfikatora wpisu, który sam założył (D105 nr 6).
 *
 * Wpisy czyta się **leniwie, przy pierwszym pytaniu**: uruchomienie aplikacji
 * nie ma powodu dotykać dysku z powodu modułu, na który nikt jeszcze nie
 * spojrzał.
 */
final class Addresses
{
    private ?Book $entries = null;

    private readonly Chapters $chapters;

    private int $revision = 0;

    private string $lastAddedId = '';

    /** Klucz katalogu z powodem, dla którego wpisów nie widać; `null` — wszystko w porządku. */
    private ?string $problemKey = null;

    public function __construct(
        private readonly AddressBookPort $state,
        private readonly EventRegistry $events,
    ) {
        $this->chapters = new Chapters();
    }

    public function chapters(): Chapters
    {
        return $this->chapters;
    }

    public function revision(): int
    {
        return $this->revision;
    }

    public function location(): string
    {
        return $this->state->location();
    }

    public function problemKey(): ?string
    {
        $this->load();

        return $this->problemKey;
    }

    /** Identyfikator wpisu dopisanego ostatnio w tym uruchomieniu; pusty, gdy żadnego nie było. */
    public function lastAddedId(): string
    {
        return $this->lastAddedId;
    }

    /** @return list<AddressEntry> */
    public function all(): array
    {
        $this->load();
        $entries = [];

        foreach ($this->book()->all() as $payload) {
            if ($payload instanceof AddressEntry) {
                $entries[] = $payload;
            }
        }

        return $entries;
    }

    public function count(): int
    {
        $this->load();

        return $this->book()->count();
    }

    public function find(string $id): ?AddressEntry
    {
        $this->load();
        $payload = $this->book()->find($id);

        return $payload instanceof AddressEntry ? $payload : null;
    }

    /**
     * Rozdziały, o których w ogóle wiadomo: **zadeklarowane w tym uruchomieniu
     * plus te, które stoją w danych** (D104 nr 2).
     *
     * Drugi człon jest tu treścią, nie ostrożnością: rozdział, którego
     * deklarujący dziś nie ma (moduł wyłączony albo odrzucony), **nie znika
     * z książki** — jego wartości są czytelne, zmienialne i wracają razem
     * z nim. Ekran pokazuje taki rozdział bez opisu pól.
     *
     * @return list<string>
     */
    public function knownChapters(): array
    {
        $names = $this->chapters->names();

        foreach ($this->all() as $entry) {
            foreach ($entry->chapters() as $chapter) {
                if (!in_array($chapter, $names, true)) {
                    $names[] = $chapter;
                }
            }
        }

        return $names;
    }

    /** Czy rozdział ma dziś deklarację — ekran mówi o tym wprost, zamiast udawać, że pól nie ma. */
    public function isDeclared(string $chapter): bool
    {
        return $this->chapters->has($chapter);
    }

    /**
     * Pokolenie kwerend rozdziałów — **oba liczniki naraz**.
     *
     * Spis rozdziałów zmienia się dwiema drogami: nową deklaracją i wpisem,
     * który dostał wartość w rozdziale dotąd nieużywanym. Pokolenie liczące
     * tylko deklaracje przegapiłoby drugą z nich, a zakładka nowego rozdziału
     * pojawiłaby się dopiero przy najbliższej deklaracji.
     */
    public function chapterGeneration(): int
    {
        return $this->chapters->revision() + $this->revision;
    }

    /** @return list<ChapterView> migawki wszystkich rozdziałów, w kolejności zakładek */
    public function chapterViews(): array
    {
        return array_map(fn (string $chapter): ChapterView => $this->chapterView($chapter), $this->knownChapters());
    }

    /**
     * Migawka jednego rozdziału — **także takiego, którego nikt nie
     * zadeklarował** i takiego, którego nigdzie nie ma.
     *
     * Odpowiedź „rozdział bez pól" jest prawdziwa i wystarczająca; odmowa
     * byłaby przegrodą, a przegród między rozdziałami nie ma (D104 nr 1).
     */
    public function chapterView(string $chapter): ChapterView
    {
        $declared = $this->chapters->find($chapter);

        return new ChapterView(
            $chapter,
            $declared?->titleKey() ?? '',
            $declared !== null,
            $declared?->fields() ?? [],
        );
    }

    /**
     * Zapowiedź użycia rozdziału — droga komendy `address-book.chapter`.
     *
     * Ogłoszenie pada **tylko przy zmianie treści spisu**, a nie przy każdym
     * wywołaniu: deklaracje padają w takcie, czyli trzydzieści razy na sekundę,
     * a zdarzenie ogłaszane co klatkę byłoby efektem dźwiękowym granym bez
     * przerwy.
     */
    public function declareChapter(string $chapter, string $titleKey): bool
    {
        $before = $this->chapters->revision();
        $accepted = $this->chapters->declareChapter($chapter, $titleKey);
        $this->announceDeclaration($before);

        return $accepted;
    }

    /** Zapowiedź użycia pola; `false` znaczy deklarację sprzeczną z tą, która stoi. */
    public function declareField(string $chapter, ChapterField $field): bool
    {
        $before = $this->chapters->revision();
        $accepted = $this->chapters->declareField($chapter, $field);
        $this->announceDeclaration($before);

        return $accepted;
    }

    private function announceDeclaration(int $before): void
    {
        if ($this->chapters->revision() !== $before) {
            $this->events->publish(AddressBookEvent::ChapterDeclared->value);
        }
    }

    /** Nowy wpis o wskazanej nazwie; nazwa wolno pusta, bo tożsamości nie niesie. */
    public function add(string $name): AddressEntry
    {
        $this->load();
        $entry = new AddressEntry($this->freeId(), $name);
        $this->book()->put($entry->id, $entry);
        $this->lastAddedId = $entry->id;
        $this->store(AddressBookEvent::EntryAdded);

        return $entry;
    }

    public function rename(string $id, string $name): bool
    {
        return $this->replace($id, static fn (AddressEntry $entry): AddressEntry => $entry->withName($name));
    }

    public function remove(string $id): bool
    {
        $this->load();

        if (!$this->book()->remove($id)) {
            return false;
        }

        $this->store(AddressBookEvent::EntryRemoved);

        return true;
    }

    /**
     * Zapisuje wartość pola — **w dowolnym rozdziale, także niezadeklarowanym**.
     *
     * Rozdział bez deklaracji przyjmuje wartość jako napis i to jest zamierzone:
     * skoro brak deklaracji nie zabiera dostępu (D104 nr 2), to nie może też
     * zabierać zapisu. Rozdział zadeklarowany sprawdza **rodzaj** (D105 nr 3),
     * a odniesienie — istnienie wskazywanego wpisu, bo to jedyna reguła, którą
     * książka zna sama.
     *
     * @throws InvalidAddressEntryException gdy wartość nie pasuje do rodzaju
     */
    public function set(string $id, string $chapter, string $field, string $value): bool
    {
        $declared = $this->chapters->find($chapter)?->field($field);
        $accepted = $declared === null ? $value : $declared->accept($value);

        if ($declared?->kind === FieldKind::Entry && $accepted !== '' && $this->find((string) $accepted) === null) {
            throw InvalidAddressEntryException::unknownEntry($field, (string) $accepted);
        }

        return $this->replace(
            $id,
            static fn (AddressEntry $entry): AddressEntry => $entry->withValue($chapter, $field, $accepted),
        );
    }

    /** Czyści jedno pole albo — przy pustym kluczu pola — cały rozdział tego wpisu. */
    public function clear(string $id, string $chapter, string $field = ''): bool
    {
        return $this->replace($id, static fn (AddressEntry $entry): AddressEntry => $field === ''
            ? $entry->withoutChapter($chapter)
            : $entry->withoutValue($chapter, $field));
    }

    /**
     * Usuwa wartości rozdziału ze **wszystkich** wpisów; oddaje, ilu wpisów to
     * dotknęło.
     *
     * Czynność istnieje dlatego, że rozdział nie ma właściciela, a deklaracje
     * nie są zapisywane — więc wartości po module, którego już nie ma, **nie
     * mają kto posprzątać**. Musi to być czynność użytkownika, a nie automat:
     * brak deklaracji znaczy „nikt tego dziś nie używa", a nie „to jest śmieć".
     */
    public function forget(string $chapter): int
    {
        $touched = 0;

        foreach ($this->all() as $entry) {
            if (!$entry->hasChapter($chapter)) {
                continue;
            }

            $this->book()->put($entry->id, $entry->withoutChapter($chapter));
            ++$touched;
        }

        if ($touched > 0) {
            $this->store(AddressBookEvent::EntryChanged);
        }

        return $touched;
    }

    /** @param callable(AddressEntry): AddressEntry $change */
    private function replace(string $id, callable $change): bool
    {
        $entry = $this->find($id);

        if ($entry === null) {
            return false;
        }

        $this->book()->put($id, $change($entry));
        $this->store(AddressBookEvent::EntryChanged);

        return true;
    }

    /** Identyfikator, którego nikt nie zajmuje — kolizję widzi wyłącznie książka. */
    private function freeId(): string
    {
        do {
            $id = AddressEntry::newId();
        } while ($this->book()->has($id));

        return $id;
    }

    private function book(): Book
    {
        $this->load();

        return $this->entries ?? new Book();
    }

    private function load(): void
    {
        if ($this->entries !== null) {
            return;
        }

        $this->entries = new Book();
        $loaded = $this->state->load();
        $this->problemKey = $loaded->problemKey;

        foreach ($loaded->entries as $entry) {
            $this->entries->put($entry->id, $entry);
        }
    }

    /**
     * Zapisuje książkę, podbija pokolenie i ogłasza, **w tej kolejności**.
     *
     * Ogłoszenie na końcu, bo odbiorca ma prawo od razu zapytać kwerendą — a ta
     * odpowiada z pokolenia, które musi już być podbite. Zdarzenie niesie samą
     * tożsamość (D40 P5): co się zmieniło, mówi kwerenda.
     */
    private function store(AddressBookEvent $event): void
    {
        ++$this->revision;
        $this->state->save($this->all());
        $this->events->publish($event->value);
    }
}
