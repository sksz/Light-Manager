<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Command;

use LightManager\Application\Command\AppliesToSelection;
use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandArgumentKind;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Domain\Exception\DomainException;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Presentation\EntryOperations;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `browser.mkdir [nazwa]` — nowy katalog w katalogu panelu czynnego (krok 41).
 *
 * Siostra `browser.rename` i po kroku 47 (D78) tak samo jak ona: nazwa podana
 * w wierszu tworzy katalog od razu, nazwa pominięta otwiera okno, które otwiera
 * `F7`.
 *
 * **Różni się jednym i to jest miejsce, w którym krok 47 przerysował granicę
 * z D69.** Nowy katalog nie dotyczy zaznaczenia — odnosi się do katalogu panelu,
 * który `ModuleContext` niesie osobnym polem. Menu pokazuje go mimo to, bo jego
 * granica brzmi odtąd: **czynności zmieniające zawartość miejsca, w którym stoi
 * zaznaczenie, a nie sposób oglądania tego miejsca**. Przy takiej granicy
 * `browser.hidden` i `browser.tree` zostają poza menu dokładnie tak, jak
 * rozstrzygnął krok 32.
 *
 * Nazwa jest **nazwą, nie ścieżką**: ukośnik w niej jest błędem, a nie
 * zaproszeniem do utworzenia dwóch poziomów naraz. Pilnuje tego `EntryName`,
 * i to ono, a nie komenda, wie, co jest poprawną nazwą.
 */
final class MakeDirectoryCommand implements CommandInterface, AppliesToSelection, OpensOverlay
{
    private const ARGUMENT = 'name';

    public function __construct(
        private readonly EntryOperations $entries,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return BrowserSettings::ID . '.mkdir';
    }

    public function descriptionKey(): string
    {
        return 'module.' . BrowserSettings::ID . '.command.mkdir';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ARGUMENT,
                'module.' . BrowserSettings::ID . '.argument.name',
                CommandArgumentKind::Text,
                required: false,
            ),
        ];
    }

    /** Bez nazwy — okno z pustą treścią; z nazwą — czynność bez okna (krok 47). */
    public function overlayFor(CommandInput $input): ?OverlayOutcome
    {
        return $input->has(self::ARGUMENT) ? null : $this->entries->directoryRequest();
    }

    /** Katalog panelu jest zawsze — pozycja znika tylko wtedy, gdy kontekstu nie ma. */
    public function appliesTo(ModuleContext $context): bool
    {
        return $context->path !== '';
    }

    public function inputFor(ModuleContext $context): CommandInput
    {
        return new CommandInput();
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        try {
            $message = $this->entries->createDirectory($input->text(self::ARGUMENT));
        } catch (DomainException $problem) {
            // Okno komend **zostaje otwarte** wraz z wpisanym wierszem: nazwa
            // zajęta albo niepoprawna jest czymś, co użytkownik ma gdzie poprawić
            // (ta sama reguła, którą kieruje się `browser.jump`).
            return CommandOutcome::stay($this->problem($problem));
        }

        return CommandOutcome::done($message);
    }

    private function problem(DomainException $problem): Message
    {
        if ($problem instanceof DescribesProblem) {
            return Message::error($this->translator->translate(
                $problem->problemKey(),
                $problem->problemParameters(),
            ));
        }

        return Message::error($this->translator->translate('problem.unexpected'));
    }
}
