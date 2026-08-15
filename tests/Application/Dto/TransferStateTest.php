<?php

declare(strict_types=1);

namespace LightManager\Tests\Application\Dto;

use LightManager\Application\Dto\TransferStage;
use LightManager\Application\Dto\TransferState;
use PHPUnit\Framework\TestCase;

/**
 * Stan pracy jest **daną, nie procesem** (D46), więc sprawdza się go jak daną:
 * co znaczy każdy etap i co niesie po nim.
 */
final class TransferStateTest extends TestCase
{
    public function testCountingKnowsWhatItFoundButNotTheWhole(): void
    {
        $state = TransferState::scanning(7, 900, 'plik.txt');

        self::assertSame(TransferStage::Scanning, $state->stage);
        self::assertTrue($state->isRunning());
        self::assertNull($state->totalBytes, 'przy liczeniu całości jeszcze nie znamy');
        self::assertSame(7, $state->doneEntries, 'przy liczeniu „zrobione” znaczy „znalezione”');
        self::assertSame('plik.txt', $state->current);
    }

    public function testCollidingStopsTheWorkAndCarriesTheName(): void
    {
        $state = TransferState::colliding('zajęty.txt', 100, 400, 1, 4);

        self::assertFalse($state->isRunning(), 'praca stoi i czeka na odpowiedź');
        self::assertSame('zajęty.txt', $state->current);
        self::assertSame(100, $state->doneBytes);
    }

    /**
     * Praca przerwana jest pracą **zakończoną** (D66), więc etap ma ten sam —
     * rozróżnia je licznik, a nie siódmy stan do obsłużenia.
     */
    public function testStoppedEarlyIsToldApartByTheCounterAloneNotByTheStage(): void
    {
        $whole = TransferState::done(400, 400, 4, 4);
        $stopped = TransferState::done(100, 400, 1, 4);

        self::assertSame(TransferStage::Done, $whole->stage);
        self::assertSame(TransferStage::Done, $stopped->stage);
        self::assertFalse($whole->wasStoppedEarly());
        self::assertTrue($stopped->wasStoppedEarly());
    }

    public function testFailureRemembersHowMuchGotDone(): void
    {
        $state = TransferState::failed('problem.transfer.unreadable', ['name' => 'x'], 128, 400, 1, 4);

        self::assertSame(TransferStage::Failed, $state->stage);
        self::assertFalse($state->isRunning());
        self::assertSame('problem.transfer.unreadable', $state->problemKey);
        self::assertSame(['name' => 'x'], $state->problemParameters);
        self::assertSame(128, $state->doneBytes, 'po niepowodzeniu użytkownik musi wiedzieć, co jest w celu');
    }

    public function testIdleCarriesNothing(): void
    {
        $state = TransferState::idle();

        self::assertSame(TransferStage::Idle, $state->stage);
        self::assertFalse($state->isRunning());
        self::assertSame('', $state->current);
        self::assertSame(0, $state->doneEntries);
    }
}
