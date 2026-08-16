<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Presentation\BrowserPanes;

/**
 * `browser.panes` — jak wygląda podział: który panel jest czynny, co pokazuje
 * i czym jest zawężony.
 *
 * Odpowiada na pytanie „jak użytkownik teraz patrzy”, którego nie zadaje żadna
 * inna kwerenda: `browser.cwd` mówi **gdzie**, `browser.entries` — **co**,
 * a to jest jedyne miejsce, z którego widać widok (lista albo drzewo), filtr
 * i liczbę zaznaczonych w obu panelach naraz.
 */
final class PanesQuery implements QueryInterface
{
    public function __construct(
        private readonly BrowserPanes $panes,
    ) {
    }

    public function name(): string
    {
        return BrowserSettings::ID . '.panes';
    }

    public function descriptionKey(): string
    {
        return 'module.' . BrowserSettings::ID . '.query.panes';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return self::VOLATILE;
    }

    public function ask(CommandInput $input): QueryResult
    {
        $panes = $this->panes;

        return QueryResult::lazy(static function () use ($panes): array {
            $rows = [];

            foreach ([0, 1] as $index) {
                [$state, , $focused] = $panes->pane($index);
                $rows[] = [
                    'pane' => $index,
                    'view' => $panes->showsTree($index) ? 'tree' : 'list',
                    'filter' => $state->filter()->value,
                    'entries' => count($state->directory()->entries()),
                    // Liczba wpisów **katalogu pełnego**, a nie widocznej listy:
                    // zbiór zaznaczonych przeżywa zawężenie filtrem (D80 nr 4),
                    // więc „12 z 340" mówi prawdę, a „12 z 30" kłamałoby.
                    'full' => $state->fullCount(),
                    'marked' => $state->marked()->count(),
                    'hidden' => $state->showsHiddenEntries(),
                    'focused' => $focused,
                ];
            }

            return $rows;
        });
    }
}
