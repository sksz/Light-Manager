<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application;

/**
 * Spis deklaracji rozdziałów tego uruchomienia (krok 60).
 *
 * Deklaracja jest **zapowiedzią użycia, nie zastrzeżeniem** (D104 nr 2): mówi
 * „będę używał tego rozdziału i tych pól", nie tworzy właściciela i nikomu
 * niczego nie zamyka. Spis jest przez to zwykłą mapą bez pojęcia „czyje" —
 * i to jest jedyna rzecz, którą trzeba o nim wiedzieć.
 *
 * Kolejność rozdziałów jest kolejnością pierwszej deklaracji, bo tak samo
 * ustawiają się zakładki na ekranie.
 *
 * `revision()` jest **pokoleniem kwerend deklaracji** (`address-book.chapters`,
 * `address-book.fields`): rośnie przy każdej zmianie treści spisu i nie rusza
 * się, gdy deklaracja była powtórzeniem — a deklaracje padają w takcie, czyli
 * trzydzieści razy na sekundę.
 */
final class Chapters
{
    /** @var array<string, AddressChapter> rozdziały w kolejności pierwszej deklaracji */
    private array $chapters = [];

    private int $revision = 0;

    public function revision(): int
    {
        return $this->revision;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->chapters);
    }

    /** @return list<AddressChapter> */
    public function all(): array
    {
        return array_values($this->chapters);
    }

    public function find(string $chapter): ?AddressChapter
    {
        return $this->chapters[$chapter] ?? null;
    }

    public function has(string $chapter): bool
    {
        return isset($this->chapters[$chapter]);
    }

    /**
     * Zakłada rozdział albo nazywa istniejący, gdy ten nazwy jeszcze nie ma;
     * `false` znaczy nazwę spoza kształtu.
     */
    public function declareChapter(string $chapter, string $titleKey): bool
    {
        if (!AddressChapter::isValidName($chapter)) {
            return false;
        }

        $existing = $this->chapters[$chapter] ?? null;

        if ($existing !== null) {
            $existing->nameIfUnnamed($titleKey);

            return true;
        }

        $this->chapters[$chapter] = new AddressChapter($chapter, $titleKey);
        ++$this->revision;

        return true;
    }

    /**
     * Dopisuje pole do rozdziału; `false` znaczy **deklarację sprzeczną** z tą,
     * która już stoi (patrz `AddressChapter::declare()`).
     *
     * Rozdział nieznany powstaje po drodze, bez tytułu — bo moduł, który
     * zadeklarował samo pole, i tak zapowiedział użycie rozdziału.
     */
    public function declareField(string $chapter, ChapterField $field): bool
    {
        if (!AddressChapter::isValidField($field->key) || !$this->declareChapter($chapter, '')) {
            return false;
        }

        $before = $this->chapters[$chapter]->fieldCount();
        $accepted = $this->chapters[$chapter]->declare($field);

        if ($this->chapters[$chapter]->fieldCount() !== $before) {
            ++$this->revision;
        }

        return $accepted;
    }
}
