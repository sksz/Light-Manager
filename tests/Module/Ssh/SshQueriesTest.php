<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Ssh;

use LightManager\Application\Query\QueryRegistry;
use LightManager\Module\Ssh\Presentation\SshQueries;
use PHPUnit\Framework\TestCase;

/**
 * Odczyt wpisów z **cudzej** książki (krok 60).
 *
 * Sprawdza zdanie graniczne reguły 15g: **moduł pytający musi umieć żyć bez
 * odpowiedzi**, bo ten drugi bywa wyłączony, odrzucony albo nieobecny. Książki
 * adresowej nie da się dziś odrzucić (nie deklaruje `RequiresEnvironment`), ale
 * **da się ją wyłączyć w ustawieniach** — a wtedy moduł sesji zdalnej ma
 * pokazać pusty spis, a nie wywrócić klatkę.
 */
final class SshQueriesTest extends TestCase
{
    public function testWithoutTheBookTheHostListIsEmptyInsteadOfBroken(): void
    {
        $reader = new SshQueries(new QueryRegistry());

        self::assertSame([], $reader->hosts());
        self::assertNull($reader->entry('a1b2c3d4e5f6'));
        self::assertNull($reader->keyPath('a1b2c3d4e5f6'));
        self::assertSame('', $reader->lastAddedEntry());
    }

    /** Rozdział, którym ten moduł opisuje wpis, jest **jego identyfikatorem**. */
    public function testTheChapterIsTheModuleIdentifier(): void
    {
        self::assertSame('ssh', SshQueries::CHAPTER);
    }
}
