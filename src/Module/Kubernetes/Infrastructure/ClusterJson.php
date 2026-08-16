<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Infrastructure;

/**
 * Wspólne rozczytywanie JSON-a klastra (krok 52).
 *
 * Powstało z jednego powodu i jest nim analiza statyczna: `json_decode()` oddaje
 * `mixed`, a PHPStan na poziomie `max` żąda, żeby każde zejście w głąb takiej
 * struktury było sprawdzone. Bez wspólnego miejsca ta sama sekwencja
 * `is_array()` + `array_key_exists()` + `is_string()` powtórzyłaby się
 * w kilkudziesięciu miejscach parsera, a każde z nich byłoby okazją do
 * przeoczenia jednego sprawdzenia.
 *
 * Klasa **niczego nie interpretuje** — nie wie, czym jest pod ani sekret. Wie
 * wyłącznie, jak bezpiecznie sięgnąć po napis, liczbę albo tablicę ze struktury,
 * której kształtu nikt nam nie zagwarantował. Znaczenie dokłada
 * `ResourceJsonParser`, a wiedzę o rodzajach — `ResourceColumnPacks`.
 */
final class ClusterJson
{
    /**
     * Dekoduje wypis w tablicę albo oddaje `null`.
     *
     * `null` znaczy „to nie jest JSON, jakiego oczekiwaliśmy”, i jest odpowiedzią
     * **zwykłą, nie awaryjną**: `kubectl` przy braku klastra wypisuje na wyjściu
     * pustkę, a powód na strumieniu błędów. Rzucanie wyjątkiem zamieniłoby
     * przewidziany stan („nie ma klastra”) w awarię aplikacji.
     *
     * @return array<string, mixed>|null
     */
    public static function decode(string $json): ?array
    {
        if (trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? self::stringKeyed($decoded) : null;
    }

    /**
     * Schodzi po kluczach i oddaje to, co zastanie — albo `null`.
     *
     * @param array<string, mixed> $data
     */
    public static function dig(array $data, string ...$keys): mixed
    {
        $current = $data;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }

            $current = $current[$key];
        }

        return $current;
    }

    /** @param array<string, mixed> $data */
    public static function text(array $data, string ...$keys): ?string
    {
        $value = self::dig($data, ...$keys);

        return is_string($value) ? $value : null;
    }

    /**
     * Liczba spod klucza — także wtedy, gdy przyszła napisem.
     *
     * Klaster podaje liczby liczbami, ale pola pochodzące z adnotacji
     * i z zasobów własnych bywają napisami; różnica nie ma tu znaczenia, bo
     * i tak pokazujemy je jako tekst.
     *
     * @param array<string, mixed> $data
     */
    public static function number(array $data, string ...$keys): ?int
    {
        $value = self::dig($data, ...$keys);

        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : null;
    }

    /**
     * Lista tablic spod klucza — pusta, gdy nie ma czego czytać.
     *
     * @param  array<string, mixed>       $data
     * @return list<array<string, mixed>>
     */
    public static function objects(array $data, string ...$keys): array
    {
        $value = self::dig($data, ...$keys);

        if (!is_array($value)) {
            return [];
        }

        $objects = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $objects[] = self::stringKeyed($item);
            }
        }

        return $objects;
    }

    /**
     * Mapa napisów spod klucza — klucze sekretu, etykiety, adnotacje.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public static function map(array $data, string ...$keys): array
    {
        $value = self::dig($data, ...$keys);

        if (!is_array($value)) {
            return [];
        }

        $map = [];

        foreach ($value as $key => $item) {
            if (is_string($item)) {
                $map[(string) $key] = $item;
            }
        }

        return $map;
    }

    /**
     * Chwila z pola `creationTimestamp` jako znacznik uniksowy.
     *
     * Kubernetes podaje ją w RFC 3339 zawsze w strefie UTC (`2026-08-16T07:12:03Z`),
     * ale strefę zostawiamy `strtotime()`, zamiast obcinać `Z` i doklejać własną
     * — data z przyszłości albo z inną strefą ma dać liczbę, a nie zgadywankę.
     */
    public static function timestamp(string $value): ?int
    {
        $parsed = strtotime($value);

        return $parsed === false ? null : $parsed;
    }

    /**
     * @param  array<array-key, mixed> $data
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $data): array
    {
        $keyed = [];

        foreach ($data as $key => $value) {
            $keyed[(string) $key] = $value;
        }

        return $keyed;
    }
}
