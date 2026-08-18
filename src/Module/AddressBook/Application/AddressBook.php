<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application;

use LightManager\Application\State\Book;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;

/**
 * Spis adresów, który prowadzi użytkownik (krok 60).
 *
 * Porządek i tożsamość niesie **rdzeniowa `Book`** z kroku 59 — kolejność
 * dopisywania i zastąpienie w miejscu. Modułowi zostaje to, czego rdzeń nie zna:
 * że ładunkiem jest `AddressEntry`, a **kluczem jest jego identyfikator**, nie
 * nazwa (D105 nr 2). To jedna z dwóch rzeczy, którymi ta książka różni się od
 * trzech poprzednich; druga jest taka, że nikt nie jest jej jedynym czytelnikiem.
 *
 * Kolekcja **mutowalna w miejscu**, jak jej poprzedniczki i z tego samego
 * powodu: opisuje spis, który użytkownik prowadzi, a nie wartość przekazywaną
 * między warstwami.
 */
final class AddressBook
{
    /** Ile razy losować identyfikator, zanim uzna się, że coś jest nie tak z losowością. */
    private const ID_ATTEMPTS = 8;

    private readonly Book $book;

    /** @param list<AddressEntry> $entries */
    public function __construct(array $entries = [])
    {
        $this->book = new Book();

        foreach ($entries as $entry) {
            $this->put($entry);
        }
    }

    /** @return list<AddressEntry> */
    public function all(): array
    {
        $entries = [];

        foreach ($this->book->all() as $payload) {
            if ($payload instanceof AddressEntry) {
                $entries[] = $payload;
            }
        }

        return $entries;
    }

    /**
     * Wpisy w kolejności do pokazania — dopisywania albo alfabetycznie
     * (pozycja ustawień, D105 nr 6).
     *
     * Porządek alfabetyczny idzie po tym, **co widać** (`label()`), więc wpis
     * bez nazwy sortuje się swoim identyfikatorem — inaczej wpisy bez nazwy
     * zbiłyby się w jedno miejsce, którego użytkownik nie umiałby wytłumaczyć.
     *
     * @return list<AddressEntry>
     */
    public function sorted(bool $alphabetically): array
    {
        $entries = $this->all();

        if ($alphabetically) {
            usort(
                $entries,
                static fn (AddressEntry $a, AddressEntry $b): int => strcasecmp($a->label(), $b->label()),
            );
        }

        return $entries;
    }

    public function count(): int
    {
        return $this->book->count();
    }

    public function isEmpty(): bool
    {
        return $this->book->count() === 0;
    }

    public function find(string $id): ?AddressEntry
    {
        $payload = $this->book->find($id);

        return $payload instanceof AddressEntry ? $payload : null;
    }

    /**
     * Wpis o tej nazwie — **jedyny** albo `null`.
     *
     * Nazwa nie jest tożsamością i wolno ją powtórzyć, więc odpowiedź
     * niejednoznaczna jest tu odpowiedzią „nie wiem", a nie pierwszym
     * trafieniem. Droga istnieje dla zgodności wstecz: wpisy zapisane u obcych
     * przed tym krokiem trzymają nazwę, a nie identyfikator.
     */
    public function findByName(string $name): ?AddressEntry
    {
        if ($name === '') {
            return null;
        }

        $found = null;

        foreach ($this->all() as $entry) {
            if ($entry->name !== $name) {
                continue;
            }

            if ($found !== null) {
                return null;
            }

            $found = $entry;
        }

        return $found;
    }

    /** Dopisuje albo **zastępuje** wpis o tym samym identyfikatorze, zachowując jego miejsce. */
    public function put(AddressEntry $entry): void
    {
        $this->book->put($entry->id, $entry);
    }

    public function remove(string $id): bool
    {
        return $this->book->remove($id);
    }

    /** @return list<string> identyfikatory — materiał na podpowiedzi argumentów komend */
    public function ids(): array
    {
        return $this->book->names();
    }

    /**
     * Identyfikator dla nowego wpisu — losowy i wolny.
     *
     * Sprawdzenie kolizji stoi tutaj, bo książka jest jedynym miejscem widzącym
     * wszystkie zajęte identyfikatory. Po `ID_ATTEMPTS` próbach oddaje ostatnie
     * losowanie: przy ośmiu znakach szesnastkowych i książce, którą prowadzi
     * człowiek, to zdarzenie nie ma prawa zajść, a pętla bez końca miałaby
     * gorsze skutki niż jego przyjęcie.
     */
    public function nextId(): string
    {
        $id = AddressEntry::newId();

        for ($attempt = 0; $attempt < self::ID_ATTEMPTS && $this->book->has($id); ++$attempt) {
            $id = AddressEntry::newId();
        }

        return $id;
    }
}
