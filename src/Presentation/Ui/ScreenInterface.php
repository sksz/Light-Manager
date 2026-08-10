<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use LightManager\Application\Dto\KeyPress;

/**
 * Ekran aplikacji — treść środkowego panelu wraz z obsługą klawiszy.
 *
 * Do kroku 18 ekran był przypadkiem enuma `Screen`, a to, co rysuje i jak
 * reaguje, rozstrzygały dwa `match`-e: jeden w `GameLoop`, drugi
 * w `InputHandler`. Dopisanie ekranu wymagało dotknięcia obu, a przypadek enuma
 * nie należał do nikogo — wiedza o ekranie była rozsypana po pętli.
 *
 * Ekran zajmuje **wyłącznie środkowy panel**. Ścieżka u góry, pasek stanu u dołu
 * i pas podglądu zostają w gestii rdzenia, więc użytkownik nie traci z oczu
 * tego, gdzie jest, niezależnie od tego, czyj ekran patrzy na niego ze środka.
 * Ten podział jest zarazem kontraktem, na którym stanie ekran modułu (krok 20).
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

    /** Czy pas podglądu ma na tym ekranie co pokazywać. */
    public function usesPreview(): bool;

    /**
     * Dopisek do ścieżki w nagłówku — pusty, gdy ekran nie ma nic do dodania.
     *
     * Nagłówek należy do rdzenia i zawsze pokazuje ścieżkę, ale numer
     * zaznaczenia albo znacznik wpisów ukrytych to wiedza konkretnego ekranu.
     * Bez tej szczeliny rdzeń musiałby pytać o nie po nazwie ekranu — czyli
     * wiedzieć, jakie ekrany istnieją.
     */
    public function headerSuffix(): string;

    /**
     * Wiązania klawiszy tego ekranu — źródło podpowiedzi w pasku stanu i spisu
     * w oknie pomocy.
     *
     * @return list<KeyBinding>
     */
    public function bindings(): array;

    public function handle(KeyPress $key): ScreenOutcome;
}
