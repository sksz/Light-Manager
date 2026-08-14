<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Infrastructure\Diagnostics\FrameDumpService;

/**
 * `core.dump` — zapisuje **następną** klatkę do pliku: prymitywy i obraz
 * (krok 38, D64).
 *
 * Komenda niczego nie zapisuje sama i to jest jej cała treść: wykonuje się
 * w chwili obsługi klawisza, czyli **zanim** klatka powstanie, a okno komend
 * stoi jeszcze na wierzchu. Zostawia więc zamówienie, które zbierze renderer
 * po narysowaniu najbliższej klatki — tej już bez okna komend, czyli tej,
 * o którą użytkownikowi chodziło.
 *
 * Ścieżka jest argumentem **nieobowiązkowym**: bez niej zrzut ląduje
 * w katalogu tymczasowym pod nazwą z datą, bo komenda wpisywana w pośpiechu,
 * gdy coś na ekranie wygląda źle, nie powinna wymagać wymyślania nazwy.
 */
final class DumpFrameCommand implements CommandInterface
{
    private const ARGUMENT = 'path';

    public function __construct(
        private readonly TranslatorPort $translator,
        private readonly ?FrameDumpService $dumps = null,
    ) {
    }

    public function name(): string
    {
        return 'core.dump';
    }

    public function descriptionKey(): string
    {
        return 'command.core.dump';
    }

    public function arguments(): array
    {
        return [new CommandArgument(self::ARGUMENT, 'command.argument.path', required: false)];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $path = $input->has(self::ARGUMENT) ? $input->text(self::ARGUMENT) : $this->defaultPath();

        ($this->dumps ?? FrameDumpService::getInstance())->request($path);

        // Okno komend zamyka się razem z wykonaniem, więc zamówiona klatka jest
        // już czysta. Komunikat mówi o **zamówieniu**, a nie o zapisie: pliku
        // jeszcze nie ma i obiecywanie go teraz byłoby kłamstwem o jedną klatkę.
        return CommandOutcome::done(Message::info(
            $this->translator->translate('command.dump.requested', ['file' => $path]),
        ));
    }

    private function defaultPath(): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'light-manager-' . date('Ymd-His');
    }
}
