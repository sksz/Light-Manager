<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Domain\ValueObject;

use LightManager\Module\Docker\Domain\Exception\InvalidContainerIdException;

/**
 * Tożsamość kontenera (krok 51).
 *
 * Obiekt wartości istnieje tu z jednego, konkretnego powodu i nie jest nim
 * porządek: **identyfikator wchodzi do ścieżki żądania HTTP**
 * (`/containers/{id}/logs`), więc napis niesprawdzony byłby wejściem cudzej
 * treści w adres. Docker nadaje identyfikatory szesnastkowe, więc granica jest
 * ostra i tania: same cyfry i litery `a`–`f`.
 *
 * Skrót do dwunastu znaków jest tym samym skrótem, który pokazuje `docker ps`,
 * i tym samym, którego demon nadal używa jako klucza — prefiks wystarczy, dopóki
 * jest jednoznaczny. Pokazujemy więc skrót, a pytamy pełnym.
 */
final readonly class ContainerId
{
    /** Tyle znaków pokazuje `docker ps` — i tyle wystarcza oku do rozróżnienia. */
    public const SHORT_LENGTH = 12;

    private function __construct(public string $value)
    {
    }

    public static function of(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidContainerIdException::forEmptyId();
        }

        if (preg_match('/^[0-9a-f]{4,64}$/', $trimmed) !== 1) {
            throw InvalidContainerIdException::forMalformedId($trimmed);
        }

        return new self($trimmed);
    }

    /** Postać widoczna na liście — pierwsze dwanaście znaków. */
    public function short(): string
    {
        return substr($this->value, 0, self::SHORT_LENGTH);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
