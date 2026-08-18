<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandArgumentKind;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Command\SuggestionSource;
use LightManager\Application\Command\SuggestsArguments;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\Addresses;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\Overlay\ConfirmOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `address-book.remove <wpis>` — usuwa wpis (krok 60).
 *
 * **Pyta oknem, bo usunięcie wpisu bywa cudzą awarią**: identyfikator trzyma
 * u siebie wpis tunelowy modułu Dockera i pamięć katalogów modułu sesji
 * zdalnej, a książka nie widzi, kto się na nią powołuje. Zdolność `OpensOverlay`
 * (krok 47) jest tu więc czynnością, nie ozdobą.
 *
 * Argument przyjmuje identyfikator albo **jednoznaczną** nazwę; podpowiedzi
 * pokazują jedno i drugie, bo identyfikatora nikt nie pamięta.
 */
final class RemoveCommand implements CommandInterface, SuggestsArguments, OpensOverlay
{
    public const ARGUMENT = 'entry';

    public function __construct(
        private readonly Addresses $addresses,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.remove';
    }

    public function descriptionKey(): string
    {
        return 'module.' . AddressBookSettings::ID . '.command.remove';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ARGUMENT,
                'module.' . AddressBookSettings::ID . '.argument.entry',
                CommandArgumentKind::Text,
                suggestions: SuggestionSource::OnDemand,
            ),
        ];
    }

    public function suggestions(string $argument, string $prefix): array
    {
        if ($argument !== self::ARGUMENT) {
            return [];
        }

        $matches = [];

        foreach ($this->addresses->entries() as $entry) {
            foreach ([$entry->id, $entry->name] as $candidate) {
                if ($candidate !== '' && str_starts_with($candidate, $prefix)) {
                    $matches[] = $candidate;
                }
            }
        }

        return $matches;
    }

    public function overlayFor(CommandInput $input): ?OverlayOutcome
    {
        $entry = $this->addresses->resolve(trim($input->text(self::ARGUMENT)));

        if ($entry === null) {
            return null;
        }

        $id = $entry->id;
        $label = $entry->label();

        return OverlayOutcome::replace(new ConfirmOverlay(
            'module.' . AddressBookSettings::ID . '.confirm.remove',
            ['name' => $label],
            function () use ($id, $label): OverlayOutcome {
                $this->addresses->remove($id);

                return OverlayOutcome::close(Message::info($this->translator->translate(
                    'module.' . AddressBookSettings::ID . '.removed',
                    ['name' => $label],
                )));
            },
            $this->translator,
        ));
    }

    /** Wykonanie bez okna zachodzi wyłącznie wtedy, gdy wpisu nie ma. */
    public function execute(CommandInput $input): CommandOutcome
    {
        return CommandOutcome::stay(Message::warning($this->translator->translate(
            'module.' . AddressBookSettings::ID . '.unknown',
            ['entry' => trim($input->text(self::ARGUMENT))],
        )));
    }
}
