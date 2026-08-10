<?php

declare(strict_types=1);

namespace LightManager\Domain\Exception;

final class InvalidMessageException extends DomainException
{
    public static function forEmptyText(): self
    {
        return new self('A message needs non-empty text.');
    }
}
