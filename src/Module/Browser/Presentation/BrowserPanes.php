<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation;

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

    private readonly SplitState $split;

    public function __construct(BrowserState $first, BrowserState $second, int $scrollMargin)
    {
        $this->states = [$first, $second];
        $this->windows = [new ScrollWindow($scrollMargin), new ScrollWindow($scrollMargin)];
        $this->split = new SplitState();
    }

    public function focused(): BrowserState
    {
        return $this->states[$this->split->focusesSecond() ? 1 : 0];
    }

    public function focusesSecond(): bool
    {
        return $this->split->focusesSecond();
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

    /**
     * Przeniesienie ogniska. Kontekst sesji ogłaszamy od razu, bo zaznaczenie,
     * o którym mówi, jest **od tej chwili inne** — moduł opisujący plik ma
     * pokazać wpis z panelu, do którego użytkownik właśnie przeszedł, a nie
     * z tego, który zostawił.
     */
    public function moveFocus(): void
    {
        $this->split->moveFocus();
        $this->focused()->selectionChanged();
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
            $this->focused()->selectionChanged();
        }
    }
}
