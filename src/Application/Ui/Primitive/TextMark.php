<?php

declare(strict_types=1);

namespace LightManager\Application\Ui\Primitive;

use LightManager\Application\Ui\Role;

/**
 * Napis postawiony **na własnym tle** — ósmy kształt słownika i pierwszy dołożony
 * do niego od kroku 18.
 *
 * Powstał dla podświetlenia dopasowania filtra (krok 30, D59), a otwarcie
 * zamkniętego słownika było na to jawną zgodą użytkownika (D48). Zgoda nie
 * przesądzała jednak kształtu, a kształt jest tu jedyną rzeczą, która ten
 * prymityw usprawiedliwia: **samo tło pod fragmentem nie byłoby nowym kształtem**,
 * tylko synonimem `Bar`a z `Weight::Fill` i `RoundRect`a bez obrysu. Nowe jest
 * dopiero związanie pisma z tłem w jednej rzeczy — i to ono się opłaca:
 *
 * - tor sixelowy składa **jedną** zapamiętaną bitmapę zamiast dwóch
 *   (`compositeImage` kosztuje tyle, ile kształt, ale wywołanie kosztuje zawsze);
 * - tor tekstowy ustawia **tło i kolor pisma tej samej komórki**, czyli degraduje
 *   kształt do atrybutu, a nie do treści;
 * - `TextRun` zostaje nietknięty, więc wiersz bez dopasowania oddaje co do
 *   prymitywu to samo, co przed krokiem 30.
 *
 * Fragment przychodzi **gotowy do narysowania**: przesunięcie liczy się w znakach,
 * a rysuje w kolumnach, więc przeliczenie należy do komponentu — tylko on wie, od
 * której kolumny zaczyna się napis po rozdziale szerokości z kroku 27.
 */
final class TextMark implements Primitive
{
    public function __construct(
        public readonly int $row,
        public readonly int $column,
        /** Sam podświetlony fragment, nie cały napis. */
        public readonly string $text,
        /** Rola pisma. */
        public readonly Role $role,
        /** Rola tła pod pismem — obejmuje dokładnie tyle kolumn, ile fragment ma znaków. */
        public readonly Role $ground,
    ) {
    }

    public function signature(): string
    {
        return 'M' . $this->row . ',' . $this->column
            . ',' . $this->role->name
            . ',' . $this->ground->name
            . ',' . $this->text;
    }
}
