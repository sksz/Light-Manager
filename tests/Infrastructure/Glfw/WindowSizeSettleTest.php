<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Glfw;

use LightManager\Infrastructure\Glfw\WindowSizeSettle;
use PHPUnit\Framework\TestCase;

/**
 * Uspokojenie zmian rozmiaru okna (krok 37) — jedyna część zapamiętywania
 * rozmiaru, którą da się sprawdzić bez otwartego okna, i zarazem ta, w której
 * mieszka cały ciężar rozstrzygnięcia: przeciągnięcie rogu sypie dziesiątkami
 * zdarzeń na sekundę, a plik ma się zapisać **raz**.
 */
final class WindowSizeSettleTest extends TestCase
{
    /** Dowolna chwila na osi czasu — liczy się wyłącznie różnica między odczytami. */
    private const NOW = 1000.0;

    public function testNothingToWaitForWithoutAChange(): void
    {
        $settle = new WindowSizeSettle();

        self::assertFalse($settle->pending());
        self::assertFalse($settle->settled(self::NOW));
    }

    public function testChangeStillFreshDoesNotSettle(): void
    {
        $settle = new WindowSizeSettle();
        $settle->noteChange(self::NOW);

        self::assertTrue($settle->pending());
        self::assertFalse($settle->settled(self::NOW + WindowSizeSettle::SETTLE_SECONDS / 2));
    }

    public function testChangeSettlesOnceTheQuietPeriodPasses(): void
    {
        $settle = new WindowSizeSettle();
        $settle->noteChange(self::NOW);

        self::assertTrue($settle->settled(self::NOW + WindowSizeSettle::SETTLE_SECONDS));
    }

    /**
     * Sedno rozstrzygnięcia: każde kolejne zdarzenie odsuwa ciszę, więc dłuższe
     * przeciąganie rogu **nie zapisuje się po drodze ani razu**.
     */
    public function testEveryFurtherChangePushesTheQuietPeriodAway(): void
    {
        $settle = new WindowSizeSettle();
        $now = self::NOW;

        for ($event = 0; $event < 20; ++$event) {
            $settle->noteChange($now);
            $now += WindowSizeSettle::SETTLE_SECONDS / 10;

            self::assertFalse($settle->settled($now), 'zapis w środku przeciągania rogu');
        }

        self::assertTrue($settle->settled($now + WindowSizeSettle::SETTLE_SECONDS));
    }

    /** Uspokojoną zmianę rozlicza się raz — drugie pytanie w tym samym takcie nie zapisuje ponownie. */
    public function testSettlingClearsTheWait(): void
    {
        $settle = new WindowSizeSettle();
        $settle->noteChange(self::NOW);

        $later = self::NOW + WindowSizeSettle::SETTLE_SECONDS;

        self::assertTrue($settle->settled($later));
        self::assertFalse($settle->settled($later));
        self::assertFalse($settle->pending());
    }

    /** Pełny ekran zmienia rozmiar, którego użytkownik nie wybierał — takie czekanie się porzuca. */
    public function testForgettingDropsTheWaitWithoutSettling(): void
    {
        $settle = new WindowSizeSettle();
        $settle->noteChange(self::NOW);
        $settle->forget();

        self::assertFalse($settle->pending());
        self::assertFalse($settle->settled(self::NOW + WindowSizeSettle::SETTLE_SECONDS));
    }
}
