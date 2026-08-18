<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application;

use LightManager\Module\AddressBook\Domain\ValueObject\FieldKind;

/**
 * Jedno pole, które moduł dokłada do wpisu (krok 60, D105 nr 3).
 *
 * Powstaje **z wiersza cudzej kwerendy**, czyli z napisów i liczb — nigdy
 * z typu cudzego modułu (reguła 15g). Dlatego `of()` jest tolerancyjne: wiersz
 * niekompletny albo o nieznanym rodzaju daje `null`, a książka takie pole
 * **pomija w ciszy**. Moduł nowszy od książki nie ma prawa jej zepsuć, a pole,
 * którego ekran nie umie pokazać, jest polem bez użytkownika.
 *
 * `labelKey` jest kluczem katalogu **cudzego modułu** i to jest w porządku:
 * napisy wszystkich modułów leżą w jednym katalogu pod przedrostkami
 * (reguła 15), więc książka tłumaczy klucz, nie wiedząc, czyj jest.
 */
final readonly class ChapterField
{
    /** @param list<string> $choices dopuszczalne wartości dla `FieldKind::Choice` */
    public function __construct(
        public string $key,
        public string $labelKey,
        public FieldKind $kind = FieldKind::Text,
        public string|int|bool $default = '',
        public array $choices = [],
    ) {
    }

    /**
     * Pole z wiersza kwerendy deklarującej; `null`, gdy wiersz nie opisuje pola,
     * które da się pokazać.
     *
     * @param array<string, string|int|bool> $row
     */
    public static function of(array $row): ?self
    {
        $key = $row['key'] ?? '';
        $label = $row['label'] ?? '';

        if (!is_string($key) || $key === '' || !is_string($label) || $label === '') {
            return null;
        }

        $kind = FieldKind::of(is_string($row['kind'] ?? '') ? (string) $row['kind'] : '');

        if ($kind === null) {
            return null;
        }

        return new self($key, $label, $kind, self::defaultOf($row, $kind), self::choicesOf($row));
    }

    /** Wartość pola jako napis do pokazania i do wpisania w oknie. */
    public function asText(string|int|bool|null $value): string
    {
        $value ??= $this->default;

        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        return (string) $value;
    }

    /** Napis z okna zamieniony na wartość rodzaju pola — bez oceny, czy jest sensowna. */
    public function fromText(string $text): string|int|bool
    {
        return match ($this->kind) {
            FieldKind::Number => (int) $text,
            FieldKind::Flag => $text !== '' && $text !== '0',
            default => $text,
        };
    }

    /** @param array<string, string|int|bool> $row */
    private static function defaultOf(array $row, FieldKind $kind): string|int|bool
    {
        $default = $row['default'] ?? null;

        if ($default === null) {
            return match ($kind) {
                FieldKind::Number => 0,
                FieldKind::Flag => false,
                default => '',
            };
        }

        return match ($kind) {
            FieldKind::Number => (int) $default,
            FieldKind::Flag => (bool) $default,
            default => (string) $default,
        };
    }

    /**
     * Dopuszczalne wartości — rozdzielone pionową kreską, bo wiersz kwerendy
     * niesie **dane pierwotne** i tablicy w nim nie ma (reguła 15g).
     *
     * @param array<string, string|int|bool> $row
     *
     * @return list<string>
     */
    private static function choicesOf(array $row): array
    {
        $choices = $row['choices'] ?? '';

        if (!is_string($choices) || $choices === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), explode('|', $choices)),
            static fn (string $choice): bool => $choice !== '',
        ));
    }
}
