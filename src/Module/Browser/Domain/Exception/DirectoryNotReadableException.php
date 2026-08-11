<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Domain\Exception;

use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Domain\Exception\DomainException;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;

/**
 * Najczęstszy wyjątek widoczny dla użytkownika: katalog bez prawa odczytu albo
 * zniknięty spod nóg. Ścieżka jest tu polem, bo `Presentation` wstawia ją do
 * przetłumaczonego komunikatu.
 *
 * Wyjątki modułu dziedziczą dalej po **rdzeniowym** `DomainException` (krok 21):
 * korzeń hierarchii zostaje w rdzeniu, bo to on ją łapie — `InputHandler` stawia
 * komunikat z wyjątku domenowego w pasku stanu i ma to robić niezależnie od tego,
 * czyj wyjątek złapał. Poza tym korzeniem domena modułu nie widzi domeny rdzenia.
 */
final class DirectoryNotReadableException extends DomainException implements DescribesProblem
{
    private function __construct(
        public readonly string $path,
    ) {
        parent::__construct(sprintf('Directory "%s" cannot be read.', $path));
    }

    public static function forPath(DirectoryPath $path): self
    {
        return new self($path->value);
    }

    public function problemKey(): string
    {
        return 'module.browser.problem.unreadable';
    }

    public function problemParameters(): array
    {
        return ['path' => $this->path];
    }
}
