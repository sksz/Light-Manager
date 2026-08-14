<?php

declare(strict_types=1);

namespace LightManager\Application\Command;

use LightManager\Application\Module\ModuleContext;

/**
 * Komenda, która potrafi powiedzieć, dla jakiego zaznaczenia ma sens (krok 32).
 *
 * Zdolność doklejana **obok** kontraktu, wzorem `SuggestsArguments`, a nie
 * metoda w `CommandInterface`: gdyby stała w kontrakcie, wypełniałoby ją siedem
 * komend rdzenia sześcioma powtórzeniami odpowiedzi „nie dotyczę niczego”.
 * Komenda bez tej zdolności po prostu nie wchodzi do menu kontekstowego —
 * i to jest właściwa domyślna odpowiedź, bo większość komend nie ma z
 * zaznaczeniem nic wspólnego (`core.theme`, `core.quit`).
 *
 * **To jest jedyne, co menu wnosi ponad okno komend** (poza wyborem bez
 * pisania): zawężenie do tego, co da się zrobić tu i teraz. Bez tej zdolności
 * menu pokazałoby wszystko, czyli nie zawęziłoby niczego, czyli byłoby oknem
 * komend bez pola tekstowego.
 *
 * Zdolność mieszka w `Application/Command`, choć wymienia typ z
 * `Application/Module`: obie przestrzenie leżą w tej samej warstwie, a kontekst
 * sesji niesie **dane pierwotne** (napis, napis i enum), więc komenda rdzenia
 * czytająca go nie dowiaduje się niczego o cudzym module (D40, P5).
 */
interface AppliesToSelection
{
    /**
     * Czy komenda ma sens dla tego, co użytkownik ma zaznaczone.
     *
     * Odpowiedź pada **przy każdym otwarciu menu**, więc ma być tania: to
     * porównanie rodzaju wpisu, a nie sięgnięcie na dysk.
     */
    public function appliesTo(ModuleContext $context): bool;

    /**
     * Argumenty złożone z kontekstu — to, czym menu wywoła komendę zamiast
     * wiersza wpisanego ręcznie.
     *
     * Dziś wszystkie cztery komendy ze zdolnością są bezargumentowe i oddają
     * puste wejście, bo działają na zaznaczeniu, a nie na wartości z wiersza.
     * Metoda istnieje mimo to, bo pierwsza komenda z argumentem przyjdzie wraz
     * z operacjami na plikach (krok 41: nazwa docelowa, katalog docelowy),
     * a wtedy menu musi mieć skąd wziąć wartość — z zaznaczenia, nie z pola
     * tekstowego, którego nie ma.
     */
    public function inputFor(ModuleContext $context): CommandInput;
}
