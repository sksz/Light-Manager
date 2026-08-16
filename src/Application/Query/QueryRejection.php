<?php

declare(strict_types=1);

namespace LightManager\Application\Query;

/**
 * Kwerenda, której rejestr nie przyjął, wraz z powodem.
 *
 * Powód jest **daną**, nie wyjątkiem — dokładnie jak przy komendzie
 * (`CommandRejection`) i przy module: zła nazwa jednej kwerendy nie ma prawa
 * odebrać użytkownikowi całego okna.
 */
final class QueryRejection
{
    public function __construct(
        public readonly string $owner,
        public readonly string $name,
        /** Klucz katalogu napisów z powodem odrzucenia. */
        public readonly string $reasonKey,
    ) {
    }
}
