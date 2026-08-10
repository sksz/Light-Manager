<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Support;

use LightManager\Tests\Infrastructure\Support\Fixture\AlphaSingleton;
use LightManager\Tests\Infrastructure\Support\Fixture\BetaSingleton;
use LightManager\Tests\Support\ResetsSingletons;
use LogicException;
use PHPUnit\Framework\TestCase;

final class AbstractSingletonTest extends TestCase
{
    use ResetsSingletons;

    protected function tearDown(): void
    {
        $this->resetSingleton(AlphaSingleton::class);
        $this->resetSingleton(BetaSingleton::class);
    }

    public function testReturnsTheSameInstanceOnEveryCall(): void
    {
        self::assertSame(AlphaSingleton::getInstance(), AlphaSingleton::getInstance());
    }

    public function testEachClassHasItsOwnInstance(): void
    {
        self::assertNotSame(AlphaSingleton::getInstance(), BetaSingleton::getInstance());
    }

    public function testResettingOneClassDoesNotAffectAnother(): void
    {
        $alpha = AlphaSingleton::getInstance();
        $beta = BetaSingleton::getInstance();

        $this->resetSingleton(AlphaSingleton::class);

        self::assertNotSame($alpha, AlphaSingleton::getInstance());
        self::assertSame($beta, BetaSingleton::getInstance());
    }

    public function testCloningIsBlocked(): void
    {
        $instance = AlphaSingleton::getInstance();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(AlphaSingleton::class . ' is a singleton and cannot be cloned.');

        $clone = clone $instance;

        self::fail('Klonowanie zwróciło ' . $clone::class . ' zamiast rzucić wyjątek.');
    }

    public function testSerializationIsBlocked(): void
    {
        $instance = AlphaSingleton::getInstance();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot be serialized');

        serialize($instance);
    }

    public function testDeserializationIsBlocked(): void
    {
        $class = AlphaSingleton::class;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot be unserialized');

        unserialize(sprintf('O:%d:"%s":0:{}', strlen($class), $class));
    }
}
