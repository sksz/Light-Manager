<?php

declare(strict_types=1);

namespace LightManager\Domain\ValueObject;

use LightManager\Domain\Exception\InvalidSelectionException;

final class Selection
{
    public function __construct(
        public readonly int $index,
    ) {
        if ($index < 0) {
            throw InvalidSelectionException::forNegativeIndex($index);
        }
    }

    public function equals(self $other): bool
    {
        return $this->index === $other->index;
    }
}
