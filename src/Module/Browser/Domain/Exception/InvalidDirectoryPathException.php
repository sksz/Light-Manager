<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Domain\Exception;

use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Domain\Exception\DomainException;

final class InvalidDirectoryPathException extends DomainException implements DescribesProblem
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

    public function problemKey(): string
    {
        return 'module.browser.problem.invalidPath';
    }

    public function problemParameters(): array
    {
        return ['path' => $this->path];
    }
}
