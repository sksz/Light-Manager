<?php

declare(strict_types=1);

namespace LightManager\Examples;

use LightManager\Domain\Exception\DomainException;

/**
 * Wzorcowy wyjątek domenowy — przykład dydaktyczny wskazywany przez
 * `docs/architektura/06-wzorce-kodu.md`.
 *
 * Trzy rzeczy do przepisania do własnego wyjątku: **prywatny konstruktor**
 * (jedyną drogą jest nazwany konstruktor statyczny, więc miejsce powstania
 * wyjątku da się przeczytać z nazwy), **komunikat techniczny po angielsku**
 * (pisany dla osoby czytającej ślad stosu) i **dane jako typowane pole**
 * (to z nich `Presentation` składa przetłumaczone zdanie dla użytkownika,
 * zamiast wyłuskiwać je z treści komunikatu).
 */
final class InvalidPortNumberException extends DomainException
{
    private function __construct(
        public readonly int $value,
    ) {
        parent::__construct(sprintf('%d is not a valid TCP port number.', $value));
    }

    public static function forValue(int $value): self
    {
        return new self($value);
    }
}
