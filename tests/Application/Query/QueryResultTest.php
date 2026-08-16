<?php

declare(strict_types=1);

namespace LightManager\Tests\Application\Query;

use LightManager\Application\Query\QueryResult;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Odpowiedź kwerendy: **wiersze dla obcych, ładunek dla właściciela**, a jedno
 * i drugie liczone leniwie (krok 53, D92 nr 4).
 */
final class QueryResultTest extends TestCase
{
    /** Ładunek dostaje **wyłącznie** ten, kto się pod kwerendą podpisał. */
    public function testThePayloadIsHandedOnlyToItsOwner(): void
    {
        $payload = new stdClass();
        $result = QueryResult::owned('browser', $payload, static fn (): array => []);

        self::assertSame($payload, $result->payloadFor('browser'));
        self::assertNull($result->payloadFor('k8s'), 'cudzy ładunek nie wychodzi');
        self::assertNull($result->payloadFor(''), 'pusty właściciel to też nie ten właściciel');
    }

    public function testAResultWithoutAPayloadHandsNothingToAnyone(): void
    {
        self::assertNull(QueryResult::of([['name' => 'a']])->payloadFor('browser'));
        self::assertNull(QueryResult::empty()->payloadFor('browser'));
    }

    /**
     * **Wiersze budują się raz i dopiero na żądanie** — na tym stoi cały rachunek
     * wydajności tego kroku: właściciel czytający ładunek nie płaci za tablice,
     * których nikt nie obejrzy.
     */
    public function testRowsAreBuiltLazilyAndOnlyOnce(): void
    {
        $built = 0;
        $result = QueryResult::lazy(static function () use (&$built): array {
            ++$built;

            return [['name' => 'a'], ['name' => 'b']];
        });

        self::assertSame(0, $built, 'sama odpowiedź nie buduje wierszy');

        $result->rows();
        $result->rows();
        $result->isEmpty();

        self::assertSame(1, $built);
        self::assertSame([['name' => 'a'], ['name' => 'b']], $result->rows());
    }

    public function testASingleValueIsASingleRowWithASingleField(): void
    {
        $result = QueryResult::value('path', '/home');

        self::assertSame(['path' => '/home'], $result->first());
        self::assertFalse($result->hasProblem());
    }

    /** Powód niepowodzenia jest **kluczem katalogu**, a nie napisem dla oka. */
    public function testAFailureCarriesAKeyAndNoRows(): void
    {
        $result = QueryResult::failed('query.problem.unknown');

        self::assertTrue($result->hasProblem());
        self::assertSame('query.problem.unknown', $result->problem);
        self::assertTrue($result->isEmpty());
        self::assertNull($result->first());
    }
}
