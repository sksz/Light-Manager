<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation;

use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Module\DeclaresEvents;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleSettingsTab;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Application\Module\ProvidesCommands;
use LightManager\Application\Module\ProvidesQueries;
use LightManager\Application\Module\ProvidesSettingsTab;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Module\AddressBook\Application\AddressBookEvent;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\Addresses;
use LightManager\Module\AddressBook\Application\Port\AddressBookPort;
use LightManager\Module\AddressBook\Infrastructure\AddressBookStateService;
use LightManager\Module\AddressBook\Presentation\Command\AddCommand;
use LightManager\Module\AddressBook\Presentation\Command\ChapterCommand;
use LightManager\Module\AddressBook\Presentation\Command\RemoveCommand;
use LightManager\Module\AddressBook\Presentation\Command\ShowCommand;
use LightManager\Module\AddressBook\Presentation\Query\EntriesQuery;
use LightManager\Module\AddressBook\Presentation\Query\EntryQuery;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Module\ProvidesHelpTab;
use LightManager\Presentation\Ui\Module\ProvidesScreen;

/**
 * Książka adresowa jako moduł (krok 60) — **siódmy sprawdzian kontraktu z kroku
 * 20 i pierwszy moduł istniejący po to, żeby dzielić dane**.
 *
 * Poprzednie sześć powstało dla własnych funkcji: przeglądarka rysuje główną
 * rzecz aplikacji, dźwięk gra bez ekranu, sesja zdalna rozmawia poza maszyną.
 * Ten nie robi nic, czego nie widać u innych — trzyma adres, pod którym coś
 * stoi, i oddaje go **każdemu, kto zapyta nazwą kwerendy** (15g).
 *
 * Trzy rzeczy, których ten moduł świadomie **nie** deklaruje, każda z powodem:
 *
 * - **`RequiresEnvironment`** — książka nie potrzebuje do działania niczego
 *   spoza aplikacji, a moduł odrzucony zabrałby adresy **wszystkim pozostałym**.
 *   To jest zarazem druga miara kroku: brak klienta `ssh` nie ma prawa odebrać
 *   użytkownikowi spisu adresów.
 * - **`NeedsTick`** — nie ma pracy w locie; takt bez odbiorcy byłby polem, które
 *   ktoś weźmie za źródło prawdy o klatce (warunek z D82).
 * - **`ReadsContext`** — adres nie ma nic wspólnego z miejscem, w którym stoi
 *   użytkownik.
 *
 * Składa się leniwie, jak wszystkie: napisy wchodzą do katalogu **po** zbudowaniu
 * rejestru modułów, a książki **nie czyta**, dopóki ktoś o nią nie zapyta —
 * uruchomienie aplikacji nie kosztuje ani jednego odczytu z dysku.
 */
final class AddressBookModule implements
    ModuleInterface,
    ProvidesSettingsTab,
    ProvidesCommands,
    ProvidesQueries,
    ProvidesHelpTab,
    ProvidesScreen,
    DeclaresEvents
{
    /**
     * Litera skrótu (D105 nr 1).
     *
     * `w` była wolna — zajęte są `a`, `b`, `d`, `k`, `o` i `s`, a `c`, `h`, `i`,
     * `j`, `m` i `z` odpadają z przyczyn terminalowych
     * (`ModuleRegistry::FORBIDDEN_CHARACTERS`). Mnemonik: **w**pisy.
     */
    private const SHORTCUT = 'w';

    /** @var list<CommandInterface>|null */
    private ?array $commands = null;

    private ?Addresses $addresses = null;

    private ?AddressBookScreen $screen = null;

    private ?EntryFlow $flow = null;

    private ?AddressBookQueries $reader = null;

    /**
     * @param ?AddressBookPort $storage wstrzyknięcie istnieje dla testów, które nie mają
     *                                  prawa dotknąć pliku w katalogu domowym — tak samo,
     *                                  jak w module sesji zdalnej i w module Dockera
     */
    public function __construct(
        private readonly LoopState $state,
        private readonly TranslatorPort $translator,
        private readonly SettingsPort $settings,
        private readonly ?AddressBookPort $storage = null,
    ) {
    }

    public function id(): string
    {
        return AddressBookSettings::ID;
    }

    public function nameKey(): string
    {
        return 'module.' . AddressBookSettings::ID . '.name';
    }

    public function descriptionKey(): string
    {
        return 'module.' . AddressBookSettings::ID . '.description';
    }

    /** `Ctrl`+`W` otwiera książkę. */
    public function shortcut(): ModuleShortcut
    {
        return new ModuleShortcut(self::SHORTCUT);
    }

    /** Napisy modułu leżą obok jego kodu, a nie w katalogu rdzenia. */
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

    public function commands(): array
    {
        return $this->commands ??= [
            new ShowCommand(),
            new AddCommand($this->addresses(), $this->flow(), $this->translator),
            new RemoveCommand($this->addresses(), $this->translator),
            new ChapterCommand($this->addresses(), $this->translator),
        ];
    }

    public function queries(): array
    {
        return [
            new EntriesQuery($this->addresses()),
            new EntryQuery($this->addresses()),
        ];
    }

    public function screen(): AddressBookScreen
    {
        return $this->screen ??= new AddressBookScreen(
            $this->addresses(),
            $this->flow(),
            $this->reader(),
            $this->translator,
        );
    }

    /**
     * Część własna zakładki pomocy — to, czego z deklaracji wyczytać się nie da.
     *
     * Pięć zdań i każde odpowiada na pytanie, które użytkownik zada: czym to
     * jest, dlaczego wpis ma identyfikator, skąd biorą się pola poza nazwą
     * i adresem, dlaczego nie ma tu haseł i co się dzieje, gdy usunie się wpis,
     * na który ktoś się powołuje.
     */
    public function helpKeys(): array
    {
        return [
            'module.' . AddressBookSettings::ID . '.help.start',
            'module.' . AddressBookSettings::ID . '.help.id',
            'module.' . AddressBookSettings::ID . '.help.chapters',
            'module.' . AddressBookSettings::ID . '.help.secrets',
            'module.' . AddressBookSettings::ID . '.help.remove',
        ];
    }

    /**
     * Koordynator — **jeden na moduł**, bo ekran, trzy komendy i dwie kwerendy
     * muszą widzieć ten sam spis (11n).
     */
    public function addresses(): Addresses
    {
        return $this->addresses ??= new Addresses(
            $this->storage ?? AddressBookStateService::getInstance(),
            $this->settings,
            $this->state->events(),
        );
    }

    private function flow(): EntryFlow
    {
        return $this->flow ??= new EntryFlow($this->addresses(), $this->reader(), $this->translator);
    }

    /** Fasada odczytu — **jedna na moduł** (krok 53, D92 nr 3). */
    private function reader(): AddressBookQueries
    {
        return $this->reader ??= new AddressBookQueries($this->state->queries(), $this->addresses());
    }
}
