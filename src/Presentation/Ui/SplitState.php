<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

/**
 * Który panel podziału jest czynny — trzecia w projekcie klasa pamiętająca coś
 * między klatkami, po `ScrollWindow` (krok 18) i `SectionState` (krok 22).
 *
 * Reguła własności jest ta sama: **komponent jest bezstanowy i powstaje co
 * klatkę**, więc ognisko nie może mieszkać w `Split`. Właścicielem jest ekran,
 * bo to on dostaje klawisze i to on wie, komu je oddać.
 *
 * Klasa jest cienka i to nie jest przeoczenie — trzyma jedną wartość, ale
 * **wraz z regułą, która się o nią potyka**: podział bywa wyłączony, a ognisko
 * stojące wtedy na nieistniejącym panelu kierowałoby klawisze w próżnię. Reguła
 * ma jedno miejsce, zanim drugi moduł napisze ją po swojemu — dokładnie ten błąd,
 * który `ScrollWindow` zastał w kroku 18 w trzech wariantach naraz.
 */
final class SplitState
{
    private bool $second = false;

    /** Czy klawisze idą do drugiego panelu (prawego albo dolnego). */
    public function focusesSecond(): bool
    {
        return $this->second;
    }

    public function moveFocus(): void
    {
        $this->second = !$this->second;
    }

    /**
     * Podział jest włączony albo nie — a gdy nie jest, ognisko wraca na pierwszy
     * panel. Bez tego wyłączenie podziału przy ognisku po prawej zostawiałoby
     * klawisze przy panelu, którego nie ma na ekranie.
     */
    public function useSplit(bool $enabled): void
    {
        if (!$enabled) {
            $this->second = false;
        }
    }
}
