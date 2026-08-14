<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Command;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Presentation\BrowserPanes;

/**
 * `browser.tree` — panel z ogniskiem jako drzewo albo z powrotem jako lista
 * (krok 32).
 *
 * Nazwa dla czynności, którą przeglądarka ma od kroku 31 pod `Ctrl`+`T`.
 * Wchodzi z tego samego powodu, co `browser.hidden`: skrót `Ctrl`+litera działa
 * wyłącznie dopóty, dopóki litery nie zajmie żaden moduł, a nazwa w rejestrze
 * jest od takiej kolizji niezależna.
 *
 * Zdolności `AppliesToSelection` nie deklaruje — widok panelu nie jest
 * czynnością na zaznaczeniu.
 */
final class TreeCommand implements CommandInterface
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
        return 'module.' . BrowserSettings::ID . '.command.tree';
    }

    public function arguments(): array
    {
        return [];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $this->panes->toggleTree();

        return CommandOutcome::done();
    }
}
