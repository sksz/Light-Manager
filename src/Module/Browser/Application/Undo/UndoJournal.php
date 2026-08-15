<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Application\Undo;

use Closure;

/**
 * Stos wykonanych operacji — pamięć, z której czerpie cofanie (krok 44).
 *
 * Leży w module, **nie w rdzeniu, wbrew literze planu kroku** — plan pisano
 * przed krokami 41–43, a gdy operacje zmaterializowały się w całości po stronie
 * modułu przeglądarki, dziennik został z jednym piszącym i jednym czytającym.
 * Reguła 15 („funkcja o jednym odbiorcy jest modułem”) wygrywa wtedy z zapisem
 * w planie — ten sam rachunek, którym krok 36 zaprowadził dźwięk do modułu.
 *
 * Trzy własności, wszystkie z rozstrzygnięć startowych (D81):
 *
 * - **głębokość jest pozycją ustawień** (nr 7) — stąd domknięcie zamiast
 *   stałej: stos pyta o limit przy każdym zapisie, więc zmiana ustawienia
 *   działa od następnej operacji, bez restartu;
 * - **wpisy nieodwracalne też się zapisują** (nr 8) — widok pokazuje je
 *   wyszarzone, bo odpowiada również na pytanie „co się właściwie wydarzyło”;
 * - **zapis nie przeżywa zamknięcia aplikacji** — cofanie po restarcie byłoby
 *   dziennikiem transakcji, a nie wygodą.
 */
final class UndoJournal
{
    /** @var list<UndoEntry> najnowszy pierwszy */
    private array $entries = [];

    /** @param Closure(): int $depth głębokość stosu — z ustawień modułu */
    public function __construct(
        private readonly Closure $depth,
    ) {
    }

    public function record(UndoEntry $entry): void
    {
        array_unshift($this->entries, $entry);
        $this->entries = array_slice($this->entries, 0, max(1, ($this->depth)()));
    }

    /** @return list<UndoEntry> najnowszy pierwszy */
    public function entries(): array
    {
        return $this->entries;
    }

    public function at(int $index): ?UndoEntry
    {
        return $this->entries[$index] ?? null;
    }

    /** Najnowszy wpis, który da się cofnąć — cel klawisza cofania. */
    public function latestReversibleIndex(): ?int
    {
        foreach ($this->entries as $index => $entry) {
            if ($entry->reversible()) {
                return $index;
            }
        }

        return null;
    }

    /** Cofnięcie wykonane w całości zdejmuje zapis — połowiczne go wymienia. */
    public function drop(UndoEntry $entry): void
    {
        $index = array_search($entry, $this->entries, true);

        if ($index !== false) {
            array_splice($this->entries, $index, 1);
        }
    }

    public function replace(UndoEntry $entry, UndoEntry $with): void
    {
        $index = array_search($entry, $this->entries, true);

        if ($index !== false) {
            $this->entries[$index] = $with;
        }
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }
}
