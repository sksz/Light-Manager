<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use LightManager\Application\Dto\Key;
use LightManager\Application\Port\TranslatorPort;

/**
 * Podpowiedzi paska stanu: co da się zrobić tu i teraz, ułożone od
 * najszczegółowszego do najogólniejszego i przycięte do tego, co się mieści.
 *
 * Klasa powstała w kroku 40 i odwraca zasięg decyzji z kroków 14 i 18. Do niej
 * stopka pokazywała **wyłącznie klawisze rdzenia** — cztery napisy niezmienne od
 * pierwszego uruchomienia — a pełny spis stał pod `F1`. Zostaje z tamtej decyzji
 * połowa, i to ta ważniejsza: podpowiedź powstaje z `KeyBinding`, czyli z tego
 * samego miejsca, z którego pochodzi obsługa klawisza. Napis w katalogu potrafił
 * skłamać po zmianie wiązania; wiązanie nie ma jak.
 *
 * Trzy poziomy i ich kolejność (czytana od lewej):
 *
 * 1. **element z ogniskiem** — panel, sekcja, pole; poprzedzony nazwą miejsca,
 * 2. **ekran albo okno nakładane** — okno **wypiera** ekran, bo klawisze do niego
 *    nie schodzą (`InputHandler::toOverlay`, krok 19),
 * 3. **globalne** — klawisze rdzenia wraz ze skrótami modułów.
 *
 * Ustępowanie idzie **od końca**, czyli w odwrotnej kolejności: pierwsze znikają
 * skróty modułów i klawisze rdzenia, bo one jedne są niezmienne i stoją w oknie
 * pomocy; ostatnie — to, co dotyczy miejsca pod kursorem. Wyjątkiem jest `F1`:
 * przypięty, ustępuje dopiero wtedy, gdy nie mieści się sam jeden.
 *
 * Pozycja **nigdy nie urywa się w połowie** — nie mieści się w całości, więc
 * znika w całości. Podpowiedź ucięta do `moduł.file-in…` nie jest podpowiedzią.
 */
final class StatusHints
{
    /** Rozdzielnik między pozycjami — kropka w połowie wysokości, jak w oknie komend. */
    private const SEPARATOR = ' · ';

    /** Odstęp nazwy miejsca od pierwszego wiązania. */
    private const LABEL_SEPARATOR = ': ';

    /**
     * Ostatnio policzone ułożenie wraz z budżetami, dla których powstało.
     *
     * @var ?array{list<string>, list<HintPlacement>}
     */
    private ?array $fitted = null;

    private ?string $fittedFor = null;

    /** @param list<Hint> $items pozycje w kolejności czytania */
    public function __construct(private readonly array $items = [])
    {
    }

    /**
     * Złożenie podpowiedzi z trzech poziomów.
     *
     * Odsiew powtórzeń jest tu **konieczny**, a nie ostrożnościowy: ekran składa
     * `bindings()` z wiązań miejsca **plus** własnych, bo okno pomocy ma zostać
     * pełnym spisem. Bez odsiewu każdy klawisz panelu stałby w stopce dwa razy.
     *
     * @param ?FocusHint       $focus    miejsce z ogniskiem albo `null`
     * @param list<KeyBinding> $bindings wiązania ekranu albo okna nakładanego
     * @param list<KeyBinding> $global   klawisze rdzenia wraz ze skrótami modułów
     */
    public static function compose(
        ?FocusHint $focus,
        array $bindings,
        array $global,
        TranslatorPort $translator,
    ): self {
        $items = [];
        $seen = [];

        if ($focus !== null) {
            foreach ($focus->bindings as $binding) {
                $seen[] = $binding;
                $items[] = new Hint(
                    // Nazwa miejsca doklejona do **pierwszej** pozycji, a nie osobna:
                    // ustępowanie idzie od końca, więc etykieta znika dokładnie wtedy,
                    // gdy znika ostatnie wiązanie miejsca — i nigdy wcześniej.
                    (count($items) === 0 ? $translator->translate($focus->labelKey) . self::LABEL_SEPARATOR : '')
                        . self::text($binding, $translator),
                    false,
                    $binding,
                );
            }
        }

        foreach ([$bindings, $global] as $level) {
            foreach ($level as $binding) {
                if (self::alreadySeen($seen, $binding)) {
                    continue;
                }

                $seen[] = $binding;
                $items[] = new Hint(self::text($binding, $translator), $binding->usesKey(Key::F1), $binding);
            }
        }

        return new self($items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /** Czy **wszystkie** pozycje mieszczą się w jednym wierszu tej szerokości. */
    public function fitInOneRow(int $columns): bool
    {
        return $this->pack($this->items, [$columns], false) !== null;
    }

    /**
     * Napisy kolejnych wierszy paska — po jednym na podany budżet kolumn.
     *
     * Ustępowanie i pakowanie liczą się **razem**, w jednym rachunku: pozycje
     * odpadają od końca dopóty, dopóki reszta nie zmieści się w podanych
     * wierszach. Wynik jest krótszy od liczby budżetów, gdy dalsze wiersze
     * zostają puste.
     *
     * @param list<int> $budgets szerokości kolejnych wierszy, w kolejności rysowania
     *
     * @return list<string>
     */
    public function lines(array $budgets): array
    {
        return $this->fit($budgets, false)[0];
    }

    /**
     * Gdzie w wierszach paska stanęła każda pozycja — **ten sam rachunek**, co
     * `lines()`, oglądany z drugiej strony (krok 55).
     *
     * Metody są dwie, ale przebieg jest jeden i to jest w tym najważniejsze:
     * gdyby położenia liczyło osobne pakowanie, rozjechałoby się z rysowaniem
     * przy pierwszej zmianie progu ustępowania — a rozjazd byłby niewidoczny do
     * chwili, gdy ktoś kliknie i wykona nie ten klawisz, co widzi.
     *
     * @param list<int> $budgets szerokości kolejnych wierszy, w kolejności rysowania
     *
     * @return list<HintPlacement>
     */
    public function placements(array $budgets): array
    {
        return $this->fit($budgets, true)[1];
    }

    /**
     * Ustępowanie i pakowanie razem: pozycje odpadają od końca dopóty, dopóki
     * reszta nie zmieści się w podanych wierszach.
     *
     * @param list<int> $budgets
     * @param bool      $withPlacements czy liczyć położenia pozycji — rachunek
     *                                  potrzebny wyłącznie wskaźnikowi, a więc
     *                                  kilka razy na minutę, nie trzydzieści
     *                                  razy na sekundę
     *
     * @return array{list<string>, list<HintPlacement>}
     */
    private function fit(array $budgets, bool $withPlacements): array
    {
        // Pamięć **na jeden zestaw budżetów** i to nie jest optymalizacja na
        // zapas: od kroku 55 ten sam rachunek zamawiają w jednej klatce trzy
        // miejsca — `fitInOneRow()` przed podziałem okna, `draw()` paska stanu
        // i mapa trafień stopki. Ustępowanie jest pętlą po pozycjach, więc trzy
        // przebiegi to trzykrotny koszt czegoś, co ma jedną odpowiedź.
        // Obiekt powstaje na nowo w każdej klatce (reguła 11a), więc pamięć nie
        // ma jak się zestarzeć.
        $key = ($withPlacements ? '+' : '-') . implode(',', $budgets);

        if ($this->fitted !== null && $this->fittedFor === $key) {
            return $this->fitted;
        }

        $items = $this->items;
        $result = [[], []];

        while ($items !== []) {
            $packed = $this->pack($items, $budgets, $withPlacements);

            if ($packed !== null) {
                $result = $packed;

                break;
            }

            $items = self::withoutLast($items);
        }

        $this->fittedFor = $key;

        return $this->fitted = $result;
    }

    /**
     * Próba ułożenia wszystkich pozycji w podanych wierszach.
     *
     * Pozycje idą po kolei i nie zmieniają miejsc — wiersz pierwszy dostaje to,
     * co najszczegółowsze. `null` znaczy „nie mieszczą się”; wołający zdejmuje
     * wtedy ostatnią pozycję i pyta jeszcze raz.
     *
     * @param list<Hint> $items
     * @param list<int>  $budgets
     *
     * @return ?array{list<string>, list<HintPlacement>}
     */
    private function pack(array $items, array $budgets, bool $withPlacements): ?array
    {
        $lines = [];
        $placements = [];
        $current = '';
        $budget = array_shift($budgets);

        if ($budget === null) {
            return $items === [] ? [[], []] : null;
        }

        foreach ($items as $item) {
            $candidate = $current === '' ? $item->text : $current . self::SEPARATOR . $item->text;

            if (mb_strlen($candidate) <= $budget) {
                $placements[] = $withPlacements ? self::placementOf($item, count($lines), $candidate) : null;
                $current = $candidate;

                continue;
            }

            $next = array_shift($budgets);

            if ($next === null || mb_strlen($item->text) > $next) {
                return null;
            }

            $lines[] = $current;
            $budget = $next;
            $current = $item->text;
            $placements[] = $withPlacements ? self::placementOf($item, count($lines), $current) : null;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return [$lines, array_values(array_filter($placements))];
    }

    /**
     * Miejsce pozycji w wierszu: koniec napisu wiersza minus długość samej
     * pozycji. Pozycja bez wiązania (obciążenie pomiarowe) nie ma czego oddać
     * kliknięciu, więc nie powstaje.
     */
    private static function placementOf(Hint $item, int $line, string $lineText): ?HintPlacement
    {
        if ($item->binding === null) {
            return null;
        }

        $length = mb_strlen($item->text);

        return new HintPlacement($line, mb_strlen($lineText) - $length, $length, $item->binding);
    }

    /**
     * Lista bez ostatniej pozycji **nieprzypiętej**.
     *
     * `F1` przeskakujemy dopóty, dopóki stoi przy nim cokolwiek innego; gdy zostaje
     * sam, zdejmuje się jak każdy inny — i wtedy stopki nie ma w ogóle, bo okno
     * jest za wąskie nawet na dwa słowa.
     *
     * @param list<Hint> $items
     *
     * @return list<Hint>
     */
    private static function withoutLast(array $items): array
    {
        for ($index = count($items) - 1; $index >= 0; --$index) {
            if (!$items[$index]->pinned || count($items) === 1) {
                // `array_splice` przenumerowuje klucze, więc wynik zostaje listą.
                array_splice($items, $index, 1);

                return $items;
            }
        }

        return [];
    }

    /** @param list<KeyBinding> $seen */
    private static function alreadySeen(array $seen, KeyBinding $binding): bool
    {
        foreach ($seen as $earlier) {
            if ($earlier->sameAs($binding)) {
                return true;
            }
        }

        return false;
    }

    private static function text(KeyBinding $binding, TranslatorPort $translator): string
    {
        return $binding->display() . ' ' . $translator->translate($binding->hintKey());
    }
}
