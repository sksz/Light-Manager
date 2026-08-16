<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Presentation\BrowserPanes;
use LightManager\Presentation\Ui\Component\TreeNode;

/**
 * `browser.tree [panel]` — drzewo katalogów panelu, spłaszczone do wierszy.
 *
 * Siódma kwerenda przeglądarki i jedyna, która oddaje **widok**, a nie zawartość
 * miejsca: `browser.entries` mówi, co leży w katalogu, a to — co użytkownik ma
 * rozwinięte i gdzie w tym stoi. Różnica jest widoczna od kroku 31: gałęzie
 * czyta się na żądanie, więc drzewo pokazuje **mniej** niż suma katalogów i to
 * jest jego treść, a nie brak.
 *
 * Odpowiedź jest **spłaszczona**, bo taka jest w module (D68): komponent dostaje
 * listę `TreeNode`ów bez wskaźnika na rodzica, a kwerenda oddaje dokładnie to,
 * co komponent rysuje — z głębokością policzoną z prowadnic.
 */
final class TreeQuery implements QueryInterface
{
    public function __construct(
        private readonly BrowserPanes $panes,
    ) {
    }

    public function name(): string
    {
        return BrowserSettings::ID . '.tree';
    }

    public function descriptionKey(): string
    {
        return 'module.' . BrowserSettings::ID . '.query.tree';
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
        $tree = $this->panes->tree($index);
        $cursor = $tree->cursorIndex();

        return QueryResult::owned(BrowserSettings::ID, $tree, static function () use ($tree, $cursor): array {
            $rows = [];

            foreach ($tree->nodes() as $position => $node) {
                $rows[] = self::describe($node, $position === $cursor);
            }

            return $rows;
        });
    }

    /** @return array<string, string|int|bool> */
    private static function describe(TreeNode $node, bool $selected): array
    {
        return [
            'path' => $node->key,
            'name' => $node->label,
            'depth' => $node->depth(),
            'expanded' => $node->expanded,
            'selected' => $selected,
        ];
    }
}
