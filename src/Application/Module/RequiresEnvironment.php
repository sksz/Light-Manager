<?php

declare(strict_types=1);

namespace LightManager\Application\Module;

/**
 * Moduł, który bez czegoś spoza aplikacji **nie ma czym działać** (krok 48, D87
 * nr 11).
 *
 * Rejestr odrzucał do tej pory z czterech powodów i wszystkie dotyczyły samej
 * deklaracji: zły identyfikator, identyfikator zajęty, litera spoza dozwolonych,
 * litera zajęta. Wszystkie cztery są **błędami autora modułu** i w wydanej
 * aplikacji nie zdarzają się nigdy. Ta zdolność wnosi powód piąty i pierwszy
 * zależny od maszyny, na której aplikacja akurat stoi.
 *
 * **Dlaczego to nie jest droga modułu dźwięku.** Krok 36 rozwiązał brak
 * `ext-glfw` inaczej — portem z dwiema implementacjami, z których druga jest
 * pustym obiektem (reguła 11o) — i tamta droga zostaje właściwa **wszędzie tam,
 * gdzie po odjęciu rzeczy brakującej coś jeszcze zostaje**. Muzyka bez silnika
 * to cisza: moduł nadal ma komendy, zakładkę i sens. Sesja zdalna bez klienta
 * `ssh` nie ma nic — spis hostów, których nie da się użyć, jest gorszy od braku
 * spisu, bo obiecuje. Różnica jest w tym, czy pusty obiekt ma co udawać.
 *
 * Zdolność leży w `Application/Module`, jak `NeedsTick` i `ProvidesCommands` —
 * nie wymienia ani jednego typu z `Presentation` (P2), bo powód jest **kluczem
 * katalogu napisów**, a nie napisem ani komponentem.
 *
 * Dwie reguły, obie wynikłe z tego, **kiedy** pada pytanie. `admit()` dzieje się
 * raz, w `Bootstrapie`, w ścieżce uruchomienia aplikacji:
 *
 * - **odpowiedź musi być tania** — `command -v`, `is_file()`, `extension_loaded()`;
 *   nigdy odpytanie sieci, nigdy uruchomienie programu, którego się szuka;
 * - **odpowiedź pada raz na uruchomienie** i tyle samo jest warta przez cały
 *   jego czas. Program doinstalowany przy działającej aplikacji zostanie
 *   zauważony po jej ponownym uruchomieniu — i to jest cena przyjęta świadomie,
 *   bo sprawdzanie co klatkę kosztowałoby `stat()` trzydzieści razy na sekundę
 *   za coś, co zmienia się raz na miesiąc.
 */
interface RequiresEnvironment
{
    /**
     * Czego brakuje — albo `null`, gdy niczego.
     *
     * @return string|null klucz katalogu napisów z powodem; **klucz, nie napis**
     *                     (krok 15), bo rejestr leży w `Application` i katalogu
     *                     nie widzi. Klucz należy do przestrzeni modułu
     *                     (`module.<id>.…`), jak każdy inny jego napis
     */
    public function unavailableReason(): ?string;
}
