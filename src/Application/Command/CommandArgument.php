<?php

declare(strict_types=1);

namespace LightManager\Application\Command;

/**
 * Deklaracja jednego argumentu komendy.
 *
 * Nazwa nie pada w wierszu — użytkownik pisze `core.theme grafit`, a nie
 * `core.theme nazwa=grafit`. Nazwa służy komendzie (pod nią odbiera wartość)
 * i komunikatom rdzenia („brak argumentu: nazwa”), więc jest **kluczem katalogu
 * napisów**, nie napisem.
 */
final class CommandArgument
{
    public function __construct(
        /** Klucz, pod którym komenda odbiera wartość z `CommandInput`. */
        public readonly string $name,
        /** Klucz katalogu z nazwą argumentu pokazywaną użytkownikowi. */
        public readonly string $labelKey,
        public readonly CommandArgumentKind $kind = CommandArgumentKind::Text,
        public readonly bool $required = true,
        public readonly SuggestionSource $suggestions = SuggestionSource::None,
    ) {
    }
}
