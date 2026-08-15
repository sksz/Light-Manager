<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Browser\Application\Undo;

use LightManager\Module\Browser\Application\Undo\UndoEntry;
use LightManager\Module\Browser\Application\Undo\UndoJournal;
use PHPUnit\Framework\TestCase;

/**
 * Stos cofnięć: pamięć operacji wraz z regułami głębokości i odwracalności
 * (krok 44, D81 nr 6–8).
 */
final class UndoJournalTest extends TestCase
{
    public function testEntriesComeBackNewestFirst(): void
    {
        $journal = new UndoJournal(fn (): int => 10);

        $journal->record(UndoEntry::renamed('/dom', 'a', 'b'));
        $journal->record(UndoEntry::directoryCreated('/dom', 'nowy'));

        $entries = $journal->entries();

        self::assertCount(2, $entries);
        self::assertSame('nowy', $entries[0]->names[0], 'najnowszy pierwszy');
        self::assertSame('b', $entries[1]->names[0]);
    }

    /** Głębokość pyta się przy każdym zapisie — zmiana ustawienia działa od następnej operacji. */
    public function testTheDepthTrimsTheOldestEntries(): void
    {
        $depth = 3;
        $journal = new UndoJournal(function () use (&$depth): int {
            return $depth;
        });

        foreach (['a', 'b', 'c', 'd'] as $name) {
            $journal->record(UndoEntry::directoryCreated('/dom', $name));
        }

        self::assertCount(3, $journal->entries());
        self::assertSame('d', $journal->entries()[0]->names[0]);
        self::assertSame('b', $journal->entries()[2]->names[0], 'najstarszy wypadł');

        $depth = 1;
        $journal->record(UndoEntry::directoryCreated('/dom', 'e'));

        self::assertCount(1, $journal->entries(), 'zawężona głębokość przycina przy zapisie');
    }

    /** Klawisz cofania bierze najnowszą operację **odwracalną** — nieodwracalne przeskakuje. */
    public function testLatestReversibleSkipsIrreversibleEntries(): void
    {
        $journal = new UndoJournal(fn (): int => 10);

        $journal->record(UndoEntry::renamed('/dom', 'a', 'b'));
        $journal->record(UndoEntry::deletedPermanently('/dom', ['c'], 1));
        $journal->record(UndoEntry::copied('/dom', ['d']));

        $index = $journal->latestReversibleIndex();

        self::assertSame(2, $index);
        self::assertSame('b', $journal->at(2)?->names[0]);
    }

    public function testAnEmptyJournalHasNothingReversible(): void
    {
        $journal = new UndoJournal(fn (): int => 10);

        self::assertTrue($journal->isEmpty());
        self::assertNull($journal->latestReversibleIndex());

        $journal->record(UndoEntry::copied('/dom', ['a']));

        self::assertFalse($journal->isEmpty(), 'nieodwracalna też jest historią');
        self::assertNull($journal->latestReversibleIndex());
    }

    /** Zdejmowanie i wymiana idą po tożsamości wpisu, nie po numerze — numer zmienia każdy zapis. */
    public function testDropAndReplaceWorkByIdentity(): void
    {
        $journal = new UndoJournal(fn (): int => 10);
        $rename = UndoEntry::renamed('/dom', 'a', 'b');

        $journal->record($rename);
        $journal->record(UndoEntry::directoryCreated('/dom', 'nowy'));

        $journal->drop($rename);

        self::assertCount(1, $journal->entries());
        self::assertSame('nowy', $journal->entries()[0]->names[0]);

        $trash = UndoEntry::trashed('/dom', ['x' => 'x', 'y' => 'y'], '/kosz');
        $journal->record($trash);
        $journal->replace($trash, $trash->withTrashNames(['y' => 'y']));

        self::assertSame(['y'], $journal->entries()[0]->names, 'wymiana pomniejsza wpis o przywrócone');
    }

    /** Spis odwracalnych mieszka w `reversible()` — i mówi dokładnie to, co plan kroku. */
    public function testTheReversibilityListMatchesThePlan(): void
    {
        self::assertTrue(UndoEntry::renamed('/d', 'a', 'b')->reversible());
        self::assertTrue(UndoEntry::directoryCreated('/d', 'a')->reversible());
        self::assertTrue(UndoEntry::trashed('/d', ['a' => 'a'], '/k')->reversible());
        self::assertTrue(UndoEntry::moved('/d', '/e', ['a'])->reversible());
        self::assertFalse(UndoEntry::copied('/d', ['a'])->reversible(), 'cofnięciem kopiowania byłoby usunięcie');
        self::assertFalse(UndoEntry::deletedPermanently('/d', ['a'], 1)->reversible());
    }
}
