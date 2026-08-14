<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Jeden wiersz porównania zrzutów: ile pikseli się różni i co z tego wynika.
 *
 * Różnica jest liczbą **pikseli**, nie procentem uśrednionym po kanałach: cienki
 * obrys zjedzony przez kwantyzator to kilkaset pikseli na sześciuset tysiącach,
 * czyli wielkość, która w mierze średniokwadratowej znika, a w tej jest widoczna
 * (D64, rozstrzygnięcie nr 4).
 */
final class SnapshotDifference
{
    private function __construct(
        public readonly Scenario $scenario,
        public readonly SnapshotVerdict $verdict,
        public readonly int $differingPixels,
        public readonly int $totalPixels,
        public readonly string $baselinePath,
        /** Obraz różnicy — powstaje wyłącznie wtedy, gdy jest co pokazać. */
        public readonly ?string $differencePath = null,
    ) {
    }

    public static function compared(
        Scenario $scenario,
        int $differingPixels,
        int $totalPixels,
        string $baselinePath,
        float $thresholdPerMille,
        ?string $differencePath,
    ): self {
        $exceeded = $differingPixels > $totalPixels * $thresholdPerMille / 1000.0;

        return new self(
            $scenario,
            $exceeded ? SnapshotVerdict::Differs : SnapshotVerdict::Match,
            $differingPixels,
            $totalPixels,
            $baselinePath,
            $exceeded ? $differencePath : null,
        );
    }

    public static function missing(Scenario $scenario, string $baselinePath): self
    {
        return new self($scenario, SnapshotVerdict::Missing, 0, 0, $baselinePath);
    }

    public static function resized(Scenario $scenario, string $baselinePath): self
    {
        return new self($scenario, SnapshotVerdict::Resized, 0, 0, $baselinePath);
    }

    public static function incomparable(Scenario $scenario, string $baselinePath): self
    {
        return new self($scenario, SnapshotVerdict::Incomparable, 0, 0, $baselinePath);
    }

    /** Udział różniących się pikseli w promilach — w procentach same zera. */
    public function perMille(): float
    {
        return $this->totalPixels === 0 ? 0.0 : $this->differingPixels / $this->totalPixels * 1000.0;
    }
}
