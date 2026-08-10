<?php

declare(strict_types=1);

namespace LightManager\Application\Module;

/**
 * Skrót otwierający ekran modułu: `Ctrl` plus litera.
 *
 * Skrót jest **daną, nie `KeyBinding`iem** (P15). Rejestr modułów leży
 * w `Application` i musi umieć porównać dwa skróty ze sobą oraz z listą liter
 * zabronionych — a `KeyBinding` mieszka w `Presentation`, więc rejestr, który by
 * go zobaczył, sięgnąłby po warstwę leżącą na zewnątrz niego. Wiązanie do
 * podpowiedzi i do spisu w pomocy składa z tej danej strona `Presentation`.
 *
 * Flaga `ctrl` jest dziś zawsze prawdziwa, a rejestr odrzuca moduł, który poda
 * fałsz. Pole istnieje mimo to, bo lista kombinacji zarezerwowanych i
 * porównywanie skrótów mają operować na **pełnej tożsamości klawisza**, a nie na
 * samym znaku: gdyby kiedyś doszedł skrót bez `Ctrl`, `d` i `Ctrl+D` musiałyby
 * być rozróżnialne, a nie równe.
 *
 * Samowalidacji w konstruktorze tu nie ma — i to jest zgodne z warstwą, a nie
 * wbrew niej. Nieprawidłowy skrót nie ma prawa przerwać startu aplikacji;
 * jest **powodem odrzucenia modułu**, czyli wynikiem pracy rejestru (sekcja 3
 * planu). Wyjątek musiałby zostać złapany w miejscu wywołania i natychmiast
 * zamieniony z powrotem na daną.
 */
final class ModuleShortcut
{
    public function __construct(
        /** Pojedyncza litera `a`–`z`; kształtu pilnuje `ModuleRegistry`. */
        public readonly string $character,
        public readonly bool $ctrl = true,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->character === $other->character && $this->ctrl === $other->ctrl;
    }
}
