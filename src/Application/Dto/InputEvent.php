<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * Cokolwiek, co przyszło z wejścia — klawisz albo wskaźnik (krok 55, D95 nr 1).
 *
 * Interfejs jest **znacznikowy** i to nie jest niedbałość: klawisz i wskaźnik
 * nie mają ani jednego wspólnego pytania, na które oba umiałyby odpowiedzieć.
 * `KeyPress` niesie znak i trzy modyfikatory, `PointerEvent` — komórkę, przycisk
 * i rodzaj czynności; wspólna metoda musiałaby więc albo kłamać przy jednym
 * z nich, albo oddawać `null` przy 99% naciśnięć. Wspólny jest wyłącznie
 * **kanał**, i dokładnie to ten typ nazywa.
 *
 * Powodem jego istnienia jest **jedna kolejka**. Wskaźnik mógł wejść drugą
 * metodą portu (`readPointer()`), taniej i bez ruszania sygnatury — ale wtedy
 * kolejność kliknięcia wobec klawisza rozstrzygałaby kolejność pytań w takcie,
 * a nie kolejność zdarzeń u użytkownika. Kliknięcie stawiające ognisko i litera
 * wpisana zaraz po nim mogłyby się w jednym takcie wyminąć.
 */
interface InputEvent
{
}
