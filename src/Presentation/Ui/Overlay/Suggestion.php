<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Overlay;

/**
 * Jedna pozycja listy podpowiedzi.
 *
 * Trzy zbiory trafiają na tę samą listę i muszą się różnić dwiema rzeczami:
 * czy mają opis (komendy mają, wartości argumentów nie) i czy wstawienie
 * podmienia **cały wiersz** (wpis historii), czy tylko uzupełniane słowo (nazwa
 * komendy, wartość argumentu).
 */
final class Suggestion
{
    public function __construct(
        public readonly string $value,
        /** Klucz katalogu z opisem; pusty, gdy podpowiedź opisu nie ma. */
        public readonly string $descriptionKey = '',
        public readonly bool $replacesLine = false,
    ) {
    }
}
