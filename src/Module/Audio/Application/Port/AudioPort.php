<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Application\Port;

/**
 * Odtwarzanie muzyki — **poza ścieżką klatki** (krok 36).
 *
 * Kontrakt jest krótki z tego samego powodu, dla którego moduł nie ma ekranu:
 * muzyka nie jest tu funkcją do oglądania, tylko czymś, co gra obok. Silnik
 * miksuje we własnym wątku, więc pętla główna, renderery i komponenty **nie
 * dowiadują się, że cokolwiek gra** — i to jest miara powodzenia tego kroku,
 * nie jego skutek uboczny.
 *
 * Port mieszka w warstwie `Application` **modułu**, a nie rdzenia: dźwięk jest
 * funkcją dopisaną modułem (reguła 15), więc rdzeń nie ma o nim wiedzieć nawet
 * tyle, ile mieści się w interfejsie.
 *
 * Żadna metoda nie rzuca. Brak rozszerzenia, brak pliku i błąd dekodowania to
 * **zwykłe stany**, o których port mówi opisem — dokładnie jak `SettingsPort`
 * mówi o nieudanym zapisie. Wyjątek infrastruktury nie przekracza granicy portu.
 *
 * Głośność jest w procentach, a nie w ułamku, bo w procentach mówi pozycja
 * ustawień i komenda; przeliczenie na to, czego chce silnik, należy do
 * implementacji.
 */
interface AudioPort
{
    /**
     * Czy w tym środowisku da się w ogóle zagrać.
     *
     * `false` znaczy „brak rozszerzenia `glfw`” i jest odpowiedzią pełnoprawną:
     * aplikacja działa wtedy jak przed tym krokiem, a komendy muzyczne mówią,
     * czego brakuje.
     */
    public function isAvailable(): bool;

    /**
     * Zaczyna albo wznawia grę.
     *
     * Wznawia, gdy ten sam utwór został wcześniej zatrzymany — bo zatrzymanie
     * w silniku jest pauzą, nie przewinięciem (sprawdzone na starcie kroku).
     * Utwór inny niż wczytany wchodzi od początku.
     *
     * @param string $path  ścieżka pliku (WAV/MP3/FLAC — formaty miniaudio)
     * @param int    $volume głośność w procentach, 0–100
     *
     * @return string|null opis problemu, gdy zagrać się nie udało; `null`, gdy gra
     */
    public function play(string $path, int $volume, bool $loop): ?string;

    /** Pauzuje. Wolno wołać zawsze, także gdy nic nie gra. */
    public function stop(): void;

    public function isPlaying(): bool;

    /** Zmiana głośności obowiązuje **natychmiast**, także w trakcie grania. */
    public function useVolume(int $volume): void;

    /**
     * Zatrzymuje silnik i zwalnia jego wątek — sprzątanie przy wyjściu
     * z aplikacji.
     */
    public function shutdown(): void;
}
