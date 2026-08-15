<?php

declare(strict_types=1);

namespace LightManager\Application\Event;

/**
 * Jedna pozycja słownika zdarzeń: nazwa i klucz napisu, pod którym pokazuje ją
 * odbiorca (krok 46).
 *
 * Zdarzenie niesie **wyłącznie tożsamość** — nazwę i nic ponad nią. Ta sama
 * zasada, którą kieruje się `ModuleContext` (D40, P5): obiekt domeny modułu nie
 * ma prawa przejechać przez zdarzenie, bo odbiorca musiałby poznać moduł, który
 * je publikuje. Gdyby efekt kiedyś miał zależeć od danej, a nie od nazwy, jest to
 * osobna decyzja i osobne pole — nie „przy okazji”.
 *
 * Samowalidacji w konstruktorze nie ma i to jest ta sama decyzja, co
 * w `ModuleShortcut`: deklaracja przychodzi od modułu, a moduł wadliwy ma zostać
 * **odsiany przez rejestr**, a nie wysadzić start aplikacji wyjątkiem. Kształtu
 * nazwy pilnuje `EventRegistry::declare()`, dokładnie tak, jak `CommandRegistry`
 * pilnuje przestrzeni nazw komend.
 */
final class EventDeclaration
{
    public function __construct(
        /** Nazwa w przestrzeni deklarującego, np. `core.message.error`. */
        public readonly string $name,
        /** Klucz katalogu napisów z nazwą widoczną dla użytkownika. */
        public readonly string $labelKey,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name && $this->labelKey === $other->labelKey;
    }
}
