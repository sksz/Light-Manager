<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation\Command;

use LightManager\Application\Command\AppliesToSelection;
use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandArgumentKind;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Ssh\Application\SshSettings;
use LightManager\Module\Ssh\Presentation\RemoteTransfer;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `ssh.get [ścieżka]` — pobranie zaznaczonego pliku zdalnego (krok 50).
 *
 * Ścieżka jest **opcjonalna**: bez niej otwiera się to samo okno, co pod `F5`,
 * z katalogiem przeglądarki jako treścią początkową; z nią praca rusza od razu.
 * Wzorzec ten sam, co w `browser.copy` — komenda niczego nie robi sama, tylko
 * prowadzi w `RemoteTransfer`, czyli tam, gdzie prowadzi klawisz (11n).
 *
 * **Do menu `F9` wchodzi wyłącznie przy zaznaczeniu zdalnym** i to jest
 * pierwszy w projekcie użytek z pochodzenia kontekstu (krok 49) poza modułem
 * opisu pliku: pobieranie pliku, na którym stoi kursor **przeglądarki**, nie
 * znaczyłoby nic — ten plik już tu jest.
 */
final class DownloadCommand implements CommandInterface, AppliesToSelection, OpensOverlay
{
    private const ARGUMENT = 'path';

    public function __construct(
        private readonly RemoteTransfer $transfers,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return SshSettings::ID . '.get';
    }

    public function descriptionKey(): string
    {
        return 'module.' . SshSettings::ID . '.command.get';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ARGUMENT,
                'module.' . SshSettings::ID . '.argument.target',
                CommandArgumentKind::Path,
                required: false,
            ),
        ];
    }

    public function overlayFor(CommandInput $input): OverlayOutcome
    {
        return $this->transfers->downloadRequest(
            $input->has(self::ARGUMENT) ? $input->text(self::ARGUMENT) : null,
        );
    }

    public function appliesTo(ModuleContext $context): bool
    {
        return $this->transfers->canTransfer()
            && $context->isRemote()
            && $context->kind === ContextEntryKind::File;
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
            'module.' . SshSettings::ID . '.transfer.needsOverlay',
        )));
    }
}
