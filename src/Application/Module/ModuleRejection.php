<?php

declare(strict_types=1);

namespace LightManager\Application\Module;

/**
 * Powód, dla którego moduł nie został wpuszczony.
 *
 * **Powód jest daną, nie wyjątkiem.** Plan przewidywał kiedyś `ModuleException`;
 * rezygnujemy z niej, bo rejestr niczego nie przerywa — odrzucenie jest
 * **wynikiem** jego pracy, a wyjątek musiałby zostać złapany w miejscu wywołania
 * i natychmiast zamieniony z powrotem na daną. Błąd w zestawie modułów nie ma
 * prawa odebrać użytkownikowi menadżera plików.
 */
final class ModuleRejection
{
    public function __construct(
        /** Identyfikator odrzuconego modułu — taki, jaki podał, nawet gdy jest niepoprawny. */
        public readonly string $id,
        /** Klucz katalogu napisów z powodem odrzucenia. */
        public readonly string $reasonKey,
    ) {
    }
}
