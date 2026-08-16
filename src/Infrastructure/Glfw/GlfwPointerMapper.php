<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Glfw;

use LightManager\Application\Dto\PointerAction;
use LightManager\Application\Dto\PointerButton;
use LightManager\Application\Dto\PointerEvent;

/**
 * Zamienia zdarzenia wskaźnika GLFW na słownik `PointerEvent` — odpowiednik
 * `GlfwKeyMapper` dla myszy i, jak on, klasa **czysta**: bez jednego wywołania
 * GLFW, bez okna i bez stanu, testowalna w PHPUnit przez samo mapowanie
 * (krok 55, wzorem kroku 44).
 *
 * Położenia kursora tu nie ma i to nie jest przeoczenie: GLFW podaje je
 * **osobnym** wywołaniem zwrotnym, a zdarzenia przycisku i kółka przychodzą
 * bez współrzędnych. Ostatnie znane położenie pamięta więc `GlfwInputService`,
 * a tutaj mieszka wyłącznie rachunek, który da się sprawdzić liczbami.
 *
 * Piksele przychodzą we współrzędnych **okna**, a komórkę liczy się metryką
 * framebuffera — te dwie miary rozjeżdżają się dopiero przy skali treści innej
 * niż 1.0, której projekt świadomie nie stosuje (reguła 11l: skala jest czytana
 * i pokazywana w pomocy, a nie stosowana). Gdyby kiedyś zaczął, to jest jedno
 * z dwóch miejsc do przeliczenia — drugim jest `GlfwWindowService::showAtGrid()`.
 */
final class GlfwPointerMapper
{
    /**
     * Piksele → komórka siatki znakowej.
     *
     * Ten sam rachunek, którym `GlfwViewportService::cells()` liczy rozmiar
     * okna, tyle że dzieli położenie, a nie rozmiar — i dlatego **nie ma tu
     * `max(1, …)`**: pierwsza komórka ma numer zero, a nie jeden.
     *
     * @return array{row: int, column: int}
     */
    public function cell(float $x, float $y, int $cellWidthPixels, int $cellHeightPixels): array
    {
        return [
            'row' => max(0, (int) ($y / max(1, $cellHeightPixels))),
            'column' => max(0, (int) ($x / max(1, $cellWidthPixels))),
        ];
    }

    /**
     * Naciśnięcie albo zwolnienie przycisku; `null` dla przycisków, których
     * słownik nie zna (boczne — reguła 13).
     *
     * Zwolnienie rozpoznaje się przez **zaprzeczenie** `GLFW_PRESS`, a nie przez
     * `GLFW_RELEASE`: tę drugą stałą stuby `phpgl/ide-stubs` definiują błędnie
     * (reguła 11g), więc kod jej nie dotyka. `GLFW_REPEAT` przy myszy nie
     * powstaje — przytrzymany przycisk nie powtarza się jak klawisz.
     *
     * @param array{row: int, column: int} $cell ostatnie znane położenie kursora
     */
    public function mapButton(int $button, int $action, int $mods, array $cell): ?PointerEvent
    {
        $mapped = $this->button($button);

        if ($mapped === null) {
            return null;
        }

        return new PointerEvent(
            $cell['row'],
            $cell['column'],
            $mapped,
            $action === GLFW_PRESS ? PointerAction::Press : PointerAction::Release,
            ...$this->modifiers($mods),
        );
    }

    /**
     * Ruch kursora — **wyłącznie przy wciśniętym przycisku**.
     *
     * `null` przy przycisku podniesionym i to jest cała treść zgodności z torem
     * terminalowym: tam ruch bez przycisku nie przychodzi w ogóle, bo
     * raportowanie włącza się trybem `1002`, a nie `1003`. Zachowanie ma być
     * **takie samo**, a nie podobne — inaczej okno zalewałoby pętlę zdarzeniami,
     * których terminal nie wysyła, i jedno z dwóch trzeba by potem odsiewać.
     *
     * @param array{row: int, column: int} $cell
     */
    public function mapMotion(?PointerButton $held, int $mods, array $cell): ?PointerEvent
    {
        if ($held === null) {
            return null;
        }

        return new PointerEvent(
            $cell['row'],
            $cell['column'],
            $held,
            PointerAction::Drag,
            ...$this->modifiers($mods),
        );
    }

    /**
     * Obrót kółka. Oś pozioma (`$xOffset`) nie ma odbiorcy, więc nie tworzy
     * zdarzenia — tak samo, jak w torze terminalowym.
     *
     * @param array{row: int, column: int} $cell
     */
    public function mapScroll(float $yOffset, int $mods, array $cell): ?PointerEvent
    {
        if ($yOffset === 0.0) {
            return null;
        }

        return new PointerEvent(
            $cell['row'],
            $cell['column'],
            PointerButton::Left,
            $yOffset > 0.0 ? PointerAction::ScrollUp : PointerAction::ScrollDown,
            ...$this->modifiers($mods),
        );
    }

    /** Przycisk GLFW → słownik; `null` dla bocznych. */
    public function button(int $button): ?PointerButton
    {
        return match ($button) {
            GLFW_MOUSE_BUTTON_LEFT => PointerButton::Left,
            GLFW_MOUSE_BUTTON_RIGHT => PointerButton::Right,
            GLFW_MOUSE_BUTTON_MIDDLE => PointerButton::Middle,
            default => null,
        };
    }

    /**
     * Trzy znaczniki w kolejności, w jakiej stoją w konstruktorze `PointerEvent`.
     *
     * Rozłączności z reguły 11j **tu nie ma i nie ma jej gdzie zastosować**:
     * tamta reguła mówi o literach i klawiszach nazwanych, a wskaźnik nie ma
     * ani jednego, ani drugiego. Protokoły obu torów podają wszystkie trzy
     * niezależnie i tak też się je przepisuje.
     *
     * @return array{bool, bool, bool}
     */
    private function modifiers(int $mods): array
    {
        return [
            ($mods & GLFW_MOD_CONTROL) !== 0,
            ($mods & GLFW_MOD_ALT) !== 0,
            ($mods & GLFW_MOD_SHIFT) !== 0,
        ];
    }
}
