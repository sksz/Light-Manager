<?php

declare(strict_types=1);

namespace LightManager\Application\Module;

/**
 * Moduł — miejsce, w którym dopisuje się funkcję, nie dotykając rdzenia.
 *
 * Baza jest **sama tożsamość**: kim moduł jest, jak się nazywa i czym się go
 * otwiera. Wszystko, co moduł naprawdę wnosi — ekran, zakładka ustawień,
 * zakładka pomocy, komendy — jest osobną zdolnością, deklarowaną przez
 * implementację interfejsu (P17 planu kroku 20). Moduł bez ani jednej zdolności
 * jest legalny; jest wtedy pustą deklaracją i nic złego z tego nie wynika.
 *
 * Zdolności rozkładają się na dwie warstwy, a granicą jest to, czy interfejs
 * wymienia typ z `Presentation`: `ProvidesSettingsTab` i `ProvidesCommands`
 * mówią wyłącznie danymi i zostają tutaj, a `ProvidesScreen`, `ProvidesHelpTab`
 * i `ReadsContext` leżą w `Presentation\Ui\Module` (P2).
 *
 * `version()` w kontrakcie **nie ma** (P6): moduły są wbudowane w repozytorium,
 * więc ich wersja zawsze równałaby się wersji aplikacji. Pole wraca wtedy, gdy
 * moduły staną się zewnętrzne i naprawdę będą mogły się z nią rozjechać.
 */
interface ModuleInterface
{
    /**
     * Identyfikator: `[a-z][a-z0-9-]*`.
     *
     * Jeden napis pełni trzy role naraz — jest kluczem w pliku konfiguracyjnym
     * (`modules.<id>`), przedrostkiem napisów (`module.<id>.`) i przestrzenią
     * nazw komend (`<id>.`). Kształt pilnuje `ModuleRegistry`, a moduł
     * z identyfikatorem niezgodnym ze wzorcem nie zostaje wpuszczony.
     */
    public function id(): string;

    /** Klucz katalogu napisów z nazwą modułu — nie sam napis (krok 15). */
    public function nameKey(): string;

    /** Klucz katalogu z jednym zdaniem: po co ten moduł istnieje. */
    public function descriptionKey(): string;

    /** Skrót otwierający ekran modułu; `null`, gdy moduł ekranu nie wnosi. */
    public function shortcut(): ?ModuleShortcut;

    /**
     * Katalog z plikami napisów modułu (`pl.php`, `en.php`); `null`, gdy moduł
     * własnych napisów nie niesie.
     *
     * Ścieżka na dysku w warstwie `Application` jest **daną, nie typem** —
     * reguła zależności zostaje nietknięta, a alternatywy były gorsze: konwencja
     * kazałaby `Bootstrapowi` wyprowadzać położenie plików z nazwy klasy przez
     * refleksję, a osobna zdolność w `Presentation` trafiłaby tam wbrew
     * kryterium podziału z P2 (nie wymienia ani jednego typu tej warstwy).
     */
    public function translations(): ?string;
}
