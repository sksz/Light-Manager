<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Support;

use LogicException;

/**
 * Wszystkie usługi-Singletony dziedziczą po tej klasie. Konstruktor jest
 * `protected` (nie `private`), bo współdzielona `getInstance()` musi móc go
 * wywołać przez `new static()`; z zewnątrz hierarchii blokada `new` działa
 * tak samo.
 *
 * @phpstan-consistent-constructor klasy pochodne mogą nadpisać konstruktor,
 * ale nie wolno im dodać parametrów — `getInstance()` nie miałaby ich skąd wziąć.
 */
abstract class AbstractSingleton
{
    /** @var array<class-string, self> */
    private static array $instances = [];

    protected function __construct()
    {
    }

    final public static function getInstance(): static
    {
        $instance = self::$instances[static::class] ??= new static();

        /** @var static $instance */
        return $instance;
    }

    final public function __clone(): void
    {
        throw new LogicException(static::class . ' is a singleton and cannot be cloned.');
    }

    final public function __wakeup(): void
    {
        throw new LogicException(static::class . ' is a singleton and cannot be unserialized.');
    }

    /** @return list<string> */
    final public function __sleep(): array
    {
        throw new LogicException(static::class . ' is a singleton and cannot be serialized.');
    }
}
