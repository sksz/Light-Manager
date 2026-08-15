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
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Presentation\EntryTransfer;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `browser.move [ścieżka]` — przeniesienie zaznaczonego wpisu (krok 42).
 *
 * Bliźniak `browser.copy` i różni się od niego jedną wartością logiczną — tą,
 * która w `EntryTransfer` rozstrzyga, czy źródło znika po zapisaniu celu.
 * Osobna komenda, a nie przełącznik przy tamtej, bo w menu i w oknie komend
 * czynności są dwie: „skopiuj” i „przenieś” to nie jest jedna rzecz z opcją.
 */
final class MoveCommand implements CommandInterface, AppliesToSelection, OpensOverlay
{
    private const ARGUMENT = 'path';

    public function __construct(
        private readonly EntryTransfer $transfers,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return BrowserSettings::ID . '.move';
    }

    public function descriptionKey(): string
    {
        return 'module.' . BrowserSettings::ID . '.command.move';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ARGUMENT,
                'module.' . BrowserSettings::ID . '.argument.path',
                CommandArgumentKind::Path,
                required: false,
            ),
        ];
    }

    public function overlayFor(CommandInput $input): OverlayOutcome
    {
        return $this->transfers->moveRequest(
            $input->has(self::ARGUMENT) ? $input->text(self::ARGUMENT) : null,
        );
    }

    public function appliesTo(ModuleContext $context): bool
    {
        return $context->selection !== null;
    }

    public function inputFor(ModuleContext $context): CommandInput
    {
        return new CommandInput();
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        return CommandOutcome::stay(Message::error($this->translator->translate(
            'module.' . BrowserSettings::ID . '.transfer.needsOverlay',
        )));
    }
}
