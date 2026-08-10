<?php

declare(strict_types=1);

namespace LightManager\Domain\Exception;

final class InvalidFrameException extends DomainException
{
    public static function forEmptyTitle(): self
    {
        return new self('A frame needs a non-empty title.');
    }
}
