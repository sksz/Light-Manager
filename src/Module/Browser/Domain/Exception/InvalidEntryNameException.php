<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Domain\Exception;

use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Domain\Exception\DomainException;

/**
 * Nazwa wpisana przez użytkownika, której nie da się nadać (krok 41).
 *
 * Wyjątek **przedstawia się sam** (`DescribesProblem`, D42), bo mówi o dziedzinie
 * modułu: rdzeń nie wie, czym jest nazwa wpisu, i nie ma prawa dobierać dla niej
 * zdania po klasie wyjątku.
 *
 * Powód jest osobnym zdaniem dla każdego przypadku, a nie jednym „zła nazwa”:
 * użytkownik, który wpisał ukośnik, ma zobaczyć, że chodzi o ukośnik, a nie
 * zgadywać, co jest nie tak.
 */
final class InvalidEntryNameException extends DomainException implements DescribesProblem
{
    /** @param array<string, string> $problemParameters */
    private function __construct(
        string $message,
        private readonly string $problemKey,
        private readonly array $problemParameters,
    ) {
        parent::__construct($message);
    }

    public static function empty(): self
    {
        return new self('Entry name is empty.', 'module.browser.name.empty', []);
    }

    /** `.` i `..` należą do systemu plików i nadać ich nie sposób. */
    public static function reserved(string $name): self
    {
        return new self(
            sprintf('Entry name "%s" is reserved.', $name),
            'module.browser.name.reserved',
            ['name' => $name],
        );
    }

    /** Ukośnik w nazwie znaczy „katalog piętro niżej”, a okno pyta o nazwę, nie o ścieżkę. */
    public static function separator(string $name): self
    {
        return new self(
            sprintf('Entry name "%s" contains a path separator.', $name),
            'module.browser.name.separator',
            ['name' => $name],
        );
    }

    public static function tooLong(string $name, int $limit): self
    {
        return new self(
            sprintf('Entry name "%s" is longer than %d bytes.', $name, $limit),
            'module.browser.name.tooLong',
            ['limit' => (string) $limit],
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
