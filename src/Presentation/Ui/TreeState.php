<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

/**
 * Stan drzewa: które gałęzie są rozwinięte i na którym węźle stoi kursor.
 *
 * **Czwarta w projekcie klasa pamiętająca coś między klatkami**, po
 * `ScrollWindow` (krok 18), `SectionState` (krok 22) i `SplitState` (krok 24).
 * Reguła własności jest za każdym razem ta sama i nie zmienia się i tutaj:
 * komponent powstaje na nowo trzydzieści razy na sekundę, więc to, co ma przeżyć
 * klatkę, mieszka **obok** niego, a właścicielem jest ekran.
 *
 * Rozwinięcia trzymają się **pod kluczem węzła, a nie pod jego numerem** — wprost
 * z `SectionState`, bo to jest ten sam problem o wymiar głębiej. Gałąź, która
 * zniknęła z widoku i wróciła (użytkownik wszedł katalog niżej i się rozmyślił,
 * odświeżył listę, przełączył wpisy ukryte), ma wrócić rozwinięta. Numer po
 * zmianie drzewa wskazywałby na sąsiada.
 *
 * **Kursor jest tu jednak też kluczem, i to jest jedyna rzecz, którą ta klasa robi
 * inaczej niż `SectionState`.** Tam kursor jest numerem, bo znaczy „która sekcja
 * po kolei”, a lista sekcji zmienia się razem z ekranem. Tutaj numer wiersza
 * zmienia się przy **każdym** rozwinięciu i zwinięciu czegokolwiek powyżej — więc
 * kursor-numer wędrowałby po drzewie sam z siebie. Stąd rozstrzygnięcie
 * użytkownika ze startu kroku: po zwinięciu gałęzi kursor staje na **zwiniętym
 * rodzicu**, a nie na przypadkowym sąsiedzie, który akurat przejął jego numer.
 *
 * Dwie klasy zamiast jednej — również rozstrzygnięcie ze startu kroku. Poza
 * kursorem różni je jeszcze domyślna odpowiedź: sekcja bez wpisu jest
 * **rozwinięta**, gałąź bez wpisu — **zwinięta**. Wspólna klasa musiałaby
 * przyjmować tę domyślną z zewnątrz, czyli nazywać się już nie stanem, tylko
 * mapą wartości logicznych.
 */
final class TreeState
{
    /**
     * Klucze gałęzi rozwiniętych — i **tylko** rozwiniętych.
     *
     * Zwinięcie usuwa wpis zamiast zapisywać `false`, więc mapa rośnie wraz
     * z tym, co użytkownik naprawdę otworzył, a nie wraz z tym, czego kiedykolwiek
     * dotknął.
     *
     * @var array<string, true>
     */
    private array $expanded = [];

    private ?string $cursor = null;

    private ?string $context = null;

    /**
     * Zmiana kontekstu — inny katalog w korzeniu — sprowadza kursor na początek,
     * tak samo jak w `ScrollWindow` i `SectionState`.
     *
     * **Rozwinięcia zostają**, i to jest cała różnica wobec kursora: klucz gałęzi
     * jest bezwzględny, więc po powrocie do poprzedniego korzenia nadal znaczy to
     * samo. Na tym stoi kryterium kroku „rozwinięcia wracają w tym samym stanie
     * po powrocie z innego ekranu”.
     */
    public function useContext(string $context): void
    {
        if ($this->context !== $context) {
            $this->context = $context;
            $this->cursor = null;
        }
    }

    public function isExpanded(string $key): bool
    {
        return isset($this->expanded[$key]);
    }

    public function expand(string $key): void
    {
        $this->expanded[$key] = true;
    }

    /**
     * Zwinięcie gałęzi wraz z **przeniesieniem na nią kursora**.
     *
     * Kursor idzie tu, a nie u wołającego, bo to jest reguła, nie wybór ekranu:
     * węzły pod zwiniętą gałęzią przestają istnieć w spłaszczonej liście, a kursor
     * na nieistniejącym węźle znaczyłby „gdziekolwiek”. Rozstrzygnięcie
     * użytkownika ze startu kroku 31.
     */
    public function collapse(string $key): void
    {
        unset($this->expanded[$key]);
        $this->cursor = $key;
    }

    public function toggle(string $key): void
    {
        $this->isExpanded($key) ? $this->collapse($key) : $this->expand($key);
    }

    /** Klucz węzła pod kursorem; `null` — drzewo jest puste albo jeszcze nietknięte. */
    public function cursor(): ?string
    {
        return $this->cursor;
    }

    public function moveTo(?string $key): void
    {
        $this->cursor = $key;
    }

    /**
     * Numer kursora w podanej liście kluczy; `null`, gdy węzeł zniknął z drzewa.
     *
     * @param list<string> $keys widoczne węzły po spłaszczeniu, w kolejności wierszy
     */
    public function indexIn(array $keys): ?int
    {
        if ($this->cursor === null) {
            return null;
        }

        $index = array_search($this->cursor, $keys, true);

        return $index === false ? null : $index;
    }

    /**
     * Przesuwa kursor o zadaną liczbę wierszy i **przycina go do drzewa**.
     *
     * Wołanie z `$delta === 0` jest przez to poprawnym sposobem powiedzenia
     * „drzewo się zmieniło, ustaw się w jego granicach” — dokładnie tak, jak
     * `SectionState::moveBy()`. Kursor wskazujący węzeł, którego już nie ma
     * (katalog odczytany ponownie, gałąź zwinięta gdzieś wyżej), wraca na
     * początek: bez wiedzy o tym, czym są klucze, rdzeń nie ma jak znaleźć jego
     * najbliższego żyjącego przodka, a zgadywanie byłoby gorsze od jawnej reguły.
     *
     * @param list<string> $keys widoczne węzły po spłaszczeniu, w kolejności wierszy
     */
    public function moveBy(int $delta, array $keys): void
    {
        if ($keys === []) {
            $this->cursor = null;

            return;
        }

        $index = $this->indexIn($keys) ?? 0;

        $this->cursor = $keys[max(0, min($index + $delta, count($keys) - 1))];
    }
}
