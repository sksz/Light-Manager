<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Browser\Presentation;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandTransition;
use LightManager\Application\Command\SuggestsArguments;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleSetting;
use LightManager\Application\Module\ProvidesSettingsTab;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * Menadżer plików po przeprowadzce z rdzenia do modułu.
 *
 * Test pilnuje trzech rzeczy: że przeglądarka jest **modułem jak każdy inny**
 * (tożsamość, skrót, zakładka ustawień, napisy, komenda), że jest zarazem modułem
 * **ostatniej szansy** (niewyłączalna, sprawdzana pierwsza) i że jej ekran rysuje
 * **trzy strefy**, a nie jedną.
 */
final class BrowserModuleTest extends TestCase
{
    private InMemoryDirectoryRepository $directories;

    private ScreenFixture $app;

    protected function setUp(): void
    {
        $this->directories = (new InMemoryDirectoryRepository())
            ->add('/', [Entry::directory('home')])
            ->add('/home', [Entry::directory('dokumenty'), Entry::directory('dane'), Entry::file('notatka.txt', 12)])
            ->add('/home/dokumenty', [Entry::file('umowa.pdf', 2048)])
            ->add('/home/dane', []);

        $this->app = new ScreenFixture(
            $this->directories->get(new DirectoryPath('/home'), false),
            $this->directories,
        );
    }

    public function testModuleDeclaresEveryHookOfTheContract(): void
    {
        $module = $this->app->module('browser');

        self::assertNotNull($module);
        self::assertSame('browser', $module->id());

        $shortcut = $module->shortcut();

        self::assertNotNull($shortcut);
        self::assertSame('b', $shortcut->character);
        self::assertTrue($shortcut->ctrl);
        self::assertDirectoryExists((string) $module->translations(), 'napisy leżą w katalogu modułu');
    }

    /**
     * `showHiddenEntries` przestało być kluczem rdzenia i jest pozycją modułu.
     *
     * Od kroku 24 stoją obok niego dwie pozycje podziału — i to jest treść tej
     * asercji: podział jest ustawieniem **modułu**, a nie rdzenia, bo to moduł
     * rozstrzyga, jak wygląda jego własny interfejs. Krok 31 dokłada szóstą,
     * i jest ona zarazem **jedyną pozycją wyboru** w tej zakładce: „bez limitu”
     * nie jest liczbą, więc głębokość drzewa nie mogła zostać przełącznikiem.
     *
     * Krok 41 dokłada siódmą — pytanie przed usunięciem — i stawia ją **na końcu**
     * listy, bo odczyty wskazują deklaracje numerem: pozycja wstawiona w środku
     * przestawiłaby znaczenie wszystkich następnych.
     */
    public function testSettingsTabCarriesTheHiddenEntriesToggle(): void
    {
        $module = $this->app->module('browser');

        self::assertInstanceOf(ProvidesSettingsTab::class, $module);

        $keys = array_map(
            static fn (ModuleSetting $setting): string => $setting->key,
            $module->settingsTab()->settings,
        );

        self::assertSame(
            [
                BrowserSettings::SHOW_HIDDEN,
                BrowserSettings::SPLIT,
                BrowserSettings::SPLIT_VERTICAL,
                BrowserSettings::DETAILS,
                BrowserSettings::COLUMN_HEADER,
                BrowserSettings::TREE_DEPTH,
                BrowserSettings::ASK_BEFORE_DELETE,
                BrowserSettings::DELETE_TO_TRASH,
                BrowserSettings::TRASH_DIRECTORY,
                BrowserSettings::UNDO_DEPTH,
            ],
            $keys,
        );
    }

    public function testCommandMovedIntoTheBrowserNamespace(): void
    {
        $command = $this->app->commandRegistry->find('browser.jump');

        self::assertNotNull($command);
        self::assertSame('module.browser.command.jump', $command->descriptionKey());
    }

    public function testJumpEntersTheGivenDirectory(): void
    {
        $outcome = $this->jump()->execute(new CommandInput(['path' => '/home/dokumenty']));

        self::assertSame(CommandTransition::Close, $outcome->transition);
        self::assertSame('/home/dokumenty', $this->app->state->context()->path);
    }

    public function testJumpAcceptsAPathRelativeToTheCurrentPlace(): void
    {
        $this->jump()->execute(new CommandInput(['path' => 'dane']));

        self::assertSame('/home/dane', $this->app->state->context()->path);
    }

    /** Ścieżka nieczytelna nie zamyka okna — użytkownik ma gdzie poprawić literówkę. */
    public function testJumpToAnUnreadableDirectoryStaysOpenWithAMessage(): void
    {
        $outcome = $this->jump()->execute(new CommandInput(['path' => '/nie-ma-takiego']));

        self::assertSame(CommandTransition::Stay, $outcome->transition);
        self::assertNotNull($outcome->message);
        self::assertSame('/home', $this->app->state->context()->path);
    }

    public function testJumpSuggestsDirectoriesFromDiskOnDemand(): void
    {
        self::assertSame(['/home/dokumenty/', '/home/dane/'], $this->suggestions('/home/d'));
    }

    public function testSuggestionsOfAnUnreadableDirectoryAreEmptyRatherThanAnError(): void
    {
        $this->directories->makeUnreadable('/home');

        self::assertSame([], $this->suggestions('/home/d'));
    }

    public function testSuggestionsSkipFiles(): void
    {
        self::assertSame(
            ['dokumenty/', 'dane/'],
            $this->suggestions(''),
            'skok dotyczy katalogów, więc plik nie jest podpowiedzią',
        );
    }

    /**
     * Skok zmienia katalog **i ogłasza to modułom**: kontekst sesji ma jedno
     * miejsce publikacji, więc druga droga zmiany katalogu nie może go pominąć.
     */
    public function testJumpPublishesTheSessionContext(): void
    {
        $this->jump()->execute(new CommandInput(['path' => '/home/dokumenty']));

        self::assertSame('/home/dokumenty', $this->app->state->context()->path);
        self::assertSame('umowa.pdf', $this->app->state->context()->selection);
    }

    public function testBrowserIsTheLastResortModuleAndCannotBeDisabled(): void
    {
        self::assertSame('browser', $this->app->modules->lastResort());
        self::assertTrue($this->app->modules->isEssential('browser'));
        self::assertFalse($this->app->modules->isEssential('file-info'));
    }

    /** Dno stosu wybiera konfiguracja; domyślnie wskazuje przeglądarkę. */
    public function testScreenOfTheDefaultModuleStandsOnTheFloor(): void
    {
        self::assertSame('browser', $this->app->screens->current()->id());
        self::assertNull($this->app->startup->problemKey);
    }

    /**
     * Wskazanie innego modułu w konfiguracji uruchamia aplikację z jego ekranem —
     * **bez zmiany w kodzie rdzenia**. To drugie z trzech zdań, którymi krok 21
     * mierzy swoje powodzenie.
     */
    public function testAnotherModuleNamedInTheConfigurationBecomesTheFloor(): void
    {
        $app = $this->fixtureWith((new Settings())->withStartupModule('file-info'));

        self::assertSame('file-info', $app->screens->current()->id());
        self::assertNull($app->startup->problemKey);
    }

    /** Wartość nieznana w pliku wraca do modułu ostatniej szansy i mówi o tym. */
    public function testUnknownStartupModuleFallsBackToTheBrowser(): void
    {
        $app = $this->fixtureWith((new Settings())->withStartupModule('drzewo'));

        self::assertSame('browser', $app->screens->current()->id());
        self::assertSame('module.startup.unknown', $app->startup->problemKey);
    }

    /**
     * `Ctrl+B` otwiera przeglądarkę z każdego ekranu, a naciśnięty na niej samej
     * — gdy jest dnem — **nie robi nic**. Przypadku szczególnego na to nie ma:
     * `toggle()` woła `close()`, a `close()` stawia ten sam ekran.
     */
    public function testCtrlBOpensTheBrowserFromAnyScreenAndDoesNothingOnTheFloor(): void
    {
        $this->app->input->handle(KeyPress::special(Key::F1, ''), $this->app->state, 0.0);
        self::assertSame('help', $this->app->screens->current()->id());

        $this->app->input->handle(KeyPress::ctrl('b'), $this->app->state, 0.0);
        self::assertSame('browser', $this->app->screens->current()->id());

        $this->app->input->handle(KeyPress::ctrl('b'), $this->app->state, 0.0);
        self::assertSame('browser', $this->app->screens->current()->id(), 'na dnie skrót nie robi nic');
    }

    private function fixtureWith(Settings $settings): ScreenFixture
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/home', [Entry::directory('dokumenty')])
            ->add('/home/dokumenty', []);

        return new ScreenFixture(
            $directories->get(new DirectoryPath('/home'), false),
            $directories,
            new InMemorySettings($settings),
        );
    }

    /**
     * **Dwie strefy, nie trzy** (D76): pasek ścieżki zamawia ekran, a pasa podglądu
     * nie zamawia nikt.
     *
     * Do tej zmiany przeglądarka zamawiała wszystkie trzy strefy i był to jej znak
     * firmowy po kroku 21. Podglądu nie ma tu odtąd wcale — miniaturę pokazuje moduł
     * `FileInfo` w całym panelu — więc wiersze pasa idą do listy plików.
     */
    public function testScreenOrdersTheHeaderButNoPreviewStrip(): void
    {
        $screen = $this->app->browser;

        self::assertSame('module.browser.zone.files', $screen->labelKey());

        $header = $screen->header();

        self::assertNotNull($header);
        self::assertSame('layout.zone.path', $header->labelKey);

        $texts = self::textsOf($header->content->draw(new Rect(0, 2, 1, 60)));

        self::assertNotSame([], $texts);
        self::assertStringStartsWith('/home', $texts[0], 'ścieżka wraz z numerem zaznaczenia');
        self::assertStringContainsString('1/3', $texts[0]);
    }

    /** Znacznik wpisów ukrytych wrócił tam, skąd pochodzi — do paska ścieżki. */
    public function testHiddenMarkerAppearsInTheHeaderAfterTheDotKey(): void
    {
        $this->app->browser->handle(KeyPress::character('.'));

        $header = $this->app->browser->header();

        self::assertNotNull($header);
        self::assertStringContainsString(
            'module.browser.hidden',
            implode('', self::textsOf($header->content->draw(new Rect(0, 2, 1, 80)))),
        );
    }

    /**
     * Klawisze przeglądarki — spis zamknięty, więc każdy nowy widać w teście.
     *
     * Do kroku 30 zdanie brzmiało „nie zmieniły się co do znaku” i pilnowało
     * obietnicy kroku 21: przenosiny do modułu nie dotykają zachowania. Filtr
     * dokłada tu **jedną** pozycję i to jest cała zmiana; `Esc` dochodzi
     * warunkowo i sprawdza go osobny test, bo bez zawężenia nie ma prawa się
     * pokazać.
     *
     * Krok 31 dokłada drugą — klawisz widoku — i stawia ją **na końcu**, bo
     * dotyczy panelu, a nie tego, co w nim jest. Reszta spisu zostaje nietknięta,
     * bo panel pokazujący listę zachowuje się tak, jak przed tym krokiem;
     * spisu drzewa pilnuje osobny test.
     *
     * Krok 41 dokłada trzy klawisze czynności zmieniających dysk (`F4`, `F7`,
     * `F8`/`Delete`), krok 42 dwa następne (`F5`, `F6`) — i one też stoją na
     * końcu, bo dotyczą wpisu, a nie widoku. Że są w spisie **jednym** wpisem na
     * czynność, ma znaczenie dla stopki: `F8` i `Delete` robią to samo, więc jedno
     * wiązanie o dwóch klawiszach.
     *
     * Krok 43 dokłada dwa klawisze zaznaczania i stawia je **pośrodku, tuż za
     * ruchem kursora**, a nie na końcu — bo należą do **listy**, czyli do miejsca
     * ogniska, a nie do ekranu. Widać to w spisie drzewa, w którym ich nie ma:
     * zbiór trzyma nazwy z jednego katalogu, a węzły leżą na różnych poziomach
     * (D80, rozstrzygnięcie 9).
     */
    public function testKeyBindingsGrewByTheFilterOnly(): void
    {
        $keys = array_map(
            static fn (object $binding): string => (string) $binding->descriptionKey,
            $this->app->browser->bindings(),
        );

        self::assertSame(
            [
                'help.key.move',
                'module.browser.help.open',
                'module.browser.help.up',
                'module.browser.help.mark',
                // Zaznaczanie zakresem (krok 44) stoi przy spacji, bo jest tym
                // samym krokiem zaznaczania — tylko bez podnoszenia palca.
                'module.browser.help.markRange',
                'module.browser.help.invert',
                'module.browser.help.hidden',
                'module.browser.help.filter',
                'module.browser.help.tree',
                'module.browser.help.rename',
                'module.browser.help.copy',
                'module.browser.help.move',
                'module.browser.help.mkdir',
                // Dwie drogi usunięcia (krok 44): goły klawisz wedle ustawienia
                // (domyślnie kosz), `Shift` zawsze to drugie — stąd dwa wpisy.
                'module.browser.help.trash',
                'module.browser.help.delete',
                'module.browser.help.undo',
                'module.browser.help.undoView',
            ],
            $keys,
        );
    }

    public function testEscapeIsNotHandledByTheFloorScreenItself(): void
    {
        $outcome = $this->app->browser->handle(KeyPress::special(Key::Escape, ''));

        self::assertSame([], array_filter([$outcome->message]), 'dno nie ma dokąd wracać');
    }

    private function jump(): CommandInterface
    {
        $command = $this->app->commandRegistry->find('browser.jump');

        self::assertNotNull($command);

        return $command;
    }

    /** @return list<string> */
    private function suggestions(string $prefix): array
    {
        $command = $this->jump();

        self::assertInstanceOf(SuggestsArguments::class, $command);

        return $command->suggestions('path', $prefix);
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<string>
     */
    private static function textsOf(array $primitives): array
    {
        $texts = [];

        foreach ($primitives as $primitive) {
            if ($primitive instanceof TextRun) {
                $texts[] = $primitive->text;
            }
        }

        return $texts;
    }
}
