<?php

declare(strict_types=1);

namespace LightManager\Domain\Exception;

use RuntimeException;

/**
 * Wspólny przodek wyjątków warstwy domenowej.
 *
 * **Komunikat jest techniczny i po angielsku** — pisany dla osoby czytającej
 * ślad stosu, nie dla użytkownika aplikacji. Napis, który zobaczy użytkownik,
 * dobiera `Presentation` po klasie wyjątku i składa z katalogu napisów; dane
 * potrzebne do jego złożenia (ścieżka, nazwa) wyjątek wystawia jako typowane
 * pola, a nie zaszywa w treści komunikatu (krok 15).
 *
 * Dzięki temu `Domain` nadal nie zna ani katalogu napisów, ani wybranego
 * języka — a mimo to nie odbiera użytkownikowi konkretów.
 */
abstract class DomainException extends RuntimeException
{
}
