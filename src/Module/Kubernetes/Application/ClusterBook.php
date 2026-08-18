<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Application\State\Book;
use LightManager\Module\Kubernetes\Domain\ValueObject\ClusterProfile;

/**
 * Książka klastrów prowadzona przez użytkownika (krok 59, D96 nr 3).
 *
 * Trzecia książka wpisów w projekcie — i pierwsza pisana od razu na rdzeniowej
 * `Book` (wynik przeglądu 15e, D103): porządek i tożsamość niesie rdzeń,
 * modułowi zostało to, czego rdzeń nie zna — że ładunkiem wpisu jest
 * `ClusterProfile`.
 *
 * Pole **wpisu bieżącego** jest napisem, jak w książce środowisk Dockera
 * i z tego samego powodu: bieżącym bywa wpis spoza książki (kontekst czytany
 * z pliku), więc o tym, co nazwa znaczy w tej chwili, rozstrzyga spis złożony
 * z obu źródeł, nie sama książka. Pusty napis znaczy „użytkownik jeszcze nie
 * wybrał" — wtedy obowiązuje dzisiejsze zachowanie modułu: bieżący kontekst
 * domyślnego pliku.
 */
final class ClusterBook
{
    private readonly Book $book;

    private string $current;

    /** @param list<ClusterProfile> $entries */
    public function __construct(array $entries = [], string $current = '')
    {
        $this->book = new Book();
        $this->current = $current;

        foreach ($entries as $entry) {
            $this->add($entry);
        }
    }

    /** @return list<ClusterProfile> */
    public function all(): array
    {
        $entries = [];

        foreach ($this->book->all() as $payload) {
            if ($payload instanceof ClusterProfile) {
                $entries[] = $payload;
            }
        }

        return $entries;
    }

    public function count(): int
    {
        return $this->book->count();
    }

    public function find(string $name): ?ClusterProfile
    {
        $payload = $this->book->find($name);

        return $payload instanceof ClusterProfile ? $payload : null;
    }

    public function current(): string
    {
        return $this->current;
    }

    public function makeCurrent(string $name): void
    {
        if ($name !== '') {
            $this->current = $name;
        }
    }

    /** Dopisuje albo **zastępuje** wpis o tej samej nazwie, zachowując jego miejsce. */
    public function add(ClusterProfile $entry): void
    {
        $this->book->put($entry->name, $entry);
    }

    public function remove(string $name): bool
    {
        $removed = $this->book->remove($name);

        // Skasowanie wpisu bieżącego nie zostawia nazwy wskazującej donikąd:
        // wybór wraca do „nie wybrano", czyli do bieżącego kontekstu
        // domyślnego pliku — stanu sprzed pierwszego wyboru.
        if ($removed && $this->current === $name) {
            $this->current = '';
        }

        return $removed;
    }
}
