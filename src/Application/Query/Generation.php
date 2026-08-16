<?php

declare(strict_types=1);

namespace LightManager\Application\Query;

/**
 * Licznik pokoleń dla źródła, które własnego licznika nie prowadzi.
 *
 * Rejestr kwerend pyta o `generation()` przed każdym odczytem i przelicza wynik
 * wyłącznie po zmianie tej liczby — ale większość źródeł rdzenia numeru wersji
 * nie ma. Mają za to **tani znacznik**: obiekt ustawień wymieniany przy każdej
 * zmianie, nazwa motywu, liczba wierszy terminala. Ta klasa zamienia taki
 * znacznik w liczbę rosnącą.
 *
 * Porównanie jest **tożsamościowe** (`!==`), więc dla obiektów kosztuje jedno
 * porównanie wskaźników, a nie przejście po polach — i to jest cały powód, dla
 * którego wolno je wołać w klatce. Warunek, pod którym to działa: obiekt musi
 * być **wymieniany przy zmianie**, a nie zmieniany w miejscu. Wszystkie trzy
 * dzisiejsze źródła tak robią (`Settings`, `ModuleContext`, `Message`).
 */
final class Generation
{
    private int $value = 0;

    private object|string|int|float|bool|null $stamp = null;

    private bool $seen = false;

    /** Numer pokolenia dla podanego znacznika — rośnie, gdy znacznik się zmienił. */
    public function of(object|string|int|float|bool|null $stamp): int
    {
        if (!$this->seen || $stamp !== $this->stamp) {
            $this->seen = true;
            $this->stamp = $stamp;
            ++$this->value;
        }

        return $this->value;
    }
}
