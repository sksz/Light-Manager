<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

use LightManager\Application\Module\ModuleSetting;

/**
 * Gdzie stoi kursor na ekranie ustawień.
 *
 * Pasek zakładek jest jednym z miejsc, które kursor odwiedza — stąd `item`
 * równe `null` zamiast osobnego znacznika „jesteśmy na zakładkach”. Strzałki
 * góra/dół przechodzą między paskiem a pozycjami, strzałki lewo/prawo znaczą
 * co innego w każdym z tych dwóch miejsc: na pasku zmieniają zakładkę, na
 * pozycji — wartość ustawienia.
 *
 * Pod pozycjami zakładki rdzenia stoi **wiersz czynności** — jedno miejsce
 * więcej, niż jest w niej ustawień (krok 18, P12: przycisk „przywróć ustawienia
 * domyślne”). Kursor odwiedza go tak samo jak pozycję, ale `key()` nie ma tam
 * czego zwrócić, bo czynność nie jest ustawieniem.
 *
 * Od kroku 20 kursor chodzi po **liście zakładek podanej z zewnątrz**, a nie po
 * `SettingsTab::cases()`: zakładki modułów powstają przy starcie i enum nie
 * miałby jak ich zobaczyć. Lista pusta jest legalna — kursor stoi wtedy na
 * pasku i nie ma dokąd zejść.
 */
final class SettingsCursor
{
    /** @param list<SettingsTab> $tabs zakładki tego uruchomienia, w kolejności paska */
    public function __construct(
        private readonly array $tabs,
        /** Indeks aktywnej zakładki na liście. */
        public readonly int $tab = 0,
        /** Indeks pozycji w zakładce; `null` znaczy „kursor na pasku zakładek”. */
        public readonly ?int $item = null,
    ) {
    }

    /** @return list<SettingsTab> */
    public function tabs(): array
    {
        return $this->tabs;
    }

    public function activeTab(): ?SettingsTab
    {
        return $this->tabs[$this->tab] ?? null;
    }

    public function isOnTabBar(): bool
    {
        return $this->item === null;
    }

    /** Czy kursor stoi na wierszu czynności pod ostatnią pozycją. */
    public function isOnAction(): bool
    {
        $tab = $this->activeTab();

        return $tab !== null && $tab->hasAction() && $this->item !== null && $this->item >= $tab->itemCount();
    }

    /** Ustawienie rdzenia pod kursorem; `null` wszędzie indziej. */
    public function key(): ?SettingKey
    {
        if ($this->item === null) {
            return null;
        }

        return $this->activeTab()?->keyAt($this->item);
    }

    /** Pozycja modułu pod kursorem; `null` wszędzie indziej. */
    public function setting(): ?ModuleSetting
    {
        if ($this->item === null) {
            return null;
        }

        return $this->activeTab()?->settingAt($this->item);
    }

    /**
     * Ruch w pionie. Z paska zakładek w dół wchodzi się na pierwszą pozycję, z
     * pierwszej pozycji w górę wraca na pasek; na krańcach lista się nie zawija,
     * bo zawijanie w pionie gubi orientację przy dwóch sąsiadujących listach.
     */
    public function movedBy(int $delta): self
    {
        $tab = $this->activeTab();
        $count = ($tab?->itemCount() ?? 0) + ($tab !== null && $tab->hasAction() ? 1 : 0);

        if ($this->item === null) {
            return $delta > 0 && $count > 0 ? new self($this->tabs, $this->tab, 0) : $this;
        }

        $next = $this->item + $delta;

        if ($next < 0) {
            return new self($this->tabs, $this->tab, null);
        }

        return new self($this->tabs, $this->tab, min($next, max(0, $count - 1)));
    }

    /** Zmiana zakładki zostawia kursor na pasku — dopiero strzałka w dół wchodzi w treść. */
    public function switchedTab(int $direction): self
    {
        $count = count($this->tabs);

        if ($count === 0) {
            return $this;
        }

        $step = $direction < 0 ? -1 : 1;

        return new self($this->tabs, ($this->tab + $step + $count) % $count, null);
    }

    /** Numer wiersza kursora w treści panelu: pasek zakładek stoi w wierszu zerowym. */
    public function row(): int
    {
        return $this->item === null ? 0 : $this->item + 2;
    }

    public function equals(self $other): bool
    {
        return $this->tab === $other->tab && $this->item === $other->item;
    }
}
