<?php

declare(strict_types=1);

namespace LightManager\Tests\Application\Dto;

use LightManager\Application\Dto\RemovalStage;
use LightManager\Application\Dto\RemovalState;
use LightManager\Application\Dto\WorkProgress;
use PHPUnit\Framework\TestCase;

/**
 * Stan usuwania i jego przekład na język okna (krok 41).
 *
 * Sedno: **`Ready` nie jest pracą w toku**. Praca staje na przystanku i czeka na
 * odpowiedź na pytanie „usunąć tyle wpisów?”, więc okno postępu ma się wtedy
 * zamknąć, a nie odliczać dalej.
 */
final class RemovalStateTest extends TestCase
{
    public function testScanningDoesNotKnowTheTotalYet(): void
    {
        $state = RemovalState::scanning(12, 'plik.txt');

        self::assertSame(RemovalStage::Scanning, $state->stage);
        self::assertSame(12, $state->done);
        self::assertNull($state->total);
        self::assertTrue($state->isRunning());
        self::assertNull($state->progress()->fraction(), 'bez całości nie ma paska');
    }

    public function testReadyIsAStopNotWork(): void
    {
        $state = RemovalState::ready(30);

        self::assertFalse($state->isRunning());
        self::assertSame(30, $state->total);
        self::assertFalse($state->progress()->running);
    }

    public function testDeletingCarriesBothNumbersAndTheFraction(): void
    {
        $progress = RemovalState::deleting(15, 30, 'plik.txt')->progress();

        self::assertTrue($progress->running);
        self::assertSame('plik.txt', $progress->current);
        self::assertSame(0.5, $progress->fraction());
    }

    public function testFailureKeepsTheCountOfWhatWasAlreadyDeleted(): void
    {
        $state = RemovalState::failed('problem.fileops.denied', ['name' => 'x'], 7, 30);

        self::assertSame(RemovalStage::Failed, $state->stage);
        self::assertFalse($state->isRunning());
        self::assertSame(7, $state->done, 'po przerwanym usuwaniu trzeba wiedzieć, ile zniknęło');
        self::assertSame(['name' => 'x'], $state->problemParameters);
    }

    public function testDoneMeansEverything(): void
    {
        $state = RemovalState::done(30);

        self::assertSame(30, $state->done);
        self::assertSame(1.0, $state->progress()->fraction());
    }

    /** Praca bez ani jednego wpisu nie ma czego pokazać paskiem — dzielenia przez zero też nie ma. */
    public function testAnEmptyWorkHasNoFraction(): void
    {
        self::assertNull((new WorkProgress(true, '', 0, 0))->fraction());
        self::assertNull(WorkProgress::idle()->fraction());
    }

    /** Ułamek jest przycinany, bo liczba usuniętych nie ma prawa przekroczyć całości. */
    public function testTheFractionStaysInRange(): void
    {
        self::assertSame(1.0, (new WorkProgress(true, '', 40, 30))->fraction());
    }
}
