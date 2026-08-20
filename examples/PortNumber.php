<?php

declare(strict_types=1);

namespace LightManager\Examples;

/**
 * Wzorcowy obiekt wartości — przykład dydaktyczny wskazywany przez
 * `docs/KONWENCJE.md` i `docs/architektura/06-wzorce-kodu.md`.
 *
 * Numer portu **nie występuje w aplikacji** jako obiekt wartości i nie ma
 * powstać: gdyby powstał, dokumentacja wskazywałaby `src/`, a ten plik
 * zniknąłby. Przykład dydaktyczny w `src/` byłby kodem bez odbiorcy (reguła 13).
 */
final class PortNumber
{
    private const LOWEST = 1;
    private const HIGHEST = 65535;

    /**
     * Samowalidacja stoi w konstruktorze, więc **nie da się** zbudować obiektu
     * w stanie niepoprawnym — nie ma settera, który mógłby go potem zepsuć.
     */
    public function __construct(
        public readonly int $value,
    ) {
        if ($value < self::LOWEST || $value > self::HIGHEST) {
            throw InvalidPortNumberException::forValue($value);
        }
    }

    /**
     * Obiekty wartości porównuje się **treścią**, nie tożsamością — dwa różne
     * egzemplarze tego samego portu są tym samym portem.
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
