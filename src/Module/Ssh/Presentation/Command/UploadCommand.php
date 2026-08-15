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
 * `ssh.put [ścieżka]` — wysłanie zaznaczonego pliku lokalnego (krok 50).
 *
 * Bliźniak `ssh.get` w drugą stronę, z jedną różnicą wynikającą wprost
 * z pochodzenia kontekstu: do menu wchodzi przy zaznaczeniu **lokalnym**, bo
 * wysyła się to, na czym stoi kursor przeglądarki. Ścieżka w argumencie jest
 * katalogiem **zdalnym**; bez niej okno podpowiada ten, który widać w panelu.
 */
final class UploadCommand implements CommandInterface, AppliesToSelection, OpensOverlay
{
    private const ARGUMENT = 'path';

    public function __construct(
        private readonly RemoteTransfer $transfers,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return SshSettings::ID . '.put';
    }

    public function descriptionKey(): string
    {
        return 'module.' . SshSettings::ID . '.command.put';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ARGUMENT,
                'module.' . SshSettings::ID . '.argument.remoteTarget',
                CommandArgumentKind::Path,
                required: false,
            ),
        ];
    }

    public function overlayFor(CommandInput $input): OverlayOutcome
    {
        return $this->transfers->uploadRequest(
            $input->has(self::ARGUMENT) ? $input->text(self::ARGUMENT) : null,
        );
    }

    public function appliesTo(ModuleContext $context): bool
    {
        return $this->transfers->canTransfer()
            && !$context->isRemote()
            && $context->kind === ContextEntryKind::File;
    }

    public function inputFor(ModuleContext $context): CommandInput
    {
        return new CommandInput();
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        return CommandOutcome::stay(Message::error($this->translator->translate(
            'module.' . SshSettings::ID . '.transfer.needsOverlay',
        )));
    }
}
