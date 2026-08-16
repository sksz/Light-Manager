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
    public function __construct(
        private QueryRegistry $queries,
    ) {
    }

    public function directory(?int $pane = null): ?Directory
    {
        $payload = $this->payloadOf('entries', $pane);

        return $payload instanceof Directory ? $payload : null;
    }

    public function selection(?int $pane = null): ?Entry
    {
        $payload = $this->payloadOf('selection', $pane);

        return $payload instanceof Entry ? $payload : null;
    }

    public function marked(?int $pane = null): MarkedEntries
    {
        $payload = $this->payloadOf('marked', $pane);

        return $payload instanceof MarkedEntries ? $payload : new MarkedEntries();
    }

    public function tree(?int $pane = null): ?BrowserTree
    {
        $payload = $this->payloadOf('tree', $pane);

        return $payload instanceof BrowserTree ? $payload : null;
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
