<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

/**
 * Miejsce, na którym stoi ognisko — nazwa i klawisze, które w nim działają.
 *
 * Dana, a nie komponent, i to jest cała treść rozstrzygnięcia nr 1 kroku 40.
 * Aplikacja **nie ma zachowanego drzewa komponentów**: komponent powstaje
 * w `draw()` i ginie razem z klatką, a wszystko, co przeżywa takt, mieszka obok
 * niego (`ScrollWindow`, `SectionState`, `SplitState`, `TreeState`). Ogniska nie
 * da się więc odkryć, chodząc po drzewie — trzeba je **zadeklarować**, a jedynym,
 * kto wie, gdzie stoi, jest ekran.
 *
 * Prawdziwi właściciele ogniska komponentami nie są: `BrowserPanes` trzyma
 * numer panelu, `SettingsCursor` numer pozycji, `SplitState` samą stronę
 * podziału. Gdyby kontrakt oddawał `FocusableInterface`, każdy z nich musiałby
 * dorobić `draw()` i `handle()` wyłącznie po to, żeby pasek stanu miał się kogo
 * spytać — czyli udawać coś, czym nie jest.
 *
 * Etykieta jest **kluczem katalogu**, nie napisem, tą samą zasadą co
 * `ScreenZone::labelKey`: ekran nazywa miejsce, ale go nie tłumaczy.
 */
final class FocusHint
{
    /**
     * @param string          $labelKey klucz katalogu napisów z nazwą miejsca
     *                                  („Podgląd”, „Panel lewy”)
     * @param list<KeyBinding> $bindings klawisze działające w tym miejscu — te
     *                                   same obiekty, które ekran oddaje
     *                                   w `bindings()`, a nie ich kopie
     */
    public function __construct(
        public readonly string $labelKey,
        public readonly array $bindings,
    ) {
    }
}
