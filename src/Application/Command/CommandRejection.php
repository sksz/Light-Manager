<?php

declare(strict_types=1);

namespace LightManager\Application\Command;

/**
 * Komenda, której rejestr nie przyjął, wraz z powodem.
 *
 * Powód jest **daną**, nie wyjątkiem: rejestr niczego nie przerywa, bo zła nazwa
 * jednej komendy nie ma prawa odebrać użytkownikowi całego okna. Ta sama zasada
 * rządzi odrzuceniem modułu w kroku 20.
 */
final class CommandRejection
{
    public function __construct(
        public readonly string $owner,
        public readonly string $name,
        /** Klucz katalogu napisów z powodem odrzucenia. */
        public readonly string $reasonKey,
    ) {
    }
}
