<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandArgumentKind;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Domain\Exception\DomainException;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Presentation\EntryOperations;

/**
 * `browser.mkdir <nazwa>` — nowy katalog w katalogu panelu czynnego (krok 41).
 *
 * Siostra `browser.rename` i tak samo jak ona bierze nazwę **z wiersza**, a nie
 * z okna: komenda okna nakładanego otworzyć nie umie (D75, rozstrzygnięcie 5).
 * Różni się jednym: nie dotyczy zaznaczenia w ogóle, więc pytanie o pozycję
 * w menu kontekstowym nawet się nie zaczyna — nowy katalog odnosi się do miejsca,
 * w którym użytkownik stoi.
 *
 * Nazwa jest **nazwą, nie ścieżką**: ukośnik w niej jest błędem, a nie
 * zaproszeniem do utworzenia dwóch poziomów naraz. Pilnuje tego `EntryName`,
 * i to ono, a nie komenda, wie, co jest poprawną nazwą.
 */
final class MakeDirectoryCommand implements CommandInterface
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
            ),
        ];
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
