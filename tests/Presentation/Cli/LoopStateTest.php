<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Cli;

use LightManager\Application\Dto\Settings;
use LightManager\Domain\Aggregate\Directory;
use LightManager\Domain\ValueObject\DirectoryPath;
use LightManager\Domain\ValueObject\Entry;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Component\Dialog;
use LightManager\Presentation\Ui\Overlay\MessageOverlay;
use PHPUnit\Framework\TestCase;

final class LoopStateTest extends TestCase
{
    private LoopState $state;

    protected function setUp(): void
    {
        $this->state = new LoopState(
            new Directory(new DirectoryPath('/start'), [Entry::file('alfa.txt', 0)]),
        );
    }

    public function testStartsEmpty(): void
    {
        self::assertNull($this->state->message());
        self::assertNull($this->state->overlays()->current());
        self::assertFalse($this->state->showsHiddenEntries());
        self::assertSame('/start', $this->state->directory()->path()->value);
    }

    public function testEnteringDirectoryKeepsPreviousProblemVisible(): void
    {
        $this->state->reportProblem('coś poszło nie tak', 0.0);

        $this->state->enterDirectory(new Directory(new DirectoryPath('/inny'), []));

        self::assertSame('/inny', $this->state->directory()->path()->value);
        // Samo wejście do katalogu komunikatu nie gasi — robi to dopiero klawisz
        // naciśnięty po upływie czasu na przeczytanie.
        self::assertNotNull($this->state->message());
    }

    /** Widoczność ukrytych nie jest już osobnym znacznikiem — czyta ją z ustawień. */
    public function testHiddenEntriesFlagComesFromSettings(): void
    {
        self::assertFalse($this->state->showsHiddenEntries());

        $this->state->applySettings((new Settings())->withShowHiddenEntries(true));

        self::assertTrue($this->state->showsHiddenEntries());
    }

    public function testOpensAndClosesTheModalWindow(): void
    {
        $this->state->overlays()->open(new MessageOverlay(new Dialog('alfa.txt', ['ASCII text'])));

        self::assertNotNull($this->state->overlays()->current());

        $this->state->overlays()->close();

        self::assertNull($this->state->overlays()->current());
    }
}
