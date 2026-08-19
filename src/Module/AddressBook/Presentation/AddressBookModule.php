<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Module\DeclaresEvents;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleSettingsTab;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Application\Module\NeedsTick;
use LightManager\Application\Module\ProvidesCommands;
use LightManager\Application\Module\ProvidesQueries;
use LightManager\Application\Module\ProvidesSettingsTab;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Module\AddressBook\Application\AddressBookEvent;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\Addresses;
use LightManager\Module\AddressBook\Application\Port\AddressBookPort;
use LightManager\Module\AddressBook\Infrastructure\AddressBookStateService;
use LightManager\Module\AddressBook\Presentation\Command\AddCommand;
use LightManager\Module\AddressBook\Presentation\Command\ChapterCommand;
use LightManager\Module\AddressBook\Presentation\Command\ClearCommand;
use LightManager\Module\AddressBook\Presentation\Command\EditCommand;
use LightManager\Module\AddressBook\Presentation\Command\FieldCommand;
use LightManager\Module\AddressBook\Presentation\Command\ForgetCommand;
use LightManager\Module\AddressBook\Presentation\Command\RemoveCommand;
use LightManager\Module\AddressBook\Presentation\Command\RenameCommand;
use LightManager\Module\AddressBook\Presentation\Command\SetCommand;
use LightManager\Module\AddressBook\Presentation\Command\ShowCommand;
use LightManager\Module\AddressBook\Presentation\Query\ChaptersQuery;
use LightManager\Module\AddressBook\Presentation\Query\EntriesQuery;
use LightManager\Module\AddressBook\Presentation\Query\EntryQuery;
use LightManager\Module\AddressBook\Presentation\Query\FieldsQuery;
use LightManager\Module\AddressBook\Presentation\Query\LastQuery;
use LightManager\Module\AddressBook\Presentation\Query\ValueQuery;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Module\ProvidesHelpTab;
use LightManager\Presentation\Ui\Module\ProvidesScreen;
use LightManager\Presentation\Ui\ScreenInterface;

/**
 * Książka adresowa — **siódmy moduł i pierwszy, który istnieje po to, żeby
 * trzymać pola wszystkich** (krok 60).
 *
 * **Nie deklaruje `RequiresEnvironment`** i jest to warunek, nie przeoczenie:
 * książka nie potrzebuje do działania niczego spoza aplikacji, a moduł
 * odrzucony zabrałby wpisy wszystkim pozostałym naraz. To jedna z miar tego
 * kroku: brak klienta `ssh` odrzuca moduł sesji zdalnej, ale **nie ma prawa
 * odebrać użytkownikowi adresów**, z których korzysta też moduł Dockera.
 *
 * **Deklaruje `NeedsTick`** i używa taktu do jednej rzeczy: zapowiada w nim
 * użycie **własnego rozdziału** — tymi samymi komendami, którymi robią to
 * moduły. Książka jest przez to pierwszym użytkownikiem własnego mechanizmu
 * i nie ma w nim wyjątku dla siebie (D104 nr 1); gdyby mechanizm był
 * niewygodny, poczułaby to pierwsza.
 */
final class AddressBookModule implements
    ModuleInterface,
    ProvidesSettingsTab,
    ProvidesCommands,
    ProvidesQueries,
    ProvidesScreen,
    ProvidesHelpTab,
    DeclaresEvents,
    NeedsTick
{
    /** „Wpisy” — litera `w` jest wolna i nie stoi w spisie zakazanych. */
    private const SHORTCUT = 'w';

    /** Rozdział własny książki: to, co ma sens przy każdym miejscu. */
    public const GENERAL = 'general';

    private ?Addresses $addresses = null;

    private ?AddressBookQueries $reader = null;

    private ?AddressBookScreen $assembled = null;

    private ?EntryFlow $flow = null;

    private bool $declared = false;

    public function __construct(
        private readonly LoopState $state,
        private readonly TranslatorPort $translator,
        /** Wstrzyknięcie istnieje dla testów; `null` znaczy sekcję prawdziwego dokumentu stanu. */
        private readonly ?AddressBookPort $storage = null,
    ) {
    }

    public function id(): string
    {
        return AddressBookSettings::ID;
    }

    public function nameKey(): string
    {
        return AddressBookSettings::key('name');
    }

    public function descriptionKey(): string
    {
        return AddressBookSettings::key('description');
    }

    public function shortcut(): ModuleShortcut
    {
        return new ModuleShortcut(self::SHORTCUT);
    }

    public function translations(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lang';
    }

    public function settingsTab(): ModuleSettingsTab
    {
        return new ModuleSettingsTab($this->nameKey(), AddressBookSettings::declarations());
    }

    public function events(): array
    {
        return AddressBookEvent::declarations();
    }

    /**
     * Takt służy do **jednej** rzeczy: zapowiedzenia użycia własnego rozdziału.
     *
     * Deklaracja idzie **komendami z rejestru**, a nie wywołaniem modelu obok
     * nich — inaczej książka miałaby drogę, której nie ma nikt inny, a właśnie
     * to było czwartą wadą jej poprzedniczki. Pada raz: komendy są idempotentne,
     * ale wołanie ich trzydzieści razy na sekundę byłoby pracą bez odbiorcy
     * (takt ma być tani, 11o').
     */
    public function tick(float $now): void
    {
        if ($this->declared) {
            return;
        }

        $this->declared = true;
        $chapter = $this->state->commands()->find(AddressBookSettings::ID . '.chapter');
        $field = $this->state->commands()->find(AddressBookSettings::ID . '.field');

        if ($chapter === null || $field === null) {
            $this->declared = false;

            return;
        }

        $chapter->execute(new CommandInput([
            ChapterCommand::CHAPTER => self::GENERAL,
            ChapterCommand::TITLE => AddressBookSettings::key('chapter.general'),
        ]));

        foreach (self::generalFields() as $declaration) {
            $field->execute(new CommandInput($declaration));
        }
    }

    public function commands(): array
    {
        $addresses = $this->addresses();
        $reader = $this->reader();

        return [
            new ShowCommand($this->screenOf(), $reader),
            new ChapterCommand($addresses, $this->translator),
            new FieldCommand($addresses, $this->translator),
            new AddCommand($addresses, $this->flow(), $this->translator),
            new RenameCommand($addresses, $reader, $this->translator),
            new RemoveCommand($addresses, $reader, $this->translator),
            new SetCommand($addresses, $reader, $this->translator),
            new ClearCommand($addresses, $reader, $this->translator),
            new EditCommand($this->flow(), $reader, $this->translator),
            new ForgetCommand($addresses, $reader, $this->translator),
        ];
    }

    /**
     * Sześć źródeł danych — i **wszystkie sześć obsługują wszystkie rozdziały**
     * (D104 nr 1). Rozdział jest w nich argumentem, nie osobnym wejściem.
     */
    public function queries(): array
    {
        $addresses = $this->addresses();

        return [
            new EntriesQuery($addresses),
            new EntryQuery($addresses),
            new ChaptersQuery($addresses),
            new FieldsQuery($addresses),
            new ValueQuery($addresses),
            new LastQuery($addresses),
        ];
    }

    public function screen(): ScreenInterface
    {
        return $this->screenOf();
    }

    public function helpKeys(): array
    {
        return [
            AddressBookSettings::key('help.entry'),
            AddressBookSettings::key('help.chapter'),
            AddressBookSettings::key('help.access'),
            AddressBookSettings::key('help.secret'),
        ];
    }

    /**
     * Pola rozdziału własnego — **to, co ma sens przy każdym miejscu**, i nic
     * ponad to.
     *
     * Adres stoi tutaj, a nie wśród pól wpisu (D104 nr 5): książka nie wie, co
     * to adres, a rozdział `general` jest zwykłym rozdziałem — wolno go
     * wyczyścić `address-book.forget`, tak samo jak każdy inny.
     *
     * @return list<array<string, string>>
     */
    private static function generalFields(): array
    {
        return [
            [
                FieldCommand::CHAPTER => self::GENERAL,
                FieldCommand::FIELD => 'address',
                FieldCommand::LABEL => AddressBookSettings::key('field.address'),
                FieldCommand::KIND => 'text',
            ],
            [
                FieldCommand::CHAPTER => self::GENERAL,
                FieldCommand::FIELD => 'note',
                FieldCommand::LABEL => AddressBookSettings::key('field.note'),
                FieldCommand::KIND => 'text',
            ],
        ];
    }

    private function screenOf(): AddressBookScreen
    {
        return $this->assembled ??= new AddressBookScreen(
            $this->state,
            $this->reader(),
            $this->flow(),
            $this->translator,
        );
    }

    private function flow(): EntryFlow
    {
        return $this->flow ??= new EntryFlow($this->state, $this->reader(), $this->translator);
    }

    /** Odczyt własnych danych — przez rejestr kwerend (D92 nr 3). */
    private function reader(): AddressBookQueries
    {
        return $this->reader ??= new AddressBookQueries($this->state->queries());
    }

    /**
     * Model — **jeden na moduł**, wspólny dla komend i kwerend.
     *
     * Widzą go wyłącznie one; ekran, łańcuch okien i wszyscy pozostali dobijają
     * się przez rejestry rdzenia.
     */
    private function addresses(): Addresses
    {
        return $this->addresses ??= new Addresses(
            $this->storage ?? AddressBookStateService::getInstance(),
            $this->state->events(),
        );
    }
}
