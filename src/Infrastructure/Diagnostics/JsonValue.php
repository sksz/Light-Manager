<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Odczyt wartości z tablicy pochodzącej z `json_decode()`.
 *
 * Plik wzorca leży w repozytorium i bywa oglądany oraz poprawiany ręcznie, więc
 * jego zawartość jest dla nas danymi obcymi — każdy klucz może zniknąć albo mieć
 * niewłaściwy typ. Zamiast rozsypywać `is_float()` po całym `fromArray()`,
 * wszystkie takie sprawdzenia mieszkają tutaj, a brak wartości cofa się do
 * bezpiecznej wartości domyślnej zamiast wywracać porównanie.
 */
final class JsonValue
{
    /** @param array<string, mixed> $data */
    public static function float(array $data, string $key, float $default = 0.0): float
    {
        $value = $data[$key] ?? null;

        return is_int($value) || is_float($value) ? (float) $value : $default;
    }

    /**
     * Liczba, której **brak jest wartością**: wzorce sprzed kroku 38 nie mają
     * kolumny zimnej klatki, a zero znaczyłoby tam „klatka nie kosztowała nic”
     * zamiast „nie zmierzono”.
     *
     * @param array<string, mixed> $data
     */
    public static function nullableFloat(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    /** @param array<string, mixed> $data */
    public static function int(array $data, string $key, int $default = 0): int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : $default;
    }

    /** @param array<string, mixed> $data */
    public static function bool(array $data, string $key, bool $default = false): bool
    {
        $value = $data[$key] ?? null;

        return is_bool($value) ? $value : $default;
    }

    /** @param array<string, mixed> $data */
    public static function string(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    /**
     * Zagnieżdżona tablica o kluczach tekstowych; cokolwiek innego znaczy pustą.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function map(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (!is_array($value)) {
            return [];
        }

        $map = [];

        /** @var mixed $entry */
        foreach ($value as $name => $entry) {
            if (is_string($name)) {
                $map[$name] = $entry;
            }
        }

        return $map;
    }
}
