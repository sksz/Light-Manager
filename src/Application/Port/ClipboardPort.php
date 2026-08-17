<?php

declare(strict_types=1);

namespace LightManager\Application\Port;

/**
 * Schowek środowiska graficznego — zdolność **toru wyjścia**, jak `ViewportPort`
 * i `FrameRendererPort` (krok 57).
 *
 * Port jest rdzeniowy i **nie jest to wyjątek od reguły 15**: moduł nie ma
 * dostępu ani do terminala, ani do okna inaczej niż portem, więc drugiej drogi do
 * schowka nie ma czego rozważać. Dwie implementacje:
 * `Infrastructure\Terminal\TerminalClipboardService` (OSC 52 — wspólna dla toru
 * sixelowego i tekstowego, bo oba rozmawiają z tym samym terminalem) oraz
 * `Infrastructure\Glfw\GlfwClipboardService`.
 *
 * **Metody są dwie i asymetryczne, i to jest treść tego portu.** Położenie tekstu
 * kończy się odpowiedzią „udało się albo nie” w tym samym wywołaniu. Poproszenie
 * o tekst **nie oddaje tekstu** — bo w torze terminalowym nie ma go skąd wziąć:
 * odpowiedź przychodzi zdarzeniem `Application\Dto\ClipboardText` na wejściu
 * aplikacji, klatkę albo dwie później. Metoda nazywa się dlatego `requestText()`,
 * a nie `text()`: nazwa obiecująca zwrot wartości byłaby kłamstwem w jednej
 * z dwóch implementacji, a takie kłamstwo widać dopiero pod terminalem, który
 * odczytu nie obsługuje.
 */
interface ClipboardPort
{
    /**
     * Kładzie tekst w schowku środowiska graficznego.
     *
     * @return ?string klucz katalogu napisów z powodem odmowy albo `null`, gdy
     *                 się udało. Wyjątek nie przekracza granicy portu (reguła 8):
     *                 treść za długa dla protokołu jest **zwykłym stanem**,
     *                 o którym trzeba powiedzieć zdaniem — a nie awarią. Ciche
     *                 obcięcie jest tu wykluczone z założenia: kopiowanie, które
     *                 oddaje połowę zawartości, jest gorsze od kopiowania, które
     *                 odmawia.
     */
    public function put(string $text): ?string;

    /**
     * Prosi o zawartość schowka — **wyłącznie na wyraźne polecenie użytkownika**
     * (`Alt`+`v` albo komenda `core.clipboard.paste`).
     *
     * Nigdy przy starcie, nigdy w takcie, nigdy „na wszelki wypadek”: pierwsze
     * z trzech zobowiązań, na które zamieniła się cena rozstrzygnięcia D95 nr 5 —
     * odblokowanie `GetSelection` w `bin/run.sh` pozwala aplikacji **przeczytać
     * cudzy schowek**.
     *
     * @return bool czy prośbę udało się wysłać. `false` znaczy „nie ma czym
     *              zapytać” i wołający mówi wtedy zdaniem; `true` **nie
     *              obiecuje odpowiedzi** — terminal, który OSC 52 nie obsługuje,
     *              milczy, więc prośba ma termin i wygasa.
     */
    public function requestText(): bool;
}
