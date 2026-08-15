<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation;

use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Module\Browser\Application\BrowserEvent;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Browser\Domain\ValueObject\MarkedEntries;
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
 *
 * **Od kroku 43 panel ma ponadto zbiór zaznaczonych** i stoi on tutaj z tego
 * samego powodu, co filtr: dwa panele otwarte na tym samym katalogu mają prawo
 * mieć różne zaznaczenia. Zbiór ginie razem z katalogiem (`enter()`), a przeżywa
 * zawężenie i odświeżenie — nazwy zaznaczone tutaj w katalogu obok znaczą co
 * innego albo nic, ale ten sam katalog odczytany na nowo jest tym samym
 * katalogiem.
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

    /**
     * Wpisy zaznaczone wielokrotnie — **nazwami**, nie numerami (krok 43).
     *
     * Zbiór odnosi się do katalogu pełnego (`$all`), a nie do widocznego: wpis
     * wypchnięty poza widok przez filtr nadal do niego należy (rozstrzygnięcie 4),
     * więc przycinanie zbioru dzieje się wyłącznie tam, gdzie zmienia się katalog
     * na dysku — w `refresh()` — a nigdy przy zawężaniu listy.
     */
    private MarkedEntries $marked;

    public function __construct(
        private readonly LoopState $state,
        private Directory $directory,
    ) {
        $this->all = $directory;
        $this->filter = NameFilter::none();
        $this->marked = MarkedEntries::none();
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
        // Zdarzenie ogłasza **zmianę**, a nie wywołanie tej metody, i to jest
        // różnica warta jednego porównania (krok 46): tą samą drogą wchodzi
        // przełączenie wpisów ukrytych (`HiddenEntries`) i odczyt katalogu na
        // nowo po operacji — a jedno i drugie zostaje w tym samym miejscu.
        // Publikujemy wprost rejestrem ze stanu pętli, bez pośrednika: nie ma tu
        // czego rozstrzygać tonem, bo wejście do katalogu nie ma dwóch końców.
        $changed = !$this->directory->path()->equals($directory->path());

        $this->all = $directory;
        $this->directory = $directory;
        $this->filter = NameFilter::none();
        // Zbiór ginie razem z filtrem i z tego samego powodu (krok 43): nazwa
        // zaznaczona tutaj w katalogu obok znaczy co innego albo nic, a operacja
        // działająca na zbiorze odziedziczonym po poprzednim miejscu byłaby
        // operacją na wpisach, których użytkownik nie widział.
        $this->marked = MarkedEntries::none();
        $this->publish();

        if ($changed) {
            $this->state->events()->publish(BrowserEvent::DirectoryEntered->value);
        }
    }

    /**
     * Ten sam katalog odczytany na nowo — po **własnej** zmianie na dysku (krok 41).
     *
     * Różni się od `enter()` jedną rzeczą, za to zasadniczą: **filtr zostaje**.
     * Wejście do innego katalogu zdejmuje zawężenie, bo fragment nazwy wpisany
     * tutaj w katalogu obok znaczy co innego (krok 30) — ale katalog odświeżony
     * jest tym samym katalogiem, a użytkownik, który zawęził listę, żeby coś w niej
     * znaleźć, nie prosił o powrót do pełnej za to, że zmienił nazwę.
     *
     * @param ?string $select nazwa, na której ma stanąć zaznaczenie; `null` znaczy
     *                        „ta, którą niesie odczytany katalog”
     */
    public function refresh(Directory $directory, ?string $select = null): void
    {
        $keep = $select ?? $directory->selectedEntry()?->name;
        $this->all = $directory;
        // Zbiór przycina się **tutaj i tylko tutaj** (krok 43): wpisy, które
        // zniknęły z dysku, znikają z niego, a te, których operacja nie dotknęła
        // — pominięte przy kolizji, nieudane — zostają zaznaczone. To jedyna
        // droga, którą użytkownik dowie się, co się nie udało, bez listy błędów,
        // której aplikacja nie ma.
        $this->marked = $this->marked->keptFrom(self::sizesOf($directory));
        $this->rebuild($keep);
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

    public function marked(): MarkedEntries
    {
        return $this->marked;
    }

    /**
     * Ile wpisów ma katalog **przed** zawężeniem filtrem.
     *
     * Pytanie zadaje podsumowanie zbioru w pasie ścieżki: zaznaczenie odnosi się
     * do katalogu pełnego, więc mianownik też musi (krok 43).
     */
    public function fullCount(): int
    {
        return count($this->all->entries());
    }

    /**
     * Spacja: przełącza zaznaczenie wpisu **pod kursorem**.
     *
     * Katalog wolno zaznaczyć na równi z plikiem (rozstrzygnięcie 7) i niesie
     * wtedy `null` zamiast rozmiaru — bo rozmiaru katalogu nie znamy, a nie
     * dlatego, że jest zerowy.
     *
     * Oddaje `true`, gdy coś się zmieniło; `false` znaczy „nie ma czego
     * zaznaczyć” i pozwala wołającemu odróżnić pustą listę od zwykłego
     * przełączenia.
     */
    public function toggleMark(): bool
    {
        $entry = $this->directory->selectedEntry();

        if ($entry === null) {
            return false;
        }

        $this->marked = $this->marked->toggled($entry->name, self::sizeOf($entry));
        $this->publish();

        return true;
    }

    /**
     * `*`: odwraca zaznaczenie na liście **widocznej** (rozstrzygnięcie 8).
     *
     * Wpisy wypchnięte poza widok przez filtr zostają w swoim stanie: klawisz
     * dotyczy tego, na co użytkownik patrzy, tą samą regułą, którą kieruje się
     * spis klawiszy od kroku 30.
     */
    public function invertMarks(): void
    {
        $this->marked = $this->marked->invertedOn(self::sizesOf($this->directory));
        $this->publish();
    }

    /**
     * `Esc` po zdjęciu filtra: czyści zbiór.
     *
     * Oddaje `true`, gdy było co czyścić — kolejność ustępowania `Esc`
     * (rozstrzygnięcie 3) rozstrzyga się bowiem po stronie ekranu, a ten musi
     * wiedzieć, czy klawisz cokolwiek zrobił.
     */
    public function clearMarks(): bool
    {
        if ($this->marked->isEmpty()) {
            return false;
        }

        $this->marked = MarkedEntries::none();
        $this->publish();

        return true;
    }

    /**
     * Wpisy, na które ma zadziałać czynność: zbiór, a gdy jest pusty — wpis pod
     * kursorem.
     *
     * **Reguła pustego zbioru mieszka tutaj**, w jednym miejscu dla wszystkich
     * czynności (krok 43): „brak zaznaczenia znaczy wpis pod kursorem”, a nie
     * „nic”. Inaczej każda operacja wymagałaby dwóch kroków tam, gdzie dziś
     * wymaga jednego — i każda musiałaby to samo pytanie zadać sobie osobno.
     *
     * @return list<string> nazwy w kolejności zaznaczania; pusta lista znaczy
     *                      „nie ma na czym działać”
     */
    public function operands(): array
    {
        if (!$this->marked->isEmpty()) {
            return $this->marked->names();
        }

        $entry = $this->directory->selectedEntry();

        return $entry === null ? [] : [$entry->name];
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
        $this->publishFrom($node, withMarked: false);
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

    /**
     * Zbiór jedzie w kontekście razem z wpisem pod kursorem, a nie zamiast niego
     * (krok 43, D80 rozstrzygnięcie 1): odbiorca ma prawo pokazać jedno, drugie
     * albo oba, a kontekst bez wpisu pod kursorem odebrałby modułowi opisu pliku
     * jedyne, co dziś czyta.
     *
     * Węzeł drzewa (`publishNode()`) idzie tą samą drogą i **zbioru nie niesie**,
     * bo zaznaczenie jest własnością listy (D80, rozstrzygnięcie 9) — pole
     * `$marked` należy wtedy do listy panelu, na którą użytkownik akurat nie
     * patrzy.
     */
    private function publishFrom(Directory $directory, bool $withMarked = true): void
    {
        $entry = $directory->selectedEntry();
        $marked = $withMarked ? $this->marked : MarkedEntries::none();

        $this->state->publishContext(new ModuleContext(
            $directory->path()->value,
            $entry?->name,
            self::kindOf($entry),
            $marked->count(),
            $marked->bytes(),
            $marked->directories(),
        ));
    }

    private static function kindOf(?Entry $entry): ContextEntryKind
    {
        if ($entry === null) {
            return ContextEntryKind::None;
        }

        return $entry->isDirectory() ? ContextEntryKind::Directory : ContextEntryKind::File;
    }

    /**
     * Nazwy wraz z rozmiarami — dana wejściowa zbioru, liczona z agregatu.
     *
     * @return array<string, ?int>
     */
    private static function sizesOf(Directory $directory): array
    {
        $sizes = [];

        foreach ($directory->entries() as $entry) {
            $sizes[$entry->name] = self::sizeOf($entry);
        }

        return $sizes;
    }

    /** Katalog nie ma rozmiaru; `null` mówi to wprost, a zero by skłamało. */
    private static function sizeOf(Entry $entry): ?int
    {
        return $entry->isDirectory() ? null : $entry->sizeInBytes;
    }
}
