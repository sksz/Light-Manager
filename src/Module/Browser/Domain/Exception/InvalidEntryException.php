<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Domain\Exception;

use LightManager\Domain\Exception\DomainException;

final class InvalidEntryException extends DomainException
{
    public static function forName(string $name): self
    {
        return new self(sprintf('"%s" is not a valid directory entry name.', $name));
    }

    public static function forNegativeSize(string $name, int $size): self
    {
        return new self(sprintf('Entry "%s" has a negative size (%d bytes).', $name, $size));
    }
}
