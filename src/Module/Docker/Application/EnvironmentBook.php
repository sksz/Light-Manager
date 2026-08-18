<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Application\State\Book;
use LightManager\Module\Docker\Domain\ValueObject\DockerEnvironment;

/**
 * Książka środowisk prowadzona przez użytkownika (krok 58, wzorem `HostBook`).
 *
 * Kolekcja **mutowalna w miejscu** i z tych samych powodów, co książka hostów:
 * jest własnością modułu, tożsamością wpisu jest nazwa własna (dopisanie pod
 * zajętą nazwą **zastępuje**), a kolejność jest kolejnością dopisywania
 * i przeżywa zapis. Porządek i tożsamość niesie od kroku 59 **rdzeniowa
 * `Book`** (wynik przeglądu 15e, D103); modułowi zostało to, czego rdzeń nie
 * zna — że ładunkiem wpisu jest `DockerEnvironment`.
 *
 * Ponad tamten wzorzec książka niesie jedno pole więcej: **nazwę wpisu
 * bieżącego**. Bieżące bywa też wpisem spoza książki (kontekstem klienta albo
 * gniazdem lokalnym), więc pole jest napisem, a nie wskazaniem na wpis —
 * o tym, co ta nazwa znaczy w tej chwili, rozstrzyga spis złożony z obu
 * źródeł, nie sama książka.
 */
final class EnvironmentBook
{
    /** Nazwa wpisu bieżącego, gdy użytkownik jeszcze żadnego nie wybrał. */
    public const DEFAULT_NAME = 'default';

    private readonly Book $book;

    private string $current;

    /** @param list<DockerEnvironment> $entries */
    public function __construct(array $entries = [], string $current = self::DEFAULT_NAME)
    {
        $this->book = new Book();
        $this->current = $current === '' ? self::DEFAULT_NAME : $current;

        foreach ($entries as $entry) {
            $this->add($entry);
        }
    }

    /** @return list<DockerEnvironment> */
    public function all(): array
    {
        $entries = [];

        foreach ($this->book->all() as $payload) {
            if ($payload instanceof DockerEnvironment) {
                $entries[] = $payload;
            }
        }

        return $entries;
    }

    public function count(): int
    {
        return $this->book->count();
    }

    public function find(string $name): ?DockerEnvironment
    {
        $payload = $this->book->find($name);

        return $payload instanceof DockerEnvironment ? $payload : null;
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
    public function add(DockerEnvironment $entry): void
    {
        $this->book->put($entry->name, $entry);
    }

    public function remove(string $name): bool
    {
        $removed = $this->book->remove($name);

        // Skasowanie wpisu bieżącego nie zostawia nazwy wskazującej donikąd:
        // wybór wraca do gniazda lokalnego, czyli do stanu sprzed pierwszego
        // wyboru.
        if ($removed && $this->current === $name) {
            $this->current = self::DEFAULT_NAME;
        }

        return $removed;
    }
}
