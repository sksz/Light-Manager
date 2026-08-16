<?php

declare(strict_types=1);

namespace LightManager\Application\Query;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;

/**
 * Źródło danych pytane po nazwie — **jedyna droga odczytu w tej aplikacji**
 * (krok 53, D92 nr 3).
 *
 * Zdanie, które trzyma podział wobec komendy: **komenda robi, kwerenda mówi.**
 * Co zmienia stan — jest komendą i wraca `CommandOutcome`, czyli zdaniem dla
 * użytkownika; co go wyłącznie czyta — jest kwerendą i wraca `QueryResult`, czyli
 * daną. Bez tego podziału pierwsza kwerenda `docker.prune` uczyniłaby mechanizm
 * drugą drogą do czynności.
 *
 * **Argument i jego wartość to jedno pojęcie, więc jedna klasa**: kwerenda
 * deklaruje `CommandArgument` i odbiera `CommandInput`, a wiersz rozbiera ten sam
 * `CommandLineParser`, co przy komendach. Bliźniacze `QueryArgument`
 * i `QueryInput` byłyby dwoma miejscami do poprawienia przy każdej zmianie
 * rozbioru wiersza — tą samą pomyłką, przed którą broni się `ScreenCommand`
 * jedną klasą na dwa ekrany.
 *
 * Cztery reguły kwerendy, wszystkie wykonane w rejestrze albo tutaj:
 *
 * 1. **Czyta i nie zmienia.**
 * 2. **Nie zna wołającego** — wygląda tak samo przy zerze pytających. Ładunek
 *    typowany wydaje się po **nazwie właściciela**, a nie po tożsamości
 *    pytającego; rejestr pytającego nadal nie zna.
 * 3. **Nie woła kwerendy** — wzorem „zdarzenie nie rodzi zdarzenia" (krok 46).
 * 4. **Odpowiada w klatce albo nie odpowiada wcale** — praca dłuższa od klatki
 *    idzie komendą, a kwerenda oddaje jej **stan**, nigdy nie czeka na koniec.
 */
interface QueryInterface
{
    /**
     * Znacznik dla kwerendy, której odpowiedzi **nie da się opisać pokoleniem** —
     * postęp pracy, stan silnika audio, licznik bajtów, komunikat gasnący sam.
     *
     * Rejestr takiej odpowiedzi **nie pamięta**: liczy ją przy każdym pytaniu.
     * Warunek, pod którym wolno tak zadeklarować kwerendę, jest jeden i ostry —
     * `ask()` ma być tani, bo zapłaci się za niego tyle razy, ile razy ktoś
     * zapyta w klatce. Kosztowną treść oddaje się przez `QueryResult::lazy()`,
     * którą i tak liczy się raz.
     *
     * Wartość ujemna, bo pokolenia są licznikami i zaczynają się od zera.
     */
    public const VOLATILE = -1;

    /**
     * Pełna nazwa wraz z przestrzenią właściciela: `core.settings`,
     * `browser.entries`. Przedrostka pilnuje rejestr.
     */
    public function name(): string;

    /** Klucz katalogu napisów z opisem — nie sam napis (reguła 7). */
    public function descriptionKey(): string;

    /**
     * Argumenty w kolejności, w jakiej padają w wierszu.
     *
     * @return list<CommandArgument>
     */
    public function arguments(): array;

    /**
     * Tani znacznik zmiany źródła: **ten sam numer znaczy tę samą odpowiedź**.
     *
     * Rejestr przelicza wynik wyłącznie po zmianie tej liczby, więc odczyt
     * niezmienionego źródła kosztuje jedno wyszukanie w tablicy. Odpowiedź musi
     * być tania — licznik odświeżeń, numer pokolenia sesji, `count()` — nigdy
     * praca, przed którą pokolenie ma chronić. Kwerenda zmieniająca się co
     * klatkę oddaje `self::VOLATILE`.
     */
    public function generation(): int;

    /** Odpowiedź; kwerenda **nie rzuca** (reguła 8) — powód wraca w wyniku. */
    public function ask(CommandInput $input): QueryResult;
}
