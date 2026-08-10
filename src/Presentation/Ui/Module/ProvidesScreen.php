<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Module;

use LightManager\Presentation\Ui\ScreenInterface;

/**
 * Moduł, który wnosi własne okno — ekran zajmujący środkowy panel.
 *
 * Zdolność leży w `Presentation`, bo wymienia `ScreenInterface` (P2). Gdyby
 * stała w `Application`, interfejs opisany w warstwie wewnętrznej sięgałby po
 * klasę z warstwy leżącej **na zewnątrz** niego — strzałka w złą stronę.
 *
 * „Własne okno” i „przejęcie listy plików” to **jedna zdolność, nie dwie**.
 * Ekran z definicji zajmuje ten sam panel, w którym normalnie stoi lista plików,
 * więc każdy ekran modułu już zastępuje jej treść. Jedyna różnica między oknem
 * niezależnym a alternatywnym widokiem katalogu polega na tym, czy ekran chce
 * dostawać kontekst — a to mówi osobno, przez `ReadsContext`.
 *
 * Ekran modułu odpowiada za wszystko wewnątrz panelu: treść, zaznaczenie,
 * przewijanie i klawisze. Rdzeń nie zakłada, że zaznaczenie w ogóle istnieje.
 * Poza panelem nie zmienia się nic — ścieżka u góry, pasek stanu u dołu i pas
 * podglądu zostają w gestii rdzenia (kontrakt `ScreenInterface` z kroku 18).
 */
interface ProvidesScreen
{
    public function screen(): ScreenInterface;
}
