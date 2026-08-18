<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Module\Docker\Domain\ValueObject\DockerEnvironment;

/**
 * Książka środowisk prowadzona przez użytkownika (krok 58, wzorem `HostBook`).
 *
 * Kolekcja **mutowalna w miejscu** i z tych samych powodów, co książka hostów:
 * jest własnością modułu, tożsamością wpisu jest nazwa własna (dopisanie pod
 * zajętą nazwą **zastępuje**), a kolejność jest kolejnością dopisywania
 * i przeżywa zapis.
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

    /** @var list<DockerEnvironment> */
    private array $entries;

    private string $current;

    /** @param list<DockerEnvironment> $entries */
    public function __construct(array $entries = [], string $current = self::DEFAULT_NAME)
    {
        $this->entries = [];
        $this->current = $current === '' ? self::DEFAULT_NAME : $current;

        foreach ($entries as $entry) {
            $this->add($entry);
        }
    }

    /** @return list<DockerEnvironment> */
    public function all(): array
    {
        return $this->entries;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function find(string $name): ?DockerEnvironment
    {
        foreach ($this->entries as $entry) {
            if ($entry->name === $name) {
                return $entry;
            }
        }

        return null;
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
        $entries = [];
        $replaced = false;

        foreach ($this->entries as $existing) {
            if ($existing->equals($entry)) {
                $entries[] = $entry;
                $replaced = true;

                continue;
            }

            $entries[] = $existing;
        }

        if (!$replaced) {
            $entries[] = $entry;
        }

        $this->entries = $entries;
    }

    public function remove(string $name): bool
    {
        $entries = [];
        $removed = false;

        foreach ($this->entries as $entry) {
            if ($entry->name === $name) {
                $removed = true;

                continue;
            }

            $entries[] = $entry;
        }

        $this->entries = $entries;

        // Skasowanie wpisu bieżącego nie zostawia nazwy wskazującej donikąd:
        // wybór wraca do gniazda lokalnego, czyli do stanu sprzed pierwszego
        // wyboru.
        if ($removed && $this->current === $name) {
            $this->current = self::DEFAULT_NAME;
        }

        return $removed;
    }
}
