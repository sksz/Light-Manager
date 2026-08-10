<?php

declare(strict_types=1);

namespace LightManager\Application\Command;

/**
 * Gdzie stoi wpisywanie w wierszu: czy użytkownik pisze jeszcze nazwę komendy,
 * czy któryś z argumentów, i co zdążył wpisać.
 *
 * Rozbiór wiersza na potrzeby uzupełniania jest tą samą robotą co rozbiór na
 * potrzeby wywołania, więc należy do parsera. Okno komend pyta o wynik i nie
 * ogląda ani spacji, ani cudzysłowów.
 */
final class CommandCompletion
{
    public function __construct(
        /** Pierwsze słowo wiersza; pusty napis, gdy nic jeszcze nie padło. */
        public readonly string $name,
        /** Numer uzupełnianego argumentu; `-1`, gdy wpisywana jest nazwa komendy. */
        public readonly int $argumentIndex,
        /** Przedrostek uzupełnianego słowa. */
        public readonly string $prefix,
    ) {
    }

    public function completesName(): bool
    {
        return $this->argumentIndex < 0;
    }
}
