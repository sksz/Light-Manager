<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation;

use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Browser\Domain\ValueObject\NameFilter;
use LightManager\Presentation\Cli\LoopState;

/**
 * Bieżący katalog modułu przeglądarki wraz z publikacją kontekstu sesji.
 *
 * Do kroku 20 katalog leżał w `LoopState` — czyli w stanie **powłoki**, mimo że
 * jest stanem jednej konkretnej funkcji. Krok 21 wyprowadza go tutaj i zostawia
 * powłoce to, co naprawdę do niej należy: ustawienia, komunikat, okna nakładane,
 * czas klatki i kontekst sesji.
 *
 * **Publikowanie kontekstu ma jedno miejsce i to jest cały powód istnienia tej
 * klasy.** Katalog zmieniają dwie rzeczy: klawisze ekranu i komenda `browser.jump`.
 * Gdyby ekran publikował kontekst w `draw()`, a komenda w `execute()`, kontekst
 * zacząłby się rozjeżdżać o klatkę przy pierwszym module o dwóch wejściach.
 * Zmiana przechodzi więc przez ten obiekt, a publikacja jest tam, gdzie zmiana.
 *
 * Klasa leży w warstwie `Presentation` modułu, bo dostaje `LoopState` — obiekt
 * warstwy dostarczania. Ta sama zasada, która w kroku 20 postawiła `JumpCommand`
 * w `Presentation` modułu, a nie w jego `Application` (D41).
 *
 * **Od kroku 30 panel ma dwa katalogi zamiast jednego**: ten z dysku i ten
 * widoczny po zawężeniu filtrem. Zawężenie mieszka tutaj, a nie w agregacie
 * `Directory`, i jest to rozstrzygnięcie ze startu tamtego kroku: filtr jest
 * widokiem na katalog, a nie jego własnością — dwa panele otwarte na tym samym
 * katalogu mają prawo mieć różne filtry, a katalog na dysku jest jeden.
 */
final class BrowserState
{
    /**
     * Katalog **taki, jaki przyszedł z dysku** — bez zawężenia filtrem.
     *
     * Bez tego pola wyjście z filtra wymagałoby ponownego odczytu katalogu, czyli
     * sięgnięcia na dysk po dane, które już się ma. Przy pustym filtrze jest to
     * **ten sam obiekt**, co `$directory`, a nie jego kopia: klatka bez filtra ma
     * kosztować dokładnie tyle, co przed krokiem 30, więc nie ma prawa przejść
     * przez ani jedno `array_filter`.
     */
    private Directory $all;

    private NameFilter $filter;

    public function __construct(
        private readonly LoopState $state,
        private Directory $directory,
    ) {
        $this->all = $directory;
        $this->filter = NameFilter::none();
        $this->publish();
    }

    /** Katalog **widoczny** — zawężony filtrem, jeśli filtr jest ustawiony. */
    public function directory(): Directory
    {
        return $this->directory;
    }

    /**
     * Wejście do innego katalogu — jedyna droga, którą katalog się zmienia.
     *
     * **Filtr znika razem z katalogiem** i jest to rozstrzygnięcie planu kroku 30,
     * nie skutek uboczny: fragment nazwy wpisany po to, żeby znaleźć coś tutaj,
     * w katalogu obok znaczy co innego albo nie znaczy nic, a lista pusta zaraz
     * po wejściu wyglądałaby jak pusty katalog.
     */
    public function enter(Directory $directory): void
    {
        $this->all = $directory;
        $this->directory = $directory;
        $this->filter = NameFilter::none();
        $this->publish();
    }

    public function filter(): NameFilter
    {
        return $this->filter;
    }

    /**
     * Zawężenie listy wpisanym fragmentem — **w tej samej klatce**, bo filtrowanie
     * tablicy wpisów jest tanie i praca kawałkowa (D46) go nie dotyczy.
     *
     * Zaznaczenie przeżywa zawężenie, o ile wskazany wpis nadal jest widoczny:
     * przenosi się je **po nazwie**, nie po numerze, bo filtr zmienia numery
     * wszystkim wpisom naraz. Gdy zaznaczony wpis wypadł z listy, `Directory`
     * sprowadza zaznaczenie na jej początek — tak samo, jak przy ukrywaniu wpisów.
     */
    public function useFilter(string $value): void
    {
        $filter = new NameFilter($value);

        if ($filter->equals($this->filter)) {
            return;
        }

        $keep = $this->directory->selectedEntry()?->name;
        $this->filter = $filter;
        $this->rebuild($keep);
    }

    /**
     * Powrót do pełnej listy wraz z zaznaczeniem na wskazanym wpisie.
     *
     * Nazwa przychodzi z zewnątrz, bo to wołający wie, do czego wraca: okno filtra
     * pamięta wpis sprzed otwarcia i podaje go przy odmowie (`Esc`), a przy
     * zatwierdzeniu (`Enter`) zostawia zaznaczenie tam, gdzie stoi.
     */
    public function clearFilter(?string $select = null): void
    {
        $keep = $select ?? $this->directory->selectedEntry()?->name;
        $this->filter = NameFilter::none();
        $this->rebuild($keep);
    }

    private function rebuild(?string $keep): void
    {
        if ($this->filter->isEmpty()) {
            $this->directory = $this->all;
        } else {
            $entries = [];

            foreach ($this->all->entries() as $entry) {
                if ($this->filter->matches($entry->name)) {
                    $entries[] = $entry;
                }
            }

            $this->directory = new Directory($this->all->path(), $entries);
        }

        if ($keep !== null) {
            $this->directory->selectEntryNamed($keep);
        }

        $this->publish();
    }

    /**
     * Zaznaczenie zmieniło się **w** katalogu, więc obiekt jest ten sam, a
     * kontekst już nie. Agregat jest mutowalny w miejscu, więc bez tego wywołania
     * nikt by się o zmianie nie dowiedział.
     */
    public function selectionChanged(): void
    {
        $this->publish();
    }

    /**
     * Kontekst wskazany przez **drzewo** panelu (krok 31).
     *
     * Katalog przychodzi z zewnątrz, bo węzeł pod kursorem drzewa leży zwykle
     * głębiej niż katalog panelu — a moduł opisujący plik ma pokazać ten wpis,
     * na który użytkownik patrzy, nie ten, od którego zaczął. `BrowserTree`
     * podaje gotowy agregat z zaznaczeniem, więc publikacja zostaje **jedna**
     * i liczy się dokładnie tak samo, jak dla listy.
     */
    public function publishNode(Directory $node): void
    {
        $this->publishFrom($node);
    }

    /**
     * Widoczność wpisów ukrytych czytana z ustawień, nie z własnego pola: jedno
     * miejsce prawdy zamiast dwóch, które musiałyby się pilnować nawzajem.
     */
    public function showsHiddenEntries(): bool
    {
        return BrowserSettings::showHidden($this->state->settings());
    }

    private function publish(): void
    {
        $this->publishFrom($this->directory);
    }

    private function publishFrom(Directory $directory): void
    {
        $entry = $directory->selectedEntry();

        $this->state->publishContext(new ModuleContext(
            $directory->path()->value,
            $entry?->name,
            self::kindOf($entry),
        ));
    }

    private static function kindOf(?Entry $entry): ContextEntryKind
    {
        if ($entry === null) {
            return ContextEntryKind::None;
        }

        return $entry->isDirectory() ? ContextEntryKind::Directory : ContextEntryKind::File;
    }
}
