<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Application\Port;

/**
 * Pytania o pliki utworów: czy plik jest i co można wpisać dalej (krok 45).
 *
 * Port jest **modułowy i wyłącznie czytający**, więc nie podlega wyjątkowi
 * z reguły 15b: tamten dotyczy pisania po dysku, a moduł czytający pliki ma
 * własny port od kroku 29 (`TextPreviewPort` w module opisu pliku). Do
 * przeglądarki moduł audio sięgnąć nie może i nie sięga — moduły się nie znają
 * (reguła 15) — więc katalog przegląda sam, kilkoma linijkami, zamiast poznawać
 * cudze repozytorium wpisów.
 *
 * Rozstrzyganie ścieżki względnej leży po stronie implementacji i jest **tą
 * samą regułą**, którą stosuje odtwarzacz: liczy się od korzenia projektu, bo
 * tam leży katalog `assets/`.
 */
interface TrackFilesPort
{
    /** Czy plik utworu da się dziś znaleźć i przeczytać. */
    public function exists(string $path): bool;

    /**
     * Podpowiedzi do wpisywanej ścieżki: katalogi (z ukośnikiem na końcu)
     * i pliki dźwiękowe pasujące do przedrostka.
     *
     * Liczone **na żądanie**, jak w `browser.jump`: zawartość dysku zmienia się
     * pod ręką użytkownika, więc policzona z góry byłaby kłamstwem.
     *
     * @return list<string>
     */
    public function suggestions(string $prefix): array;
}
