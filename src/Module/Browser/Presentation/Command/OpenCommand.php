<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Command;

use LightManager\Application\Command\AppliesToSelection;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\Exception\DomainException;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Application\UseCase\NavigateIntoDirectoryUseCase;
use LightManager\Module\Browser\Presentation\BrowserPanes;
use LightManager\Module\Browser\Presentation\BrowserQueries;

/**
 * `browser.open` — wejście do **zaznaczonego** katalogu (krok 32).
 *
 * Siostra `browser.jump`, różniąca się od niej dokładnie tym, skąd bierze cel:
 * skok czyta ścieżkę z wiersza, a ta komenda — z zaznaczenia. Stąd dwie
 * konsekwencje. Pierwsza: to **ona**, a nie skok, deklaruje
 * `AppliesToSelection`, bo w menu kontekstowym dwie pozycje robiące to samo
 * byłyby wyłącznie pytaniem, którą wybrać. Druga: nie ma argumentu, więc
 * w oknie komend uruchamia się samą nazwą.
 *
 * Zaznaczenie bierze z `BrowserPanes::focusedDirectory()`, czyli z tego samego
 * miejsca, z którego bierze je kontekst sesji — a to znaczy, że komenda działa
 * także wtedy, gdy panel pokazuje drzewo, i wchodzi wtedy do węzła pod
 * kursorem, nie do zaznaczenia listy sprzed przełączenia widoku.
 *
 * Wyjątku nie wypuszcza: klawisze idą do ekranu przez `InputHandler`, który
 * łapie `DomainException`, ale komenda wywołana z okna nie ma nad sobą tego
 * łapacza (precedens `JumpCommand` z kroku 20).
 */
final class OpenCommand implements CommandInterface, AppliesToSelection
{
    public function __construct(
        private readonly BrowserPanes $panes,
        /** Odczyt danych przeglądarki — przez rejestr kwerend (krok 53, D92 nr 3). */
        private readonly BrowserQueries $queries,
        private readonly NavigateIntoDirectoryUseCase $navigateInto,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return BrowserSettings::ID . '.open';
    }

    public function descriptionKey(): string
    {
        return 'module.' . BrowserSettings::ID . '.command.open';
    }

    public function arguments(): array
    {
        return [];
    }

    /** Wejść da się wyłącznie do katalogu — na pliku pozycji w menu nie ma. */
    public function appliesTo(ModuleContext $context): bool
    {
        return $context->kind === ContextEntryKind::Directory;
    }

    public function inputFor(ModuleContext $context): CommandInput
    {
        return new CommandInput();
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $pane = $this->panes->focused();

        try {
            $entered = $this->navigateInto->execute(
                $this->queries->pointedDirectory(),
                $pane->showsHiddenEntries(),
            );
        } catch (DomainException) {
            return CommandOutcome::done(Message::error(
                $this->translator->translate('module.' . BrowserSettings::ID . '.open.failed'),
            ));
        }

        if ($entered === null) {
            // Zaznaczenie nie jest katalogiem: w menu takiej pozycji nie ma,
            // ale w oknie komend nazwę wolno wpisać na czymkolwiek.
            return CommandOutcome::done(Message::info(
                $this->translator->translate('module.' . BrowserSettings::ID . '.open.notDirectory'),
            ));
        }

        $pane->enter($entered);

        return CommandOutcome::done();
    }
}
