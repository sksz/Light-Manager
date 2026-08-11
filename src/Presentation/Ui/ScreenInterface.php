<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use LightManager\Application\Dto\KeyPress;

/**
 * Ekran aplikacji — treść trzech stref klatki wraz z obsługą klawiszy.
 *
 * Do kroku 18 ekran był przypadkiem enuma `Screen`, a to, co rysuje i jak
 * reaguje, rozstrzygały dwa `match`-e: jeden w `GameLoop`, drugi
 * w `InputHandler`. Dopisanie ekranu wymagało dotknięcia obu, a przypadek enuma
 * nie należał do nikogo — wiedza o ekranie była rozsypana po pętli.
 *
 * Do kroku 20 ekran zajmował **wyłącznie środkowy panel**: pasek u góry i pas
 * podglądu rysował rdzeń, bo miał czym — katalog leżał w `LoopState`. Krok 21
 * zabrał mu ten katalog i wraz z nim podstawę, więc obie strefy przeszły do
 * ekranu (D40, P6). Ekran zamawia je przez `header()` i `preview()`, a `null`
 * znaczy „strefa nie powstaje, jej wiersze idą do środka”.
 *
 * Rdzeniowi zostają **oprawa i pasek stanu**: obwódki, nawiasy narożne, etykiety
 * stref (`ScreenZone::labelKey`), progi ustępowania w `HudLayout` oraz komunikat
 * z podpowiedziami klawiszy globalnych. Ekran nie rysuje ramek i nie zna motywu
 * od tej strony.
 *
 * Ekran jest komponentem, bo rysuje się dokładnie tak samo jak każdy inny —
 * dostaje prostokąt i oddaje prymitywy. Nie ma powodu, by rdzeń miał w tej
 * jednej sprawie własną ścieżkę.
 */
interface ScreenInterface extends ComponentInterface
{
    /** Identyfikator ekranu, unikalny w całym uruchomieniu. */
    public function id(): string;

    /** Klucz katalogu napisów z etykietą strefy środkowej. */
    public function labelKey(): string;

    /**
     * Górny pas klatki — `null`, gdy ekran nie ma czego w nim postawić.
     *
     * Do kroku 20 stała tu bezwarunkowo ścieżka bieżącego katalogu, a ekran mógł
     * co najwyżej dopisać do niej końcówkę (`headerSuffix()`). Szczelina zniknęła
     * razem z powodem swojego istnienia: skoro ekran rysuje cały pas, nie ma czego
     * łatać. Przeglądarka stawia tu ścieżkę wraz z numerem zaznaczenia, pomoc —
     * nazwę i wersję aplikacji, ustawienia — położenie pliku konfiguracyjnego.
     */
    public function header(): ?ScreenZone;

    /**
     * Pas podglądu — `null` zastępuje dawne `usesPreview() === false`.
     *
     * O tym, czy strefa naprawdę powstanie, decyduje jeszcze wysokość okna:
     * `HudLayout` odmawia jej miejsca poniżej swojego progu, tak samo jak przed
     * zmianą.
     */
    public function preview(): ?ScreenZone;

    /**
     * Wiązania klawiszy tego ekranu — źródło podpowiedzi w pasku stanu i spisu
     * w oknie pomocy.
     *
     * @return list<KeyBinding>
     */
    public function bindings(): array;

    public function handle(KeyPress $key): ScreenOutcome;
}
