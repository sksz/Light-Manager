<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Command\SuggestionSource;
use LightManager\Application\Command\SuggestsArguments;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\Addresses;
use LightManager\Module\AddressBook\Presentation\AddressBookQueries;
use LightManager\Module\AddressBook\Presentation\Suggestions;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\Overlay\ConfirmOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `address-book.remove <wpis>` — usuwa wpis wraz z wartościami **wszystkich**
 * jego rozdziałów (krok 60).
 *
 * **Pyta zawsze** i nie ma na to pozycji ustawień: z ekranu książki nie widać,
 * kto się na wpis powołuje, a usunięcie wpisu bywa cudzą awarią — wpis tunelowy
 * modułu Dockera wskazuje właśnie taki wpis polem rodzaju `entry`.
 */
final class RemoveCommand implements CommandInterface, SuggestsArguments, OpensOverlay
{
    public const ENTRY = 'entry';

    public function __construct(
        private readonly Addresses $addresses,
        private readonly AddressBookQueries $reader,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.remove';
    }

    public function descriptionKey(): string
    {
        return AddressBookSettings::key('command.remove');
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ENTRY,
                AddressBookSettings::key('argument.entry'),
                suggestions: SuggestionSource::OnDemand,
            ),
        ];
    }

    public function suggestions(string $argument, string $prefix): array
    {
        return $argument === self::ENTRY ? Suggestions::entries($this->reader, $prefix) : [];
    }

    public function overlayFor(CommandInput $input): ?OverlayOutcome
    {
        $id = Suggestions::idOf($input->text(self::ENTRY));
        $entry = $this->reader->entry($id);

        if ($entry === null) {
            // `null` znaczy „wykonaj mnie zwyczajnie", a `execute()` oddaje wtedy
            // zdanie o nieznanym wpisie — pytanie o usunięcie czegoś, czego nie
            // ma, byłoby pytaniem bez treści.
            return null;
        }

        return OverlayOutcome::replace(new ConfirmOverlay(
            AddressBookSettings::key('confirm.remove'),
            ['entry' => $entry->label()],
            fn (): OverlayOutcome => OverlayOutcome::close($this->removed($id, $entry->label())),
            $this->translator,
        ));
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $id = Suggestions::idOf($input->text(self::ENTRY));

        return CommandOutcome::done(Message::error(
            $this->translator->translate(AddressBookSettings::key('message.unknown'), ['entry' => $id]),
        ));
    }

    private function removed(string $id, string $label): Message
    {
        $this->addresses->remove($id);

        return Message::info(
            $this->translator->translate(AddressBookSettings::key('message.removed'), ['entry' => $label]),
        );
    }
}
