<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Domain\Exception;

use LightManager\Domain\Exception\DomainException;

final class InvalidSelectionException extends DomainException
{
    public static function forNegativeIndex(int $index): self
    {
        return new self(sprintf('The selection index cannot be negative (%d).', $index));
    }

    public static function outOfRange(int $index, int $entryCount): self
    {
        return new self(sprintf(
            'The selection points at entry %d, but the directory has %d.',
            $index,
            $entryCount,
        ));
    }
}
