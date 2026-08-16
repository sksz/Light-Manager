<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use Closure;
use LightManager\Application\Dto\PointerAction;
use LightManager\Application\Dto\PointerEvent;
use LightManager\Application\Ui\Rect;

/**
 * Który panel podziału jest czynny i **gdzie biegnie granica** — trzecia
 * w projekcie klasa pamiętająca coś między klatkami, po `ScrollWindow`
 * (krok 18) i `SectionState` (krok 22).
 *
 * Reguła własności jest ta sama: **komponent jest bezstanowy i powstaje co
 * klatkę**, więc ognisko nie może mieszkać w `Split`. Właścicielem jest ekran,
 * bo to on dostaje klawisze i to on wie, komu je oddać.
 *
 * **Krok 55 dokłada proporcję i jest to jedyna zmiana w stanie, jaką ten krok
 * wnosi poza wejściem.** Do niego granica była **liczona** — `Split::halves()`
 * dzielił prostokąt na pół (albo w proporcji podanej w miejscu wywołania)
 * i nikt nie pamiętał niczego, bo nie było czym tej granicy ruszyć. Odkąd da
 * się ją przeciągnąć, ktoś musi pamiętać, dokąd ją przeciągnięto — a że
 * proporcja przeżywa uruchomienie, ten sam ktoś zamawia jej zapis
 * w ustawieniach modułu (reguła 11c: ustawienia podziału należą do modułu).
 *
 * Zapis pada **po zwolnieniu przycisku, a nie w trakcie przeciągania**, i jest
 * to ta sama reguła, którą krok 37 zapisał dla rozmiaru okna: zapis przy każdym
 * zdarzeniu znaczyłby dziesiątki zapisów pliku na sekundę.
 */
final class SplitState
{
    /** Domyślny podział: po połowie. */
    public const DEFAULT_FRACTION = 0.5;

    /**
     * Granice, poniżej i powyżej których panel przestaje mieć sens.
     *
     * Są **udziałem, a nie liczbą kolumn**, i to nie jest niedopatrzenie:
     * bezwzględne minimum ma już `Split` (`MINIMUM_COLUMNS`, `MINIMUM_ROWS`)
     * i pilnuje nim tego, czy podział w ogóle powstaje. Tutaj chodzi o coś
     * innego — o to, żeby przeciągnięcie do samej krawędzi nie zamieniło
     * podziału w jeden panel z paskiem obok, którego nie da się już odzyskać
     * myszą, bo nie ma czego chwycić.
     */
    public const MINIMUM_FRACTION = 0.2;

    public const MAXIMUM_FRACTION = 0.8;

    /**
     * Ile komórek od granicy jeszcze ją chwyta.
     *
     * Zero — czyli chwytają dokładnie **dwie** kolumny (albo dwa wiersze):
     * ostatnia pierwszego panelu i pierwsza drugiego. Są to dwie stykające się
     * obwódki, czyli dokładnie to, co użytkownik widzi jako kreskę. Szerszy
     * zapas zabierałby kliknięcia treści przy krawędzi panelu.
     */
    private const GRAB_TOLERANCE = 0;

    private bool $second = false;

    private float $fraction;

    private bool $dragging = false;

    /**
     * @param ?Closure(int): void $persist zapis proporcji (w procentach) po
     *                                     zwolnieniu przycisku; `null` znaczy
     *                                     „ta proporcja nie przeżywa uruchomienia”
     */
    public function __construct(
        float $fraction = self::DEFAULT_FRACTION,
        private readonly ?Closure $persist = null,
    ) {
        $this->fraction = self::clamp($fraction);
    }

    /** Czy klawisze idą do drugiego panelu (prawego albo dolnego). */
    public function focusesSecond(): bool
    {
        return $this->second;
    }

    public function moveFocus(): void
    {
        $this->second = !$this->second;
    }

    /** Ognisko postawione wprost — tak działa kliknięcie w panel (krok 55). */
    public function focus(bool $second): void
    {
        $this->second = $second;
    }

    /** Jaka część prostokąta przypada pierwszemu panelowi. */
    public function fraction(): float
    {
        return $this->fraction;
    }

    /**
     * Proporcja podana z zewnątrz — z ustawień modułu, czytanych co klatkę.
     *
     * W trakcie przeciągania **jest pomijana**: ustawienie nie zna jeszcze
     * wartości, którą użytkownik właśnie wskazuje ręką, więc odczytana wprost
     * cofałaby granicę w każdej klatce.
     */
    public function useFraction(float $fraction): void
    {
        if (!$this->dragging) {
            $this->fraction = self::clamp($fraction);
        }
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
            $this->dragging = false;
        }
    }

    public function isDragging(): bool
    {
        return $this->dragging;
    }

    /**
     * Wskaźnik na granicy podziału — **cała obsługa przeciągania w jednym
     * miejscu** (krok 55).
     *
     * Metoda stoi tutaj, a nie w każdym z pięciu ekranów z podziałem, z tego
     * samego powodu, dla którego w kroku 18 powstał `ScrollWindow`: ten sam
     * rachunek przepisany pięć razy rozjedzie się przy pierwszej poprawce.
     *
     * @param Rect $bounds prostokąt, który podział dzieli — ten sam, który
     *                     dostaje `Split::halves()`
     *
     * @return bool czy zdarzenie należało do granicy; `false` znaczy „to nie
     *              moja sprawa, obsłuż je po swojemu”
     */
    public function pointer(PointerEvent $event, Rect $bounds, SplitAxis $axis): bool
    {
        if ($bounds->isEmpty()) {
            return false;
        }

        return match ($event->action) {
            PointerAction::Press => $this->grab($event, $bounds, $axis),
            PointerAction::Drag => $this->dragging && $this->moveTo($event, $bounds, $axis),
            PointerAction::Release => $this->release(),
            default => false,
        };
    }

    /** Pierwsza komórka **drugiego** panelu, czyli miejsce styku obu obwódek. */
    public static function boundary(Rect $bounds, SplitAxis $axis, float $fraction): int
    {
        return $axis === SplitAxis::Vertical
            ? $bounds->column + (int) round($bounds->columns * $fraction)
            : $bounds->row + (int) round($bounds->rows * $fraction);
    }

    private function grab(PointerEvent $event, Rect $bounds, SplitAxis $axis): bool
    {
        $boundary = self::boundary($bounds, $axis, $this->fraction);
        $position = $axis === SplitAxis::Vertical ? $event->column : $event->row;

        // Chwyt obejmuje granicę i komórkę przed nią: dwie stykające się
        // obwódki są dla oka jedną kreską.
        if ($position < $boundary - 1 - self::GRAB_TOLERANCE || $position > $boundary + self::GRAB_TOLERANCE) {
            return false;
        }

        $this->dragging = true;

        return true;
    }

    private function moveTo(PointerEvent $event, Rect $bounds, SplitAxis $axis): bool
    {
        $size = $axis === SplitAxis::Vertical ? $bounds->columns : $bounds->rows;
        $offset = $axis === SplitAxis::Vertical
            ? $event->column - $bounds->column
            : $event->row - $bounds->row;

        $this->fraction = self::clamp($offset / max(1, $size));

        return true;
    }

    private function release(): bool
    {
        if (!$this->dragging) {
            return false;
        }

        $this->dragging = false;

        if ($this->persist !== null) {
            ($this->persist)((int) round($this->fraction * 100));
        }

        return true;
    }

    private static function clamp(float $fraction): float
    {
        return max(self::MINIMUM_FRACTION, min(self::MAXIMUM_FRACTION, $fraction));
    }
}
