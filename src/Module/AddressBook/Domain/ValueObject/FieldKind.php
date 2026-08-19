<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Domain\ValueObject;

/**
 * Rodzaje pól, które da się dołożyć do wpisu rozdziałem (krok 60, D105 nr 2).
 *
 * Spis jest **zamknięty i krótki z tego samego powodu, co słownik prymitywów**:
 * rodzaj musi umieć narysować i przyjąć ekran książki, a ekran jest jeden.
 * Rodzaj spoza spisu **pomija się w ciszy** — moduł nowszy od książki nie ma
 * prawa jej zepsuć, a pole, którego nie da się pokazać, jest polem bez
 * użytkownika.
 *
 * Rodzaj jest **jedyną rzeczą, której książka pilnuje w wartości** (D105 nr 3):
 * liczba ma być liczbą, wybór jedną z wypisanych wartości, a odniesienie —
 * istniejącym wpisem. Wzorca dziedzinowego deklaracja nie niesie, bo reguła
 * „co wolno wpisać w adres" należy do tego, kto ten adres czyta.
 *
 * `Secret` nie jest osobnym sposobem przechowywania, tylko **napisem, którego
 * nie pokazuje się wprost** — wzorem `ModuleSetting::secret()` z kroku 54.
 * Zasłona broni przed spojrzeniem, nie przed odczytem: plik stanu ma prawa
 * `0600` i nie udaje sejfu.
 */
enum FieldKind: string
{
    case Text = 'text';

    case Number = 'number';

    case Flag = 'flag';

    case Choice = 'choice';

    case Secret = 'secret';

    case Entry = 'entry';

    /** Rodzaj z deklaracji; `null` — nazwa spoza spisu (pole się pomija). */
    public static function of(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /** Czy wartość zasłania się przy pokazywaniu. */
    public function isMasked(): bool
    {
        return $this === self::Secret;
    }

    /** Czy wartość wskazuje inny wpis książki — wtedy sprawdza się jego istnienie. */
    public function isReference(): bool
    {
        return $this === self::Entry;
    }

    /** Klucz katalogu z nazwą rodzaju — do pokazania w spisie pól. */
    public function labelKey(): string
    {
        return 'module.address-book.kind.' . $this->value;
    }
}
