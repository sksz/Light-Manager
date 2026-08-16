<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryRegistry;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Browser\Domain\ValueObject\MarkedEntries;
use LightManager\Module\Browser\Presentation\Query\PaneArgument;

/**
 * Odczyt danych przeglądarki — przez rejestr kwerend (krok 53, D92 nr 3).
 *
 * Trzecia fasada tego kroku i jedyna, która musi umieć zapytać **o wskazany
 * panel**: przeglądarka pokazuje dwa miejsca naraz, więc jej odczyt bierze numer
 * — a `null` znaczy „ten z ogniskiem”, tak samo jak w wierszu kwerendy.
 */
final readonly class BrowserQueries
{
    /**
     * @param BrowserPanes $panes źródło ostatniej szansy — **wyłącznie** na wypadek
     *                            modułu niezarejestrowanego w rejestrze kwerend
     */
    public function __construct(
        private QueryRegistry $queries,
        private BrowserPanes $panes,
    ) {
    }

    /**
     * Katalog panelu.
     *
     * Odpowiedź jest **niepusta**, i to jest różnica warta jednego zdania:
     * ekran rysuje listę w każdej klatce, więc `?Directory` kazałoby mu trzymać
     * własną gałąź zapasową — czyli drugą drogę do stanu, dokładnie tę, którą ten
     * krok znosi. Gałąź zostaje **tutaj**, w jedynym miejscu, któremu wolno znać
     * obiekt stanu, i pada wyłącznie wtedy, gdy moduł nie został zarejestrowany
     * w rejestrze kwerend (testy składające go samodzielnie).
     */
    public function directory(?int $pane = null): Directory
    {
        $payload = $this->payloadOf('entries', $pane);

        if ($payload instanceof Directory) {
            return $payload;
        }

        return $pane === null
            ? $this->panes->focused()->directory()
            : $this->panes->pane($pane)[0]->directory();
    }

    /** Drzewo panelu — z tą samą gałęzią ostatniej szansy, co katalog. */
    public function treeOf(?int $pane = null): BrowserTree
    {
        $payload = $this->payloadOf('tree', $pane);

        if ($payload instanceof BrowserTree) {
            return $payload;
        }

        return $pane === null ? $this->panes->focusedTree() : $this->panes->tree($pane);
    }

    public function selection(?int $pane = null): ?Entry
    {
        $payload = $this->payloadOf('selection', $pane);

        return $payload instanceof Entry ? $payload : null;
    }

    /**
     * Katalog, na który panel **wskazuje** — a to w drzewie znaczy co innego niż
     * w liście.
     *
     * Lista wskazuje swój własny katalog, drzewo — ten, na którym stoi kursor
     * gałęzi (krok 31). Różnica jest widoczna dla użytkownika: `browser.open`
     * w drzewie ma wejść tam, gdzie stoi kursor, a nie do korzenia panelu.
     * Rachunek stoi **tutaj**, bo tutaj są obie odpowiedzi — obie przyszły
     * z rejestru: widok z `browser.panes`, gałąź z `browser.tree`.
     */
    public function pointedDirectory(?int $pane = null): Directory
    {
        return $this->showsTree($pane)
            ? $this->treeOf($pane)->cursorDirectory()
            : $this->directory($pane);
    }

    /**
     * Katalog i **nazwy**, na które ma zadziałać czynność zmieniająca dysk.
     *
     * Jedyne miejsce tej fasady, które **nie liczy nic samo**: reguła pustego
     * zbioru („nic nie zaznaczono" znaczy „wpis pod kursorem", 15c) stoi od
     * kroku 43 w `BrowserPanes::focusedOperands()` i tam ma zostać — policzona
     * drugi raz tutaj rozjechałaby się z tamtą przy pierwszym widoku, który
     * dojdzie po drzewie. Fasada jest po to, żeby czynności nie sięgały po nią
     * przez stan; sam rachunek zostaje u siebie.
     *
     * @return ?array{Directory, list<string>}
     */
    public function operands(): ?array
    {
        return $this->panes->focusedOperands();
    }

    /**
     * Katalog i wpis, na który panel wskazuje — para, na której pracują
     * czynności zmieniające dysk.
     *
     * Chodzą razem, bo czynność potrzebuje obu naraz: wpisu, żeby wiedzieć, na
     * czym działa, i katalogu, żeby wiedzieć gdzie. Pytanie o nie osobno mogłoby
     * trafić na dwie różne chwile — kursor przewija się trzydzieści razy na
     * sekundę.
     *
     * @return ?array{Directory, Entry}
     */
    public function operandsOf(?int $pane = null): ?array
    {
        $directory = $this->pointedDirectory($pane);
        $entry = $directory->selectedEntry();

        return $entry === null ? null : [$directory, $entry];
    }

    public function marked(?int $pane = null): MarkedEntries
    {
        $payload = $this->payloadOf('marked', $pane);

        return $payload instanceof MarkedEntries ? $payload : new MarkedEntries();
    }

    /** Czy panel pokazuje drzewo zamiast listy — z wiersza `browser.panes`. */
    public function showsTree(?int $pane = null): bool
    {
        return ($this->paneRow($pane)['view'] ?? 'list') === 'tree';
    }

    public function focusesSecond(): bool
    {
        return ($this->paneRow(1)['focused'] ?? false) === true;
    }

    public function filter(?int $pane = null): string
    {
        $value = $this->paneRow($pane)['filter'] ?? '';

        return is_string($value) ? $value : '';
    }

    /** Ile wpisów ma katalog **przed** zawężeniem filtrem. */
    public function fullCount(?int $pane = null): int
    {
        $value = $this->paneRow($pane)['full'] ?? 0;

        return is_int($value) ? $value : 0;
    }

    public function showsHidden(?int $pane = null): bool
    {
        return ($this->paneRow($pane)['hidden'] ?? false) === true;
    }

    /**
     * Wiersz układu dla wskazanego panelu; `null` znaczy „ten z ogniskiem".
     *
     * Układ czyta się **wierszami**, a nie ładunkiem, i to jest różnica wobec
     * pozostałych metod tej fasady: `browser.panes` nie ma obiektu, który dałoby
     * się oddać — jego treścią jest zestawienie dwóch paneli, a nie żaden z nich
     * z osobna.
     *
     * @return array<string, string|int|bool>
     */
    private function paneRow(?int $pane): array
    {
        $rows = $this->queries->ask(BrowserSettings::ID . '.panes')->rows();

        if ($pane !== null) {
            return $rows[$pane === 1 ? 1 : 0] ?? [];
        }

        foreach ($rows as $row) {
            if (($row['focused'] ?? false) === true) {
                return $row;
            }
        }

        return $rows[0] ?? [];
    }

    private function payloadOf(string $query, ?int $pane): ?object
    {
        $input = $pane === null
            ? new CommandInput()
            : new CommandInput([PaneArgument::NAME => (string) $pane]);

        return $this->queries
            ->ask(BrowserSettings::ID . '.' . $query, $input)
            ->payloadFor(BrowserSettings::ID);
    }
}
