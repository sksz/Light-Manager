<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation;

use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Browser\Domain\ValueObject\MarkedEntries;
use LightManager\Presentation\Ui\ScrollWindow;
use LightManager\Presentation\Ui\SplitState;

/**
 * Dwa panele przeglądarki: dwa katalogi, dwa kursory, dwa okna przewijania —
 * i jedno ognisko.
 *
 * Klasa istnieje dlatego, że **panel to nie jest jedna rzecz, tylko trzy**:
 * katalog (`BrowserState`), wycinek listy (`ScrollWindow`) i to, czy klawisze
 * idą właśnie tutaj (`SplitState`). Trzymanie tych trzech w tablicach wewnątrz
 * ekranu działałoby dopóty, dopóki nikt nie pomyliłby indeksu — a pomyliłby przy
 * pierwszym miejscu, w którym „panel czynny” trzeba wyliczyć po raz drugi.
 *
 * Panel drugi istnieje **zawsze**, także przy wyłączonym podziale: jego katalog
 * jest wtedy otwarty, ale niewidoczny i nieruchomy. Wariant, w którym powstaje
 * przy włączeniu podziału, wymagałby otwarcia katalogu w środku klatki — czyli
 * odczytu z dysku w miejscu, w którym reszta aplikacji tylko rysuje.
 *
 * Kontekst sesji publikuje **panel czynny** i to jest jedyna reguła, którą ta
 * klasa dokłada do zasady z kroku 21 („publikacja jest tam, gdzie zmiana”):
 * przeniesienie ogniska jest zmianą tego, na co użytkownik patrzy, więc jest
 * i publikacją.
 */
final class BrowserPanes
{
    /** @var array{BrowserState, BrowserState} */
    private readonly array $states;

    /** @var array{ScrollWindow, ScrollWindow} */
    private readonly array $windows;

    /** @var array{BrowserTree, BrowserTree} */
    private readonly array $trees;

    /**
     * Który panel pokazuje drzewo zamiast listy (krok 31).
     *
     * Odpowiedź należy do **panelu**, a nie do ustawień modułu, i to jest
     * rozstrzygnięcie użytkownika ze startu kroku: widok przełącza się klawiszem
     * w trakcie pracy, więc jest stanem sesji, jak ognisko — a nie wyborem
     * zapisywanym do pliku, jak podział czy kolumny szczegółów.
     *
     * @var array{bool, bool}
     */
    private array $asTree = [false, false];

    private readonly SplitState $split;

    public function __construct(
        BrowserState $first,
        BrowserState $second,
        int $scrollMargin,
        BrowserTree $firstTree,
        BrowserTree $secondTree,
        /**
         * Stan podziału przychodzi **z zewnątrz** od kroku 55: niesie odtąd
         * proporcję wczytaną z ustawień modułu i zapis po przeciągnięciu granicy,
         * a jedno i drugie zna moduł, nie panele. Domyślny — bez pamięci —
         * zostaje dla testów składających panele samodzielnie.
         */
        ?SplitState $split = null,
    ) {
        $this->states = [$first, $second];
        $this->windows = [new ScrollWindow($scrollMargin), new ScrollWindow($scrollMargin)];
        $this->trees = [$firstTree, $secondTree];
        $this->split = $split ?? new SplitState();
    }

    public function focused(): BrowserState
    {
        return $this->states[$this->split->focusesSecond() ? 1 : 0];
    }

    public function focusesSecond(): bool
    {
        return $this->split->focusesSecond();
    }

    /**
     * Stan podziału — od kroku 55 niesie także **proporcję i przeciąganie
     * granicy**, więc ekran musi mieć do niego dostęp, a nie tylko do samego
     * pytania o ognisko.
     */
    public function split(): SplitState
    {
        return $this->split;
    }

    /** Ognisko postawione wprost — tak działa kliknięcie w panel (krok 55). */
    public function focusPane(int $index): void
    {
        $this->split->focus($index === 1);
    }

    /**
     * Katalog z zaznaczeniem na tym, co panel czynny **wskazuje** — z drzewa
     * albo z listy, zależnie od widoku.
     *
     * Bliźniak `publishFocused()` i powstał z tego samego powodu: odpowiedź na
     * pytanie „co jest zaznaczone” jest w drzewie inna niż w liście, a miejsc,
     * które je zadają, przybyło (krok 32: komenda `browser.open` działa na
     * zaznaczeniu, więc musi widzieć dokładnie to, co widzi kontekst sesji).
     * Dwa rachunki tej samej rzeczy rozjechałyby się przy pierwszym widoku,
     * który dojdzie po drzewie.
     */
    public function focusedDirectory(): Directory
    {
        return $this->focusShowsTree()
            ? $this->focusedTree()->cursorDirectory()
            : $this->focused()->directory();
    }

    /**
     * Katalog i wpis, na który panel czynny **wskazuje** — albo `null`, gdy lista
     * jest pusta.
     *
     * Para chodzi razem, bo czynności zmieniające dysk potrzebują obu naraz:
     * wpisu, żeby wiedzieć, na czym działają, i katalogu, żeby wiedzieć gdzie.
     * Rachunek stoi tutaj od kroku 42, gdy zamówił go drugi wołający
     * (`EntryTransfer` obok `EntryOperations`) — a odpowiedź na pytanie „co jest
     * zaznaczone” jest w drzewie inna niż w liście i policzona dwa razy
     * rozjechałaby się przy pierwszym widoku, który dojdzie po drzewie.
     *
     * @return ?array{Directory, Entry}
     */
    public function focusedSelection(): ?array
    {
        $directory = $this->focusedDirectory();
        $entry = $directory->selectedEntry();

        return $entry === null ? null : [$directory, $entry];
    }

    /**
     * Zbiór zaznaczonych panelu czynnego — **pusty, gdy panel pokazuje drzewo**
     * (krok 43, D80 rozstrzygnięcie 9).
     *
     * Zaznaczenie jest własnością listy: trzyma nazwy z jednego katalogu, a węzły
     * drzewa leżą na różnych poziomach. Przełączenie widoku zbioru **nie kasuje** —
     * powrót do listy zastaje go takim, jaki był — ale dopóki widać drzewo, zbiór
     * nie istnieje dla nikogo: ani dla znacznika w wierszu, ani dla podsumowania
     * w pasie ścieżki, ani dla czynności. Inaczej `F8` w drzewie usuwałoby dwanaście
     * wpisów, których na ekranie nie widać.
     */
    public function focusedMarked(): MarkedEntries
    {
        return $this->markedOf($this->split->focusesSecond() ? 1 : 0);
    }

    /**
     * Zaznaczenie wskazanego panelu — z regułą „drzewo zbioru nie widzi”
     * (D80 nr 9) w **jednym** miejscu.
     *
     * Rozdzielone z `focusedMarked()` w kroku 53, bo kwerenda `browser.marked`
     * pyta o panel podany numerem, a nie o ten z ogniskiem — i musi odpowiadać
     * tą samą regułą, którą kieruje się pas ścieżki. Dwa rachunki tego samego
     * rozjechałyby się przy pierwszej poprawce.
     */
    public function markedOf(int $index): MarkedEntries
    {
        return $this->showsTree($index) ? MarkedEntries::none() : $this->pane($index)[0]->marked();
    }

    /**
     * Katalog i nazwy, na które ma zadziałać czynność zmieniająca dysk (krok 43).
     *
     * Jedno miejsce dla wszystkich czynności i dla obu widoków: w drzewie jest to
     * węzeł pod kursorem, w liście — zbiór zaznaczonych, a gdy jest pusty, wpis
     * pod kursorem (reguła pustego zbioru). `null` znaczy „nie ma na czym
     * działać”.
     *
     * @return ?array{Directory, list<string>}
     */
    public function focusedOperands(): ?array
    {
        if ($this->focusShowsTree()) {
            $selection = $this->focusedSelection();

            return $selection === null ? null : [$selection[0], [$selection[1]->name]];
        }

        $pane = $this->focused();
        $names = $pane->operands();

        return $names === [] ? null : [$pane->directory(), $names];
    }

    /** @return array{BrowserState, ScrollWindow, bool} katalog, okno i czy panel jest czynny */
    public function pane(int $index): array
    {
        $index = $index === 1 ? 1 : 0;

        return [$this->states[$index], $this->windows[$index], $this->split->focusesSecond() === ($index === 1)];
    }

    /** @return array{BrowserState, BrowserState} oba katalogi — dla zmian dotyczących obu paneli */
    public function all(): array
    {
        return $this->states;
    }

    /** Drzewo panelu — swoje własne, bo rozwinięcia jednego panelu nie są rozwinięciami drugiego. */
    public function tree(int $index): BrowserTree
    {
        return $this->trees[$index === 1 ? 1 : 0];
    }

    public function focusedTree(): BrowserTree
    {
        return $this->tree($this->split->focusesSecond() ? 1 : 0);
    }

    /**
     * Oba drzewa zapominają odczytane gałęzie (krok 41).
     *
     * Zmiana na dysku dotyczy obu paneli, bo dysk jest jeden — a który z nich ma
     * właśnie rozwinięty węzeł nad zmienionym katalogiem, nie wie nikt i nie warto
     * tego liczyć.
     */
    public function forgetBranches(): void
    {
        foreach ($this->trees as $tree) {
            $tree->forgetBranches();
        }
    }

    public function showsTree(int $index): bool
    {
        return $this->asTree[$index === 1 ? 1 : 0];
    }

    public function focusShowsTree(): bool
    {
        return $this->showsTree($this->split->focusesSecond() ? 1 : 0);
    }

    /**
     * Zamiana widoku panelu z ogniskiem — lista na drzewo i z powrotem.
     *
     * Kontekst sesji ogłaszamy od razu, tą samą regułą, co przy przenoszeniu
     * ogniska: wskazany wpis jest **od tej chwili inny**, bo drzewo ma własny
     * kursor, a lista własne zaznaczenie.
     */
    public function toggleTree(): void
    {
        $index = $this->split->focusesSecond() ? 1 : 0;
        $this->asTree[$index] = !$this->asTree[$index];
        $this->publishFocused();
    }

    /**
     * Ogłoszenie kontekstu panelu z ogniskiem — z drzewa albo z listy, zależnie
     * od tego, co panel pokazuje.
     *
     * Jedno miejsce, bo powodów do ogłoszenia jest odtąd kilka (ruch kursora,
     * przeniesienie ogniska, zamiana widoku), a dwa miejsca publikacji rozjechałyby
     * się o klatkę — dokładnie tak, jak zapowiada `BrowserState`.
     */
    public function publishFocused(): void
    {
        if ($this->focusShowsTree()) {
            $this->focused()->publishNode($this->focusedTree()->cursorDirectory());

            return;
        }

        $this->focused()->selectionChanged();
    }

    /**
     * Przeniesienie ogniska. Kontekst sesji ogłaszamy od razu, bo zaznaczenie,
     * o którym mówi, jest **od tej chwili inne** — moduł opisujący plik ma
     * pokazać wpis z panelu, do którego użytkownik właśnie przeszedł, a nie
     * z tego, który zostawił.
     */
    public function moveFocus(): void
    {
        $this->split->moveFocus();
        $this->publishFocused();
    }

    /**
     * Podział jest włączony albo nie. Wyłączony sprowadza ognisko na pierwszy
     * panel — regułę trzyma `SplitState`, bo dotyczy każdego ekranu z podziałem,
     * a nie tylko przeglądarki.
     */
    public function useSplit(bool $enabled): void
    {
        $wasSecond = $this->split->focusesSecond();
        $this->split->useSplit($enabled);

        if ($wasSecond && !$this->split->focusesSecond()) {
            $this->publishFocused();
        }
    }
}
