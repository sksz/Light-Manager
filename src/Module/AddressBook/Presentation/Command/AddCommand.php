<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\Addresses;
use LightManager\Module\AddressBook\Domain\Exception\InvalidAddressEntryException;
use LightManager\Module\AddressBook\Presentation\EntryFlow;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `address-book.add [nazwa]` — nowy wpis (krok 60).
 *
 * Wpis powstaje **z samą nazwą**, bo poza nią i identyfikatorem nie ma nic
 * własnego (D104 nr 5); pola dokłada się potem, w zakładce rozdziału albo
 * komendą `address-book.set`. Nazwa **wolno pusta** — tożsamości nie niesie.
 *
 * Bez argumentu komenda otwiera pole tekstowe (`OpensOverlay`, krok 47), więc
 * `F7` na ekranie i wpisanie komendy prowadzą **jedną** czynność, a nie dwie
 * (11n).
 *
 * Identyfikatora nowego wpisu komenda **nie może oddać** — komenda oddaje
 * zdanie, nie daną. Kto go potrzebuje (migracja starej książki), pyta kwerendą
 * `address-book.last` (D105 nr 6); użytkownik widzi go w zdaniu i w tabeli.
 */
final class AddCommand implements CommandInterface, OpensOverlay
{
    public const NAME = 'name';

    public const CHAPTER = 'chapter';

    public function __construct(
        private readonly Addresses $addresses,
        private readonly EntryFlow $flow,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.add';
    }

    public function descriptionKey(): string
    {
        return AddressBookSettings::key('command.add');
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(self::NAME, AddressBookSettings::key('argument.name'), required: false),
            new CommandArgument(self::CHAPTER, AddressBookSettings::key('argument.chapter'), required: false),
        ];
    }

    public function overlayFor(CommandInput $input): ?OverlayOutcome
    {
        $chapter = trim($input->text(self::CHAPTER));

        if (trim($input->text(self::NAME)) !== '') {
            return $chapter === '' ? null : $this->chain($this->addedId(trim($input->text(self::NAME))), $chapter);
        }

        // `replace()`, a nie otwarcie nad spodem: stos ma jedno piętro, a oknem
        // stojącym w tej chwili jest okno komend, które właśnie ustępuje miejsca.
        return OverlayOutcome::replace(new PromptOverlay(
            AddressBookSettings::key('prompt.name'),
            [],
            '',
            fn (string $name): OverlayOutcome => $this->accepted($name, $chapter),
            $this->translator,
            AddressBookSettings::key('prompt.name.field'),
        ));
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        return CommandOutcome::done($this->added(trim($input->text(self::NAME))));
    }

    /**
     * Po nazwie — od razu **pola rozdziału**, gdy wpis powstaje z jego zakładki.
     *
     * Bez tego przejścia wpis dopisany na zakładce rozdziału **znikałby zaraz po
     * dopisaniu**: do rozdziału należy się przez wartości, a świeży wpis nie ma
     * żadnej. Łańcuch domyka to w jednej czynności — nazwa, potem pola — zamiast
     * kazać użytkownikowi szukać wpisu w zakładce „wszystkie".
     */
    private function accepted(string $name, string $chapter): OverlayOutcome
    {
        if ($chapter === '') {
            return OverlayOutcome::close($this->added($name));
        }

        return $this->chain($this->addedId($name), $chapter) ?? OverlayOutcome::close($this->added($name));
    }

    private function chain(string $entry, string $chapter): ?OverlayOutcome
    {
        if ($entry === '') {
            return null;
        }

        $overlay = $this->flow->begin($entry, $chapter);

        return $overlay === null ? null : OverlayOutcome::replace($overlay);
    }

    /** Dopisuje wpis i oddaje jego identyfikator; pusty, gdy się nie udało. */
    private function addedId(string $name): string
    {
        try {
            return $this->addresses->add($name)->id;
        } catch (InvalidAddressEntryException) {
            return '';
        }
    }

    private function added(string $name): Message
    {
        try {
            $entry = $this->addresses->add($name);
        } catch (InvalidAddressEntryException $exception) {
            return Message::error($this->translator->translate(
                $exception->problemKey(),
                $exception->problemParameters(),
            ));
        }

        return Message::info($this->translator->translate(AddressBookSettings::key('message.added'), [
            'entry' => $entry->label(),
            'id' => $entry->id,
        ]));
    }
}
