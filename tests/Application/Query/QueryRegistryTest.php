<?php

declare(strict_types=1);

namespace LightManager\Tests\Application\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryRegistry;
use LightManager\Tests\Support\CountingQuery;
use LightManager\Tests\Support\StubQueryModule;
use PHPUnit\Framework\TestCase;

/**
 * Rejestr kwerend — przestrzeń nazw, brak wykonawcy, zakaz zagnieżdżania
 * i **routing z pamięcią pokoleń** (krok 53).
 *
 * Pamięć sprawdza się **licznikiem wywołań w atrapie**, nigdy zegarem: test
 * mierzący czas mówiłby o maszynie, a nie o tym, czy wynik został przeliczony.
 */
final class QueryRegistryTest extends TestCase
{
    public function testNameOutsideTheOwnersNamespaceIsRejectedWithAReason(): void
    {
        $registry = new QueryRegistry();
        $registry->add('browser', [new CountingQuery('audio.playlist')]);

        self::assertSame([], $registry->all(), 'cudza przestrzeń nazw nie wchodzi');
        self::assertCount(1, $registry->rejections());
        self::assertSame('query.rejected.namespace', $registry->rejections()[0]->reasonKey);
    }

    public function testTheSameNameTwiceIsRejectedWithAReason(): void
    {
        $registry = new QueryRegistry();
        $registry->add('core', [new CountingQuery('core.settings'), new CountingQuery('core.settings')]);

        self::assertCount(1, $registry->all());
        self::assertSame('query.rejected.duplicate', $registry->rejections()[0]->reasonKey);
    }

    /** Brak wykonawcy jest **zwykłym stanem**: wynik z powodem, nie wyjątek. */
    public function testAskingForSomethingNobodyProvidesGivesAReason(): void
    {
        $result = (new QueryRegistry())->ask('docker.images');

        self::assertTrue($result->hasProblem());
        self::assertSame('query.problem.unknown', $result->problem);
        self::assertSame([], $result->rows());
    }

    public function testTheSameGenerationIsAnsweredFromMemory(): void
    {
        $query = new CountingQuery('core.settings');
        $registry = new QueryRegistry();
        $registry->add('core', [$query]);

        $registry->ask('core.settings');
        $registry->ask('core.settings');
        $registry->ask('core.settings');

        self::assertSame(1, $query->asked, 'niezmienione źródło liczy się raz');
    }

    public function testANewGenerationIsAnsweredAfresh(): void
    {
        $query = new CountingQuery('core.settings');
        $registry = new QueryRegistry();
        $registry->add('core', [$query]);

        $registry->ask('core.settings');
        $query->generation = 7;
        $registry->ask('core.settings');

        self::assertSame(2, $query->asked);
    }

    /** Różne argumenty to różne pytania, więc i różne wpisy w pamięci. */
    public function testDifferentArgumentsAreDifferentQuestions(): void
    {
        $query = new CountingQuery('browser.entries');
        $registry = new QueryRegistry();
        $registry->add('browser', [$query]);

        $registry->ask('browser.entries', new CommandInput(['pane' => '0']));
        $registry->ask('browser.entries', new CommandInput(['pane' => '1']));
        $registry->ask('browser.entries', new CommandInput(['pane' => '0']));

        self::assertSame(2, $query->asked);
    }

    /**
     * Kwerenda ulotna **nie jest pamiętana w ogóle** — i to jest poprawka, którą
     * wymusił test przełącznika muzyki: odpowiedź zapamiętana na klatkę oddawała
     * stan sprzed zmiany, która padła w tej samej klatce.
     */
    public function testAVolatileQueryIsAskedEveryTime(): void
    {
        $query = CountingQuery::volatile('core.jobs');
        $registry = new QueryRegistry();
        $registry->add('core', [$query]);

        $registry->ask('core.jobs');
        $registry->ask('core.jobs');

        self::assertSame(2, $query->asked);
    }

    /** Kwerenda wołająca kwerendę zostaje odmówiona, a nie zapętla pętli. */
    public function testAQueryMayNotAskAQuery(): void
    {
        $registry = new QueryRegistry();
        $inner = new CountingQuery('core.inner');
        $registry->add('core', [$inner, CountingQuery::asking('core.outer', $registry, 'core.inner')]);

        $result = $registry->ask('core.outer');

        self::assertSame('query.problem.nested', $result->problem);
        self::assertSame(0, $inner->asked, 'zagnieżdżona nie została wykonana');
    }

    /** Wyjątek kwerendy ginie w rejestrze — klatka nie ma dokąd go zgłosić. */
    public function testAThrowingQueryBecomesAReasonInsteadOfAnException(): void
    {
        $registry = new QueryRegistry();
        $registry->add('core', [CountingQuery::throwing('core.broken')]);

        $result = $registry->ask('core.broken');

        self::assertSame('query.problem.failed', $result->problem);
    }

    /** Kwerendy wnosi wyłącznie moduł, który o tym powiedział. */
    public function testOnlyModulesDeclaringTheCapabilityBringQueries(): void
    {
        $registry = new QueryRegistry();
        $registry->useModules([
            new StubQueryModule('audio', [new CountingQuery('audio.playlist')]),
            new StubQueryModule('mute', []),
        ]);

        self::assertCount(1, $registry->all());
        self::assertNotNull($registry->find('audio.playlist'));
    }

    public function testMatchingAndCommonPrefixWorkLikeInTheCommandRegistry(): void
    {
        $registry = new QueryRegistry();
        $registry->add('core', [new CountingQuery('core.settings'), new CountingQuery('core.status')]);

        self::assertCount(2, $registry->matching('core.s'));
        self::assertSame('core.s', $registry->commonPrefix('core.'));
    }
}
