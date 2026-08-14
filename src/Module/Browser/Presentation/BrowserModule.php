<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation;

use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleSettingsTab;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Application\Module\ProvidesCommands;
use LightManager\Application\Module\ProvidesSettingsTab;
use LightManager\Application\Port\FileOperationsPort;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\UseCase\ChangeModuleSettingUseCase;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserSettings;
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
use LightManager\Module\Browser\Presentation\Command\HiddenCommand;
use LightManager\Module\Browser\Presentation\Command\JumpCommand;
use LightManager\Module\Browser\Presentation\Command\MakeDirectoryCommand;
use LightManager\Module\Browser\Presentation\Command\OpenCommand;
use LightManager\Module\Browser\Presentation\Command\RenameCommand;
use LightManager\Module\Browser\Presentation\Command\TreeCommand;
use LightManager\Presentation\Cli\LoopState;
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
    ProvidesScreen,
    ProvidesHelpTab
{
    /** „Browser” — litera `b` jest wolna: `0x02` nie znaczy w trybie surowym nic. */
    private const SHORTCUT = 'b';

    /** @var array{BrowserScreen, list<CommandInterface>}|null */
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
     * @return array{BrowserScreen, list<CommandInterface>}
     */
    private function assembled(): array
    {
        return $this->parts ??= $this->assemble();
    }

    /** @return array{BrowserScreen, list<CommandInterface>} */
    private function assemble(): array
    {
        $directories = $this->directories ?? new FilesystemDirectoryRepository(EntryComparator::create());
        $opened = $this->opened($directories);

        // Drugi panel dostaje **własny** agregat tego samego katalogu, a nie ten
        // sam obiekt: `Directory` jest mutowalny w miejscu (zaznaczenie zmienia
        // się bez tworzenia nowego), więc wspólny obiekt dałby dwa panele z jednym
        // kursorem. Odczyt idzie przez repozytorium jeszcze raz, bo to jedyna
        // droga do niezależnej kopii; komunikat o katalogu zastępczym padł już
        // przy pierwszym otwarciu i drugi raz go nie powtarzamy.
        $first = new BrowserState($this->state, $opened);
        $second = new BrowserState(
            $this->state,
            $directories->get($opened->path(), BrowserSettings::showHidden($this->state->settings())),
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
        );

        // Wpisy ukryte przełącza od kroku 32 jedna klasa dla dwóch wejść: kropki
        // na liście i komendy `browser.hidden`. Ekran i komenda dostają ten sam
        // obiekt, bo czynność jest jedna — dwa obiekty znaczyłyby dwa rachunki
        // tego samego ustawienia.
        $reload = new ReloadDirectoryUseCase($directories);
        $hidden = new HiddenEntries(
            $panes,
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
        $entries = new EntryOperations(
            $panes,
            $this->operations,
            $reload,
            new OpenStartingDirectoryUseCase($directories),
            $this->state,
            $this->translator,
        );

        return [
            new BrowserScreen(
                $panes,
                $this->state,
                new MoveSelectionUseCase(),
                $navigateInto,
                new NavigateUpUseCase($directories),
                $hidden,
                $this->translator,
                $entries,
            ),
            [
                new JumpCommand($panes, $directories, $this->translator),
                new OpenCommand($panes, $navigateInto, $this->translator),
                new HiddenCommand($hidden, $this->translator),
                new TreeCommand($panes),
                new RenameCommand($entries, $this->translator),
                new MakeDirectoryCommand($entries, $this->translator),
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
            ->execute($requested, BrowserSettings::showHidden($this->state->settings()));

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
        return new ModuleSettingsTab($this->nameKey(), BrowserSettings::declarations());
    }

    public function commands(): array
    {
        return $this->assembled()[1];
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
