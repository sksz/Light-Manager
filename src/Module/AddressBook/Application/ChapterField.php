<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application;

use LightManager\Module\AddressBook\Domain\Exception\InvalidAddressEntryException;
use LightManager\Module\AddressBook\Domain\ValueObject\FieldKind;

/**
 * Jedno pole, które ktoś zapowiedział, że będzie go używał (krok 60, D105 nr 2).
 *
 * Powstaje **z argumentów komendy**, czyli z napisów — nigdy z typu cudzego
 * modułu (reguła 15g). Kształtem jest bliźniacza wobec `ModuleSetting`, ale
 * **krótsza o walidację dziedzinową** (D105 nr 3): nie ma tu ani wzorca, ani
 * długości maksymalnej. Książka pilnuje **rodzaju** — bo rodzaj musi umieć
 * narysować i przyjąć jej własny ekran — a tego, czy `10.0.0.5` jest adresem,
 * pilnuje ten, kto adres czyta.
 *
 * `labelKey` jest kluczem katalogu **cudzego modułu** i to jest w porządku:
 * napisy wszystkich modułów leżą w jednym katalogu pod przedrostkami
 * (reguła 15), więc książka tłumaczy klucz, nie wiedząc, czyj jest.
 *
 * Deklaracja **nie tworzy właściciela** (D104 nr 2). Dwa moduły wolno
 * zadeklarować to samo pole; deklaracja identyczna jest bezczynna, a sprzeczna
 * nie przestawia pola, które już stoi — rozstrzyga to `AddressChapter`.
 */
final readonly class ChapterField
{
    /** @param list<string> $choices dopuszczalne wartości dla `FieldKind::Choice` */
    public function __construct(
        public string $key,
        public string $labelKey,
        public FieldKind $kind = FieldKind::Text,
        public string $default = '',
        public array $choices = [],
    ) {
    }

    /** Czy dwie deklaracje mówią to samo — wtedy druga jest bezczynna, a nie sprzeczna. */
    public function equals(self $other): bool
    {
        return $this->key === $other->key
            && $this->labelKey === $other->labelKey
            && $this->kind === $other->kind
            && $this->default === $other->default
            && $this->choices === $other->choices;
    }

    /**
     * Wartość w postaci, w jakiej wolno ją zapisać we wpisie — albo wyjątek
     * mówiący, czego rodzaj nie przyjmuje.
     *
     * Rodzaj `Entry` przechodzi tędy jako napis: istnienie wskazywanego wpisu
     * sprawdza `Addresses`, bo tylko ono widzi wszystkie wpisy.
     */
    public function accept(string $value): string|int|bool
    {
        return match ($this->kind) {
            FieldKind::Number => $this->asNumber($value),
            FieldKind::Flag => self::asFlag($value),
            FieldKind::Choice => $this->asChoice($value),
            default => $value,
        };
    }

    /**
     * Wartość w postaci, w jakiej wolno ją **pokazać** — zasłonięta, gdy pole
     * jest sekretem.
     *
     * Zasłona ma **stałą długość**, a nie długość wartości, i jest to ta sama
     * zasada, co w `ModuleSetting::shown()`: liczba gwiazdek równa liczbie
     * znaków mówi o sekrecie tyle, ile mówić nie musi. Pustka zostaje pustką,
     * bo „nic tu nie ma" nie jest sekretem.
     */
    public function shown(string|int|bool|null $value): string
    {
        $text = self::asText($value);

        if (!$this->kind->isMasked() || $text === '') {
            return $text;
        }

        return str_repeat('*', 8);
    }

    /** Wartość jako napis — do wpisania w polu okna i do porównania w filtrze. */
    public static function asText(string|int|bool|null $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    private function asNumber(string $value): int
    {
        if (preg_match('/^-?\d+$/D', $value) !== 1) {
            throw InvalidAddressEntryException::notANumber($this->key, $value);
        }

        return (int) $value;
    }

    /** Wszystko, co nie jest jawnym „nie", jest „tak" — pole logiczne nie ma trzeciego stanu. */
    private static function asFlag(string $value): bool
    {
        return !in_array(mb_strtolower(trim($value)), ['', '0', 'false', 'nie', 'no'], true);
    }

    private function asChoice(string $value): string
    {
        if (!in_array($value, $this->choices, true)) {
            throw InvalidAddressEntryException::notInChoices($this->key, $value, $this->choices);
        }

        return $value;
    }
}
