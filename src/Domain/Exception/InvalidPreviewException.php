<?php

declare(strict_types=1);

namespace LightManager\Domain\Exception;

final class InvalidPreviewException extends DomainException
{
    public static function forEmptyCaption(): self
    {
        return new self('A preview needs a non-empty caption.');
    }

    public static function forRelativePath(string $path): self
    {
        return new self(sprintf('A preview needs an absolute path, got "%s".', $path));
    }
}
