<?php

declare(strict_types=1);

namespace LightManager\Domain\Exception;

final class InvalidScrollPositionException extends DomainException
{
    public static function forNegativeValue(string $name, int $value): self
    {
        return new self(sprintf('Scroll position: "%s" cannot be negative, got %d.', $name, $value));
    }

    public static function forWindowOutsideList(int $first, int $visible, int $total): self
    {
        return new self(sprintf(
            'Window %d..%d does not fit a list of %d entries.',
            $first,
            $first + $visible,
            $total,
        ));
    }
}
