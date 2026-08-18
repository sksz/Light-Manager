<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Domain\ValueObject;

/**
 * Rodzaje pól, które moduł może dołożyć do wpisu rozdziałem (krok 60, D105 nr 3).
 *
 * Spis jest **zamknięty i krótki z tego samego powodu, co słownik prymitywów**:
 * rodzaj musi umieć narysować i przyjąć ekran książki, a ekran jest jeden.
 * Rodzaj nieznany książce **pomija się w ciszy** — moduł nowszy od książki nie
 * ma prawa jej zepsuć, a pole, którego nie da się pokazać, jest polem bez
 * użytkownika.
 *
 * `Choice` niesie listę dopuszczalnych wartości w deklaracji pola, nie tutaj:
 * enum mówi, **jak** się pyta, a nie **o co**.
 */
enum FieldKind: string
{
    case Text = 'text';

    case Number = 'number';

    case Flag = 'flag';

    case Choice = 'choice';

    /** Rodzaj z deklaracji modułu; `null` — nazwa spoza spisu (pole się pomija). */
    public static function of(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
