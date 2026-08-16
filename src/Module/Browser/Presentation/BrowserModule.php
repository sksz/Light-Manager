<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation;

use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Module\DeclaresEvents;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleSettingsTab;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Application\Module\ProvidesCommands;
use LightManager\Application\Module\ProvidesQueries;
use LightManager\Application\Module\ProvidesSettingsTab;
use LightManager\Application\Port\FileOperationsPort;
use LightManager\Application\Port\FileTransferPort;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Port\TrashPort;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\UseCase\ChangeModuleSettingUseCase;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserEvent;
use LightManager\Module\Browser\Application\BrowserEvents;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Application\Undo\UndoJournal;
use LightManager\Module\Browser\Application\UseCase\ExpandBranchUseCase;
use LightManager\Module\Browser\Application\UseCase\MoveSelectionUseCase;
use LightManager\Module\Browser\Application\UseCase\NavigateIntoDirectoryUseCase;
use LightManager\Module\Browser\Application\UseCase\NavigateUpUseCase;
use LightManager\Module\Browser\Application\UseCase\OpenStartingDirectoryUseCase;
use LightManager\Module\Browser\Application\UseCase\ReloadDirectoryUseCase;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\Repository\DirectoryRepositoryInterface;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Infrastructure\EntryComparator;
use LightManager\Module\Browser\Infrastructure\FilesystemDirectoryRepository;
use LightManager\Module\Browser\Presentation\Command\CopyCommand;
use LightManager\Module\Browser\Presentation\Command\DeleteCommand;
use LightManager\Module\Browser\Presentation\Command\HiddenCommand;
use LightManager\Module\Browser\Presentation\Command\JumpCommand;
use LightManager\Module\Browser\Presentation\Command\MakeDirectoryCommand;
use LightManager\Module\Browser\Presentation\Command\MoveCommand;
use LightManager\Module\Browser\Presentation\Command\OpenCommand;
use LightManager\Module\Browser\Presentation\Command\RenameCommand;
use LightManager\Module\Browser\Presentation\Command\TreeCommand;
use LightManager\Module\Browser\Presentation\Query\CwdQuery;
use LightManager\Module\Browser\Presentation\Query\EntriesQuery;
use LightManager\Module\Browser\Presentation\Query\MarkedQuery;
use LightManager\Module\Browser\Presentation\Query\PanesQuery;
use LightManager\Module\Browser\Presentation\Query\SelectionQuery;
use LightManager\Module\Browser\Presentation\Query\TreeQuery;
use LightManager\Module\Browser\Presentation\Query\UndoQuery;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Cli\Query\CoreReader;
use LightManager\Presentation\Cli\SplitSetting;
use LightManager\Presentation\Ui\Module\ProvidesHelpTab;
use LightManager\Presentation\Ui\Module\ProvidesScreen;
use LightManager\Presentation\Ui\ScreenInterface;

/**
 * Menadżer plików jako moduł — i to moduł jak każdy inny.
 *
 * Do kroku 20 przeglądarka była **rdzeniem z doklejonymi modułami**: jej katalog
 * leżał w stanie pętli, jej ekran był wpisany w dno stosu, a `FrameComposer`
 * rysował z tego katalogu dwie strefy klatki niezależnie od tego, czyj ekran stał
 * w środku. Krok 21 wkłada tę funkcję w kontrakt z kroku 20 i **nie dokłada do
 * niego ani jednej metody** — to jest cały sprawdzian.
 *
 * Moduł **składa się sam**. `Bootstrap` podaje mu wyłącznie rzeczy rdzenia: stan
 * pętli, tłumacza, port konfiguracji i port operacji na plikach. Portu podglądu
 * obrazów **już nie bierze** (D76): pas podglądu zniknął z przeglądarki, bo
 * miniaturę pokazuje moduł `FileInfo`. Repozytorium
 * katalogów, przypadki użycia, ekran, stan modułu i komenda powstają tutaj — gdyby
 * powstawały w `Bootstrapie`, rdzeń poznałby `FilesystemDirectoryRepository`
 * i `DirectoryPath`, czyli dokładnie to, czego ten krok mu odbiera.
 *
 * Przeglądarka jest zarazem **modułem ostatniej szansy** (D40, P4): rejestr
 * sprawdza ją pierwszą, nie da się jej wyłączyć ani odrzucić, a aplikacja wraca
 * do niej, gdy moduł domyślny okaże się niedostępny. Sam moduł nic o tym nie wie
 * — ten przywilej nadaje mu `Bootstrap`, podając rejestrowi jego identyfikator.
 */
final class BrowserModule implements
    ModuleInterface,
    ProvidesSettingsTab,
    ProvidesCommands,
    ProvidesQueries,
    ProvidesScreen,
    ProvidesHelpTab,
    DeclaresEvents
{
    /** „Browser” — litera `b` jest wolna: `0x02` nie znaczy w trybie surowym nic. */
    private const SHORTCUT = 'b';

    /** Domyślny podział paneli: po połowie (krok 55). */
    private const SPLIT_PERCENT = 50;

    private ?CoreReader $core = null;

    /** @var array{BrowserScreen, list<CommandInterface>, list<QueryInterface>}|null */
    private ?array $parts = null;

    /**
     * @param DirectoryRepositoryInterface|null $directories źródło katalogów; `null`
     *                                                      znaczy „system plików”.
     *                                                      Wstrzyknięcie istnieje dla
     *                                                      testów, które składają cały
     *                                                      moduł bez dotykania dysku
     * @param DirectoryPath|null                $startingPath katalog startowy; `null`
     *                                                        znaczy „katalog roboczy
     *                                                        procesu”
     */
    public function __construct(
        private readonly LoopState $state,
        private readonly TranslatorPort $translator,
        private readonly SettingsPort $settings,
        private readonly FileOperationsPort $operations,
        private readonly FileTransferPort $transfers,
        private readonly TrashPort $trash,
        private readonly ?DirectoryRepositoryInterface $directories = null,
        private readonly ?DirectoryPath $startingPath = null,
    ) {
    }

    /**
     * Ekran, stan i komenda powstają przy **pierwszym pytaniu o nie**, a nie
     * w konstruktorze — i to nie jest ostrożność na zapas.
     *
     * Otwarcie katalogu startowego potrafi skończyć się komunikatem w pasku stanu,
     * a komunikat trzeba przetłumaczyć. Napisy modułu wchodzą do katalogu dopiero
     * po zbudowaniu rejestru (`Bootstrap::registerTranslations()`), więc moduł
     * składany zachłannie, w konstruktorze, wypisałby użytkownikowi surowy klucz.
     * Leniwość ustawia to w jedyną kolejność, w której obie rzeczy są prawdziwe.
     *
     * @return array{BrowserScreen, list<CommandInterface>, list<QueryInterface>}
     */
    private function assembled(): array
    {
        return $this->parts ??= $this->assemble();
    }

    /** Odczyt ustawień rdzenia — przez rejestr kwerend (krok 53, D92 nr 3). */
    private function core(): CoreReader
    {
        return $this->core ??= new CoreReader($this->state->queries());
    }

    /** @return array{BrowserScreen, list<CommandInterface>, list<QueryInterface>} */
    private function assemble(): array
    {
        $directories = $this->directories ?? new FilesystemDirectoryRepository(EntryComparator::create());
        $opened = $this->opened($directories);

        // Publikator zdarzeń modułu (krok 46). Bierze się ze stanu pętli, jak
        // kontekst sesji, więc `Bootstrap` nie urósł o ani jeden argument —
        // i tak samo jak kontekst jest **jeden na moduł**: dwa znaczyłyby dwa
        // rejestry, a odbiorca słucha jednego.
        $events = new BrowserEvents($this->state->events());

        // Drugi panel dostaje **własny** agregat tego samego katalogu, a nie ten
        // sam obiekt: `Directory` jest mutowalny w miejscu (zaznaczenie zmienia
        // się bez tworzenia nowego), więc wspólny obiekt dałby dwa panele z jednym
        // kursorem. Odczyt idzie przez repozytorium jeszcze raz, bo to jedyna
        // droga do niezależnej kopii; komunikat o katalogu zastępczym padł już
        // przy pierwszym otwarciu i drugi raz go nie powtarzamy.
        $first = new BrowserState($this->state, $opened);
        $second = new BrowserState(
            $this->state,
            $directories->get($opened->path(), BrowserSettings::showHidden($this->core()->settings())),
        );

        // Drzewo powstaje **razem z panelem**, a nie przy pierwszym naciśnięciu
        // `Ctrl`+`T`: gałęzie czyta na żądanie, więc nietknięte nie kosztuje ani
        // jednego sięgnięcia na dysk, a tworzone w środku klatki musiałoby dostać
        // repozytorium przez ekran — czyli przewlec je przez klasę, która dziś
        // o repozytorium nie wie.
        $branches = new ExpandBranchUseCase($directories);
        $panes = new BrowserPanes(
            $first,
            $second,
            BrowserScreen::SCROLL_MARGIN,
            new BrowserTree($first, $branches, $this->state, $this->translator, BrowserScreen::SCROLL_MARGIN),
            new BrowserTree($second, $branches, $this->state, $this->translator, BrowserScreen::SCROLL_MARGIN),
            // Proporcja podziału przeżywa uruchomienie (krok 55): wczytuje się
            // z ustawień modułu, a przeciągnięcie granicy myszą zapisuje ją tam
            // z powrotem — raz, po zwolnieniu przycisku.
            SplitSetting::state(
                BrowserSettings::ID,
                self::SPLIT_PERCENT,
                $this->state,
                new ChangeModuleSettingUseCase($this->settings, $this->translator),
            ),
        );

        // Wpisy ukryte przełącza od kroku 32 jedna klasa dla dwóch wejść: kropki
        // na liście i komendy `browser.hidden`. Ekran i komenda dostają ten sam
        // obiekt, bo czynność jest jedna — dwa obiekty znaczyłyby dwa rachunki
        // tego samego ustawienia.
        // Dwie fasady odczytu na cały moduł (krok 53, D92 nr 3): jedna do danych
        // przeglądarki, druga do ustawień rdzenia. Powstają **raz**, bo dwie
        // znaczyłyby dwa miejsca rozpakowujące ładunek kwerendy.
        $reader = new BrowserQueries($this->state->queries(), $panes);
        $core = $this->core();

        $reload = new ReloadDirectoryUseCase($directories);
        $hidden = new HiddenEntries(
            $panes,
            $reader,
            $core,
            $reload,
            new ChangeModuleSettingUseCase($this->settings, $this->translator),
            $this->state,
        );
        $navigateInto = new NavigateIntoDirectoryUseCase($directories);

        // Trzy czynności zmieniające dysk — jedna klasa dla klawisza i dla komendy
        // (krok 41, wzorzec `HiddenEntries`). Odczyt katalogu po operacji idzie
        // tym samym przypadkiem użycia, którym idzie przełączenie wpisów ukrytych,
        // a katalog panelu, któremu usunięto przodka, wraca do najbliższego
        // czytelnego wyżej — czyli tam, gdzie prowadzi otwieranie katalogu
        // startowego.
        $refresh = new PaneRefresh($panes, $reload, new OpenStartingDirectoryUseCase($directories));

        // Stos cofnięć (krok 44) — pamięć modułu, nie rdzenia, wbrew literze
        // planu kroku: operacje zmaterializowały się w całości po tej stronie,
        // więc dziennik ma jednego piszącego i jednego czytającego (reguła 15).
        // Głębokość pyta ustawień przy każdym zapisie, więc zmiana pozycji
        // działa od następnej operacji.
        $journal = new UndoJournal(fn (): int => BrowserSettings::undoDepth($this->core()->settings()));
        $entries = new EntryOperations(
            $reader,
            $this->operations,
            $refresh,
            $this->translator,
            $journal,
            $events,
        );

        // Dwie czynności dłuższe od klatki (krok 42) — osobno od tamtych trzech,
        // bo prowadzą pracę kawałkową z własnym stanem i własnym łańcuchem okien.
        // Odświeżenie paneli mają wspólne: dysk jest jeden, a panele te same.
        $transfers = new EntryTransfer(
            $panes,
            $reader,
            $this->transfers,
            $refresh,
            $this->translator,
            $journal,
            $events,
        );

        // Rozdroże usunięcia i wykonawca cofnięć (krok 44). `EntryTrash` dostaje
        // `EntryOperations`, bo odpowiedź „usuń trwale” na pytanie o wpis spoza
        // systemu plików kosza prowadzi w drogę z kroku 41 — wraz z jej groźnym
        // pytaniem. `EntryUndo` dostaje `EntryTransfer`, bo cofnięcie
        // przeniesienia jest tą samą pracą kawałkową w drugą stronę.
        $trash = new EntryTrash(
            $reader,
            $core,
            $entries,
            $this->trash,
            $this->transfers,
            $refresh,
            $this->translator,
            $journal,
            $events,
        );
        $undo = new EntryUndo(
            $this->operations,
            $this->trash,
            $transfers,
            $refresh,
            $this->translator,
            $journal,
            $events,
        );

        return [
            new BrowserScreen(
                $panes,
                $reader,
                $core,
                new MoveSelectionUseCase(),
                $navigateInto,
                new NavigateUpUseCase($directories),
                $hidden,
                $this->translator,
                $entries,
                $transfers,
                $trash,
                $undo,
                $events,
            ),
            [
                new JumpCommand($panes, $reader, $directories, $this->translator),
                new OpenCommand($panes, $reader, $navigateInto, $this->translator),
                new HiddenCommand($hidden, $this->translator),
                new TreeCommand($panes),
                new RenameCommand($entries, $this->translator),
                new MakeDirectoryCommand($entries, $this->translator),
                new DeleteCommand($trash, $this->translator),
                new CopyCommand($transfers, $this->translator),
                new MoveCommand($transfers, $this->translator),
            ],
            // Sześć źródeł danych (krok 53). Powstają na **tych samych** obiektach,
            // co ekran i komendy — inaczej kwerenda odpowiadałaby o innym panelu
            // niż ten, na który patrzy użytkownik.
            [
                new EntriesQuery($panes, $this->translator),
                new SelectionQuery($panes),
                new MarkedQuery($panes),
                new CwdQuery($panes),
                new PanesQuery($panes),
                new TreeQuery($panes),
                new UndoQuery($journal),
            ],
        ];
    }

    /**
     * Katalog, od którego zaczyna się praca: katalog roboczy procesu, a gdy nie da
     * się go otworzyć — pierwszy czytelny powyżej, wraz z komunikatem.
     *
     * Wiedza o katalogu roboczym zeszła tu z `Bootstrapu` razem z całą resztą
     * (D40, P8). Komunikat stawiamy **bezwarunkowo**: pasek stanu niesie jeden
     * napis, a nieotwarty katalog jest ważniejszy od uwagi do pliku konfiguracji,
     * którą rdzeń zdążył już postawić — użytkownik ma najpierw zrozumieć, na co
     * właśnie patrzy.
     */
    private function opened(DirectoryRepositoryInterface $directories): Directory
    {
        $requested = $this->startingPath ?? self::workingDirectory();
        $directory = (new OpenStartingDirectoryUseCase($directories))
            ->execute($requested, BrowserSettings::showHidden($this->core()->settings()));

        if (!$directory->path()->equals($requested)) {
            $this->state->report(
                Message::error($this->translator->translate('module.browser.problem.fallback', [
                    'requested' => $requested->value,
                    'opened' => $directory->path()->value,
                ])),
                microtime(true),
            );
        }

        return $directory;
    }

    /** Katalog roboczy procesu; gdy nie da się go ustalić — korzeń systemu plików. */
    private static function workingDirectory(): DirectoryPath
    {
        $workingDirectory = getcwd();

        return $workingDirectory === false
            ? DirectoryPath::root()
            : new DirectoryPath($workingDirectory);
    }

    public function id(): string
    {
        return BrowserSettings::ID;
    }

    public function nameKey(): string
    {
        return 'module.' . BrowserSettings::ID . '.name';
    }

    public function descriptionKey(): string
    {
        return 'module.' . BrowserSettings::ID . '.description';
    }

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
        return new ModuleSettingsTab($this->nameKey(), [
            ...BrowserSettings::declarations(),
            // Proporcja podziału stoi w zakładce modułu, a jej deklaracja
            // powstaje w rdzeniu (krok 55): pozycja należy do modułu (reguła 11c),
            // ale mechanizm jest wspólny dla pięciu ekranów z podziałem.
            SplitSetting::declaration(BrowserSettings::ID, self::SPLIT_PERCENT),
        ]);
    }

    /**
     * Zdarzenia, które moduł wnosi do słownika (krok 46).
     *
     * Spis powstaje z enumu, a nie z listy pisanej tutaj ręcznie — dwie listy
     * rozjechałyby się przy pierwszym dołożonym zdarzeniu, a **rozjazd byłby
     * niewidoczny**: wiersz w oknie odbiorcy, do którego nic nie dochodzi, wygląda
     * dokładnie tak samo jak wiersz, do którego nic nie przypisano.
     */
    public function events(): array
    {
        return BrowserEvent::declarations();
    }

    public function commands(): array
    {
        return $this->assembled()[1];
    }

    /**
     * Sześć źródeł danych przeglądarki (krok 53).
     *
     * Cztery z nich biorą numer panelu, bo ten moduł jako jedyny pokazuje dwa
     * miejsca naraz — a `ModuleContext` mówi wyłącznie o czynnym.
     */
    public function queries(): array
    {
        return $this->assembled()[2];
    }

    public function screen(): ScreenInterface
    {
        return $this->assembled()[0];
    }

    /**
     * Część własna zakładki pomocy — to, czego z deklaracji wyczytać się nie da.
     *
     * Skrótu ani pozycji ustawień tu nie ma i być nie powinno: rdzeń wypisuje je
     * sam, z deklaracji, więc przepisane ręcznie skłamałyby przy pierwszej
     * zmianie.
     */
    public function helpKeys(): array
    {
        return [
            'module.' . BrowserSettings::ID . '.help.default',
            'module.' . BrowserSettings::ID . '.help.jump',
        ];
    }
}
