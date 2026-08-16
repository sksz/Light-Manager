<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Presentation\BrowserPanes;

/**
 * `browser.selection [panel]` — wpis pod kursorem wraz z tym, co o nim wiadomo
 * bez dotykania dysku.
 *
 * **Kwerenda powstała z odwołania zastrzeżenia trzeciego** (D92 nr 8): do tego
 * kroku nie wolno jej było napisać, bo `ModuleContext` niesie nazwę zaznaczenia
 * i byłaby drugą drogą do tej samej danej. Odkąd rejestr jest jedyną drogą
 * odczytu, drugiej drogi nie ma — a różnica wobec kontekstu została i jest
 * rzeczywista: kontekst mówi o panelu **czynnym**, kwerenda o **wskazanym**.
 */
final class SelectionQuery implements QueryInterface
{
    public function __construct(
        private readonly BrowserPanes $panes,
    ) {
    }

    public function name(): string
    {
        return BrowserSettings::ID . '.selection';
    }

    public function descriptionKey(): string
    {
        return 'module.' . BrowserSettings::ID . '.query.selection';
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
        $index = PaneArgument::from($input);
        $state = $index === null ? $this->panes->focused() : $this->panes->pane($index)[0];
        $directory = $state->directory();
        $entry = $directory->selectedEntry();

        if ($entry === null) {
            return QueryResult::empty();
        }

        $path = rtrim($directory->path()->value, '/') . '/' . $entry->name;

        return QueryResult::owned(BrowserSettings::ID, $entry, static fn (): array => [[
            'name' => $entry->name,
            'path' => $path,
            'kind' => strtolower($entry->type->name),
            'bytes' => $entry->sizeInBytes,
            'modified' => $entry->modifiedAt ?? -1,
            'permissions' => $entry->permissions ?? -1,
            'hidden' => $entry->isHidden(),
        ]]);
    }
}
