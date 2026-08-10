<?php

declare(strict_types=1);

namespace LightManager\Domain\Exception;

final class InvalidDirectoryPathException extends DomainException
{
    private function __construct(
        public readonly string $path,
    ) {
        parent::__construct(sprintf('"%s" is not an absolute directory path.', $path));
    }

    public static function forPath(string $path): self
    {
        return new self($path);
    }
}
