<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Infrastructure\Support\AbstractSingleton;
use ReflectionProperty;

/**
 * Reset instancji Singletonów wyłącznie na potrzeby testów — klasy produkcyjne
 * celowo nie mają publicznego API do zerowania stanu.
 */
trait ResetsSingletons
{
    /** @param class-string<AbstractSingleton> $singletonClass */
    protected function resetSingleton(string $singletonClass): void
    {
        $property = new ReflectionProperty(AbstractSingleton::class, 'instances');

        /** @var array<class-string, AbstractSingleton> $instances */
        $instances = $property->getValue();
        unset($instances[$singletonClass]);

        $property->setValue(null, $instances);
    }
}
