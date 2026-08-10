<?php

declare(strict_types=1);

namespace LightManager\Tests\Domain\Aggregate;

use LightManager\Domain\Aggregate\Directory;
use LightManager\Domain\Exception\InvalidSelectionException;
use LightManager\Domain\ValueObject\DirectoryPath;
use LightManager\Domain\ValueObject\Entry;
use LightManager\Domain\ValueObject\Selection;
use PHPUnit\Framework\TestCase;

final class DirectoryTest extends TestCase
{
    public function testSelectsFirstEntryByDefault(): void
    {
        $directory = $this->directoryWith('alfa.txt', 'beta.txt');

        $selected = $directory->selectedEntry();

        self::assertNotNull($selected);
        self::assertSame('alfa.txt', $selected->name);
    }

    public function testEmptyDirectoryHasNoSelection(): void
    {
        $directory = new Directory(new DirectoryPath('/pusty'), []);

        self::assertTrue($directory->isEmpty());
        self::assertNull($directory->selection());
        self::assertNull($directory->selectedEntry());
    }

    public function testRejectsSelectionBeyondEntryCount(): void
    {
        $this->expectException(InvalidSelectionException::class);

        new Directory(new DirectoryPath('/katalog'), [Entry::file('alfa.txt', 0)], new Selection(1));
    }

    public function testMovesSelectionDownAndUp(): void
    {
        $directory = $this->directoryWith('alfa.txt', 'beta.txt', 'gamma.txt');

        $directory->moveSelectionDown();
        $directory->moveSelectionDown();

        self::assertSame('gamma.txt', $directory->selectedEntry()?->name);

        $directory->moveSelectionUp();

        self::assertSame('beta.txt', $directory->selectedEntry()?->name);
    }

    public function testStopsAtTheTopOfTheList(): void
    {
        $directory = $this->directoryWith('alfa.txt', 'beta.txt');

        $directory->moveSelectionUp();
        $directory->moveSelectionUp();

        self::assertSame('alfa.txt', $directory->selectedEntry()?->name);
    }

    public function testStopsAtTheBottomOfTheList(): void
    {
        $directory = $this->directoryWith('alfa.txt', 'beta.txt');

        $directory->moveSelectionDown();
        $directory->moveSelectionDown();
        $directory->moveSelectionDown();

        self::assertSame('beta.txt', $directory->selectedEntry()?->name);
    }

    public function testMovingSelectionInEmptyDirectoryDoesNothing(): void
    {
        $directory = new Directory(new DirectoryPath('/pusty'), []);

        $directory->moveSelectionDown();
        $directory->moveSelectionUp();

        self::assertNull($directory->selection());
    }

    public function testSelectsEntryByName(): void
    {
        $directory = $this->directoryWith('alfa.txt', 'beta.txt', 'gamma.txt');

        $directory->selectEntryNamed('gamma.txt');

        self::assertSame('gamma.txt', $directory->selectedEntry()?->name);
    }

    public function testFallsBackToFirstEntryWhenNameIsGone(): void
    {
        $directory = $this->directoryWith('alfa.txt', 'beta.txt');
        $directory->moveSelectionDown();

        $directory->selectEntryNamed('nie-ma-takiego');

        self::assertSame('alfa.txt', $directory->selectedEntry()?->name);
    }

    public function testSelectingByNameInEmptyDirectoryLeavesNoSelection(): void
    {
        $directory = new Directory(new DirectoryPath('/pusty'), []);

        $directory->selectEntryNamed('cokolwiek');

        self::assertNull($directory->selection());
    }

    public function testComparesByPathOnly(): void
    {
        $first = new Directory(new DirectoryPath('/katalog'), [Entry::file('alfa.txt', 0)]);
        $second = new Directory(new DirectoryPath('/katalog'), [Entry::file('inny.txt', 5)]);
        $third = new Directory(new DirectoryPath('/inny'), [Entry::file('alfa.txt', 0)]);

        self::assertTrue($first->equals($second));
        self::assertFalse($first->equals($third));
    }

    private function directoryWith(string ...$names): Directory
    {
        return new Directory(
            new DirectoryPath('/katalog'),
            array_values(array_map(static fn (string $name): Entry => Entry::file($name, 0), $names)),
        );
    }
}
