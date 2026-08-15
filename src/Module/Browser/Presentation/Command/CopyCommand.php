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
 * `browser.copy [ścieżka]` — skopiowanie zaznaczonego wpisu (krok 42).
 *
 * Ścieżka jest **opcjonalna**: bez niej otwiera się to samo okno, co pod `F5`,
 * z katalogiem drugiego panelu jako treścią początkową; z nią praca rusza od
 * razu. Wzorzec ten sam, co w `browser.delete` z kroku 47 — komenda niczego nie
 * robi sama, tylko prowadzi w `EntryTransfer`, czyli tam, gdzie prowadzi klawisz.
 *
 * `execute()` ma treść niepodobną do reszty i z tego samego powodu, co przy
 * usuwaniu: kopiowanie **potrzebuje okna**, żeby pokazać postęp i zapytać
 * o kolizję. Wołający, który okna otworzyć nie umie, dostaje zdanie zamiast
 * pracy chodzącej bez śladu na ekranie. Dziś takiego wołającego w aplikacji nie
 * ma — okno komend i menu pytają wpierw o zdolność.
 */
final class CopyCommand implements CommandInterface, AppliesToSelection, OpensOverlay
{
    private const ARGUMENT = 'path';

    public function __construct(
        private readonly EntryTransfer $transfers,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return BrowserSettings::ID . '.copy';
    }

    public function descriptionKey(): string
    {
        return 'module.' . BrowserSettings::ID . '.command.copy';
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

    /**
     * Zawsze coś oddaje: okno ze ścieżką, okno pracy albo samo zdanie — gdy nie ma
     * czego kopiować albo gdy praca zmieściła się w pierwszym kawałku.
     */
    public function overlayFor(CommandInput $input): OverlayOutcome
    {
        return $this->transfers->copyRequest(
            $input->has(self::ARGUMENT) ? $input->text(self::ARGUMENT) : null,
        );
    }

    public function appliesTo(ModuleContext $context): bool
    {
        return $context->selection !== null;
    }

    public function inputFor(ModuleContext $context): CommandInput
    {
        // Menu nie ma pola tekstowego, więc pozycja wchodzi **bez** ścieżki —
        // i właśnie dlatego otwiera okno, w którym da się ją zobaczyć i poprawić.
        return new CommandInput();
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        return CommandOutcome::stay(Message::error($this->translator->translate(
            'module.' . BrowserSettings::ID . '.transfer.needsOverlay',
        )));
    }
}
