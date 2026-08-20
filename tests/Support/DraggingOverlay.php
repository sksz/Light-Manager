<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Presentation\Ui\DragsOwnContentInOverlay;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * Okno nakładane prowadzące **własne** przeciągnięcie — atrapa deklarująca
 * `DragsOwnContentInOverlay` (krok 77).
 *
 * Powód, dla którego stoi w `tests/Support`, a nie w `src/`, jest zapisany
 * w D106 nr 2 i jest to świadomy wybór: zdolność weszła **bez deklarującego
 * w aplikacji** (trzeci jawny wyjątek od reguły 13), a przeciągnięcie dołożone
 * prawdziwemu oknu po to, żeby interfejs miał użytkownika, byłoby funkcją bez
 * użytkownika — czyli tą samą regułą złamaną z drugiej strony. Atrapa **nie
 * jest** tym brakującym odbiorcą i nie należy jej za niego brać; sprawdza
 * wyłącznie, że rdzeń pyta o zdolność i że odpowiedź twierdząca zabiera
 * przeciągnięcie zaznaczaniu.
 */
final class DraggingOverlay implements OverlayInterface, DragsOwnContentInOverlay
{
    public function __construct(
        private bool $dragging = true,
    ) {
    }

    public function id(): string
    {
        return 'dragging';
    }

    public function bounds(int $rows, int $columns): Rect
    {
        return new Rect(0, 0, min(4, $rows), min(20, $columns));
    }

    public function bindings(): array
    {
        return [];
    }

    public function handle(KeyPress $key): OverlayOutcome
    {
        return OverlayOutcome::ignored();
    }

    /** @return list<Primitive> */
    public function draw(Rect $bounds): array
    {
        return [];
    }

    public function isDraggingOwn(): bool
    {
        return $this->dragging;
    }

    public function stopDragging(): void
    {
        $this->dragging = false;
    }
}
