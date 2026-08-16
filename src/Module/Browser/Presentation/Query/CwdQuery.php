<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Presentation\BrowserPanes;

/**
 * `browser.cwd` — ścieżki **obu** paneli wraz z tym, który jest czynny.
 *
 * Kwerenda odwołanego zastrzeżenia, tak samo jak `browser.selection` (D92 nr 8),
 * i tak samo jak tam różnica wobec `ModuleContext` została: kontekst niesie
 * **jedną** ścieżkę, a panele są dwa od kroku 24. Kopiowanie z panelu do panelu
 * jest dziś czynnością przeglądarki, bo tylko ona wie o obu — a to jest właśnie
 * ten rodzaj wiedzy, który kwerenda ma otwierać.
 */
final class CwdQuery implements QueryInterface
{
    public function __construct(
        private readonly BrowserPanes $panes,
    ) {
    }

    public function name(): string
    {
        return BrowserSettings::ID . '.cwd';
    }

    public function descriptionKey(): string
    {
        return 'module.' . BrowserSettings::ID . '.query.cwd';
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
                    'path' => $state->directory()->path()->value,
                    'focused' => $focused,
                ];
            }

            return $rows;
        });
    }
}
