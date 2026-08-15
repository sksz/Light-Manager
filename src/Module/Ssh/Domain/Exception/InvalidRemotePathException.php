<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Domain\Exception;

use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Domain\Exception\DomainException;

/**
 * Ścieżka zdalna, której nie da się użyć (krok 49).
 *
 * Wyjątek **przedstawia się sam** (`DescribesProblem`) z tego samego powodu, co
 * wyjątek profilu hosta: rdzeń nie wie, czym jest ścieżka na cudzej maszynie.
 *
 * Rzuca **wyłącznie samowalidacja obiektu wartości** — czyli miejsce, w którym
 * ścieżka powstaje z napisu podanego przez człowieka albo wczytanego z pliku
 * stanu. Odczyt katalogu nie rzuca nigdy (reguła 8): niepowodzenie sieci wraca
 * kluczem w stanie pracy, bo zdarza się rutynowo.
 */
final class InvalidRemotePathException extends DomainException implements DescribesProblem
{
    /** @param array<string, string> $problemParameters */
    private function __construct(
        string $message,
        private readonly string $problemKey,
        private readonly array $problemParameters,
    ) {
        parent::__construct($message);
    }

    public static function forEmptyPath(): self
    {
        return new self('Remote path is empty.', 'module.ssh.path.empty', []);
    }

    public static function forRelativePath(string $path): self
    {
        return new self(
            sprintf('Remote path "%s" is not absolute.', $path),
            'module.ssh.path.relative',
            ['path' => $path],
        );
    }

    public function problemKey(): string
    {
        return $this->problemKey;
    }

    public function problemParameters(): array
    {
        return $this->problemParameters;
    }
}
