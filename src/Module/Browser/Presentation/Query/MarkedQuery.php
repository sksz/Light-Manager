<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Presentation\BrowserPanes;

/**
 * `browser.marked [panel]` — **nazwy** zaznaczonych wpisów.
 *
 * To jest ta kwerenda, dla której zaznaczenie wielokrotne z kroku 43 po raz
 * pierwszy wychodzi poza przeglądarkę. `ModuleContext` niesie o zbiorze trzy
 * liczby — ile, ile bajtów, ile katalogów — i **żadnej nazwy**; policzyć czynność
 * na zaznaczeniu (wdrożenie kilku manifestów, wysłanie kilku plików) da się
 * dopiero z nazwami.
 *
 * Pusty zbiór oddaje **pusty wynik**, a nie wpis pod kursorem, i to jest różnica
 * wobec reguły 15c, która obowiązuje **czynności**: tam „nic nie zaznaczono”
 * znaczy „wpis pod kursorem”, bo czynność musi wiedzieć, na czym pracować. Tu
 * odpowiedź ma być prawdziwa: zaznaczono zero wpisów. Kto chce wpisu pod
 * kursorem, pyta `browser.selection`.
 */
final class MarkedQuery implements QueryInterface
{
    public function __construct(
        private readonly BrowserPanes $panes,
    ) {
    }

    public function name(): string
    {
        return BrowserSettings::ID . '.marked';
    }

    public function descriptionKey(): string
    {
        return 'module.' . BrowserSettings::ID . '.query.marked';
    }

    public function arguments(): array
    {
        return [PaneArgument::declaration()];
    }

    public function generation(): int
    {
        return self::VOLATILE;
    }

    public function ask(CommandInput $input): QueryResult
    {
        $index = PaneArgument::from($input) ?? ($this->panes->focusesSecond() ? 1 : 0);
        // Reguła „drzewo zbioru nie widzi" (D80 nr 9) stoi w panelach, nie tutaj:
        // kwerenda ma odpowiadać to samo, co pokazuje pas ścieżki.
        $marked = $this->panes->markedOf($index);
        $directory = rtrim($this->panes->pane($index)[0]->directory()->path()->value, '/');

        return QueryResult::owned(BrowserSettings::ID, $marked, static function () use ($marked, $directory): array {
            $rows = [];

            foreach ($marked->names() as $name) {
                $rows[] = ['name' => $name, 'path' => $directory . '/' . $name];
            }

            return $rows;
        });
    }
}
