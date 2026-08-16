<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

use LightManager\Application\Dto\PointerAction;
use LightManager\Application\Dto\PointerButton;
use LightManager\Application\Dto\PointerEvent;

/**
 * Rozpoznanie pary kliknięć (krok 55).
 *
 * Jest to **jedyna rzecz w kroku 55 pytająca o czas**, więc stoi w jednym
 * miejscu — obok `InputHandler` — a nie w każdym ekranie z osobna. Zegar
 * przychodzi z zewnątrz (`LoopState::now()`), tą samą drogą, którą dostaje go
 * karetka i pasek postępu: klasa z własnym `microtime()` przestaje być
 * testowalna (reguła 11b).
 *
 * Przeciągnięcia tu nie ma i to nie jest brak: oba tory oddają je **gotowe** —
 * terminal bitem ruchu w sekwencji SGR, okno stanem przycisku sprawdzanym
 * w wywołaniu zwrotnym. Rozpoznawać nie ma czego; pamiętać, co przeciągnięcie
 * chwyciło, musi ten, kto to coś rysuje, i robi to `SplitState`.
 */
final class PointerGestures
{
    /**
     * Ile czasu ma drugie kliknięcie na to, by było drugim.
     *
     * 400 ms to próg spotykany w interfejsach graficznych od czasów, gdy
     * ustawiało się go suwakiem; poniżej 250 ms para wymyka się ręce, powyżej
     * pół sekundy dwa osobne kliknięcia zaczynają się sklejać.
     */
    private const DOUBLE_CLICK_SECONDS = 0.4;

    private ?float $lastPressAt = null;

    private int $lastRow = -1;

    private int $lastColumn = -1;

    /**
     * Czy to naciśnięcie domyka parę.
     *
     * Warunki są trzy i wszystkie obowiązkowe: lewy przycisk, naciśnięcie
     * (nie zwolnienie), **ta sama komórka**. Ostatni jest tym, który odróżnia
     * podwójne kliknięcie od dwóch szybkich kliknięć w różne wiersze listy —
     * bez niego szybko klikający użytkownik wchodziłby do katalogów, których
     * nie wybrał.
     *
     * Trzecie kliknięcie z rzędu **nie jest** drugim podwójnym: para, która się
     * domknęła, gasi pamięć. Inaczej potrójne kliknięcie znaczyłoby `Enter`
     * dwa razy.
     */
    public function isDoubleClick(PointerEvent $event, float $now): bool
    {
        if ($event->action !== PointerAction::Press || $event->button !== PointerButton::Left) {
            return false;
        }

        $double = $this->lastPressAt !== null
            && $now - $this->lastPressAt <= self::DOUBLE_CLICK_SECONDS
            && $this->lastRow === $event->row
            && $this->lastColumn === $event->column;

        $this->lastPressAt = $double ? null : $now;
        $this->lastRow = $event->row;
        $this->lastColumn = $event->column;

        return $double;
    }
}
