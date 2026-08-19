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

/**
 * `address-book.chapter <rozdział> [klucz-tytułu]` — zapowiedź użycia rozdziału
 * (krok 60).
 *
 * **To jest połowa całej drogi, którą pola trafiają do wpisu** — druga połowa
 * to `address-book.field`. Droga jest **jednostronna**: deklarujący podaje dwa
 * napisy, a książka **po nic nie wraca**. Tu leży różnica wobec książki
 * usuniętej w kroku poprzednim, gdzie ta sama komenda wskazywała **kwerendę
 * zakładającego**, z której książka czytała spis pól — czyli oddzwaniała, i to
 * z tej jednej rzeczy brały się wszystkie kłopoty z kolejnością.
 *
 * Deklaracja **nie tworzy właściciela** (D104 nr 2): dwa moduły wolno
 * zadeklarować ten sam rozdział, a rozdział zadeklarowany przez kogokolwiek
 * jest czytelny i zapisywalny dla wszystkich.
 *
 * Komenda jest **idempotentna**, bo pada w takcie modułu — trzydzieści razy na
 * sekundę. Deklarujący nie musi pamiętać, czy już prosił, i przy leniwym
 * składaniu modułów nie miałby jak.
 *
 * Stoi w rejestrze komend jak każda inna, więc widzi ją także użytkownik
 * w oknie komend — i to jest w porządku: rozdział założony ręcznie znika przy
 * następnym uruchomieniu, bo deklaracji nie zapisuje się na dysk.
 */
final class ChapterCommand implements CommandInterface
{
    public const CHAPTER = 'chapter';

    public const TITLE = 'title';

    public function __construct(
        private readonly Addresses $addresses,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.chapter';
    }

    public function descriptionKey(): string
    {
        return AddressBookSettings::key('command.chapter');
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(self::CHAPTER, AddressBookSettings::key('argument.chapter')),
            new CommandArgument(self::TITLE, AddressBookSettings::key('argument.title'), required: false),
        ];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $chapter = trim($input->text(self::CHAPTER));
        $title = trim($input->text(self::TITLE));

        if (!$this->addresses->declareChapter($chapter, $title === '' ? 'module.' . $chapter . '.name' : $title)) {
            return CommandOutcome::stay(Message::warning(
                $this->translator->translate(AddressBookSettings::key('chapter.invalid'), ['chapter' => $chapter]),
            ));
        }

        return CommandOutcome::done();
    }
}
