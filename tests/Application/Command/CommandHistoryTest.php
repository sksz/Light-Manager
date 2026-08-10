<?php

declare(strict_types=1);

namespace LightManager\Tests\Application\Command;

use LightManager\Application\Command\CommandHistory;
use LightManager\Tests\Support\InMemoryCommandHistory;
use PHPUnit\Framework\TestCase;

final class CommandHistoryTest extends TestCase
{
    public function testStartsFromWhatWasSavedBefore(): void
    {
        $history = new CommandHistory(new InMemoryCommandHistory(['core.help', 'core.quit']));

        self::assertSame(['core.quit', 'core.help'], $history->entries(), 'najnowsze na górze');
    }

    public function testKeepsOnlyTheNewestEntriesOnLoad(): void
    {
        $stored = array_map(static fn (int $index): string => 'core.help ' . $index, range(1, 30));
        $history = new CommandHistory(new InMemoryCommandHistory($stored));

        self::assertCount(CommandHistory::CAPACITY, $history->entries());
        self::assertSame('core.help 30', $history->entries()[0]);
    }

    public function testRepeatedLineMovesToTheTopInsteadOfDoublingUp(): void
    {
        $history = new CommandHistory(new InMemoryCommandHistory());
        $history->remember('core.help');
        $history->remember('core.quit');
        $history->remember('core.help');

        self::assertSame(['core.help', 'core.quit'], $history->entries());
    }

    public function testBlankLineIsNotRemembered(): void
    {
        $history = new CommandHistory(new InMemoryCommandHistory());
        $history->remember('   ');

        self::assertSame([], $history->entries());
    }

    public function testWritesOnceTheBufferIsFullAndNotBefore(): void
    {
        $port = new InMemoryCommandHistory();
        $history = new CommandHistory($port);

        for ($entry = 1; $entry < CommandHistory::CAPACITY; ++$entry) {
            $history->remember('core.help ' . $entry);
        }

        self::assertSame(0, $port->saves, 'niepełny bufor nie dotyka dysku');

        $history->remember('core.help ' . CommandHistory::CAPACITY);

        self::assertSame(1, $port->saves);
        self::assertCount(CommandHistory::CAPACITY, $port->entries);
    }

    public function testFlushWritesWhatTheBufferHolds(): void
    {
        $port = new InMemoryCommandHistory();
        $history = new CommandHistory($port);
        $history->remember('core.help');
        $history->flush();

        self::assertSame(1, $port->saves);
        self::assertSame(['core.help'], $port->entries);
    }

    public function testFlushWithNothingNewDoesNotTouchTheFile(): void
    {
        $port = new InMemoryCommandHistory(['core.help']);
        (new CommandHistory($port))->flush();

        self::assertSame(0, $port->saves);
    }
}
