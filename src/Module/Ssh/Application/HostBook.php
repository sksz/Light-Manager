<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application;

use LightManager\Application\State\Book;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;

/**
 * Spis hostów, które użytkownik prowadzi z ekranu modułu (krok 48).
 *
 * Kolekcja **mutowalna w miejscu**, wzorem `Playlist` z kroku 45 i z tego samego
 * powodu: jest własnością modułu, a nie wartością przekazywaną między warstwami,
 * więc kopiowanie jej przy każdym dopisaniu byłoby ceremonią bez odbiorcy.
 *
 * **Tożsamością wpisu jest nazwa własna**, więc spis zachowuje się jak mapa,
 * a nie jak lista: dopisanie pod nazwą już zajętą **zastępuje** wpis, zamiast
 * dokładać drugi. Inaczej `ssh.connect biuro` musiałoby rozstrzygać, o który
 * z dwóch „biur" chodzi — a nie ma jak.
 *
 * Porządek i tożsamość niesie od kroku 59 **rdzeniowa `Book`** (wynik przeglądu
 * 15e, D103): to ona gwarantuje kolejność dopisywania i zastąpienie w miejscu.
 * Modułowi zostało to, czego rdzeń nie zna — że ładunkiem wpisu jest
 * `HostProfile`.
 */
final class HostBook
{
    private readonly Book $book;

    /** @param list<HostProfile> $profiles */
    public function __construct(array $profiles = [])
    {
        $this->book = new Book();

        foreach ($profiles as $profile) {
            $this->add($profile);
        }
    }

    /** @return list<HostProfile> */
    public function all(): array
    {
        $profiles = [];

        foreach ($this->book->all() as $payload) {
            if ($payload instanceof HostProfile) {
                $profiles[] = $payload;
            }
        }

        return $profiles;
    }

    public function count(): int
    {
        return $this->book->count();
    }

    public function isEmpty(): bool
    {
        return $this->book->count() === 0;
    }

    public function find(string $name): ?HostProfile
    {
        $payload = $this->book->find($name);

        return $payload instanceof HostProfile ? $payload : null;
    }

    public function at(int $index): ?HostProfile
    {
        return $this->all()[$index] ?? null;
    }

    /** Dopisuje albo **zastępuje** wpis o tej samej nazwie, zachowując jego miejsce. */
    public function add(HostProfile $profile): void
    {
        $this->book->put($profile->name, $profile);
    }

    public function remove(string $name): bool
    {
        return $this->book->remove($name);
    }

    /** @return list<string> nazwy własne — materiał na podpowiedzi argumentów komendy */
    public function names(): array
    {
        return $this->book->names();
    }
}
