<?php

declare(strict_types=1);

namespace LightManager\Domain\ValueObject;

use LightManager\Domain\Exception\InvalidDirectoryPathException;

/**
 * Bezwzględna, uporządkowana ścieżka katalogu.
 *
 * Porządkowanie jest czysto tekstowe (`.` i `..` są rozwijane, powtórzone
 * ukośniki znikają) — sprawdzenie, czy katalog istnieje i daje się odczytać,
 * należy do repozytorium, nie do obiektu wartości.
 */
final class DirectoryPath
{
    public readonly string $value;

    public function __construct(string $path)
    {
        if (!str_starts_with($path, '/')) {
            throw InvalidDirectoryPathException::forPath($path);
        }

        $this->value = self::normalize($path);
    }

    public static function root(): self
    {
        return new self('/');
    }

    public function isRoot(): bool
    {
        return $this->value === '/';
    }

    /** `null` dla korzenia — wyżej już nie ma dokąd iść. */
    public function parent(): ?self
    {
        if ($this->isRoot()) {
            return null;
        }

        return new self(substr($this->value, 0, (int) strrpos($this->value, '/')) ?: '/');
    }

    public function child(string $name): self
    {
        return new self($this->isRoot() ? '/' . $name : $this->value . '/' . $name);
    }

    /** Nazwa samego katalogu, bez ścieżki. Dla korzenia — `/`. */
    public function name(): string
    {
        if ($this->isRoot()) {
            return '/';
        }

        return substr($this->value, (int) strrpos($this->value, '/') + 1);
    }

    /**
     * Ścieżka przycięta do zadanej liczby znaków, skracana **od lewej**: ogon
     * mówi więcej niż korzeń drzewa, bo to w nim siedzi katalog, w którym
     * użytkownik właśnie stoi.
     */
    public function shortenedTo(int $columns): string
    {
        if ($columns < 1 || mb_strlen($this->value) <= $columns) {
            return $this->value;
        }

        return '…' . mb_substr($this->value, -($columns - 1));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private static function normalize(string $path): string
    {
        $resolved = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($resolved);

                continue;
            }

            $resolved[] = $segment;
        }

        return $resolved === [] ? '/' : '/' . implode('/', $resolved);
    }
}
