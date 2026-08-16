<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Cli;

use Closure;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Rect;
use LightManager\Domain\ValueObject\MessageTone;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Presentation\Cli\InputHandler;
use LightManager\Presentation\Cli\ProblemPresenter;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Module\ReadsContext;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubFileStat;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Wędrówka klawisza: rdzeń → aktywny ekran → komponent z kursorem.
 *
 * Do kroku 18 wszystko to mieszkało w `InputHandler` i test sprawdzał jedną
 * klasę. Dziś sprawdza **drogę**, którą klawisz przebywa, bo to ona jest
 * kontraktem — a leży na niej pięć obiektów.
 */
final class InputHandlerTest extends TestCase
{
    /** Dowolna chwila na osi czasu — liczy się wyłącznie różnica między odczytami. */
    private const NOW = 1000.0;

    private ScreenFixture $app;

    /**
     * Repozytorium trzymane osobno, w typie konkretnym: zestaw ekranów widzi je
     * od kroku 41 przez interfejs, bo przebieg operacji na plikach podstawia tam
     * prawdziwy system plików — a `makeUnreadable()` jest własnością atrapy.
     */
    private InMemoryDirectoryRepository $directories;

    protected function setUp(): void
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/', [Entry::directory('home')])
            ->add('/home', [Entry::directory('dokumenty'), Entry::directory('obrazy'), Entry::file('notatka.txt', 12)])
            ->add('/home/dokumenty', [Entry::file('umowa.pdf', 2048), Entry::file('.szkic', 10)])
            ->add('/home/obrazy', []);

        $this->directories = $directories;
        $this->app = new ScreenFixture(
            $directories->get(new DirectoryPath('/home'), false),
            $directories,
            new InMemorySettings(),
        );
    }

    private function press(KeyPress $key): bool
    {
        return $this->app->input->handle($key, $this->app->state, self::NOW);
    }

    private function character(string $character): bool
    {
        return $this->press(KeyPress::character($character));
    }

    private function special(Key $key): bool
    {
        return $this->press(KeyPress::special($key, ''));
    }

    /**
     * Zaznaczenie widziane **kontekstem sesji** — jedyną drogą, którą rdzeń o nim
     * słyszy od kroku 21. Wcześniej test sięgał po katalog w stanie pętli; katalog
     * należy dziś do modułu przeglądarki i rdzeń go nie widzi.
     */
    private function selectedName(): ?string
    {
        return $this->app->state->context()->selection;
    }

    public function testQuitKeyIsReported(): void
    {
        self::assertTrue($this->special(Key::F10));
    }

    public function testLetterQNoLongerQuits(): void
    {
        self::assertFalse($this->character('q'), 'po kroku 18 rdzeń nie rezerwuje ani jednej litery');
    }

    public function testArrowsMoveSelection(): void
    {
        self::assertSame('dokumenty', $this->selectedName());

        $this->special(Key::ArrowDown);
        self::assertSame('obrazy', $this->selectedName());

        $this->special(Key::ArrowUp);
        self::assertSame('dokumenty', $this->selectedName());
    }

    /** @return array<string, array{Key}> */
    public static function enterKeys(): array
    {
        return ['Enter' => [Key::Enter], 'strzałka w prawo' => [Key::ArrowRight]];
    }

    #[DataProvider('enterKeys')]
    public function testEntersSelectedDirectory(Key $key): void
    {
        $this->special($key);

        self::assertSame('/home/dokumenty', $this->app->state->context()->path);
    }

    /** @return array<string, array{Key}> */
    public static function upKeys(): array
    {
        return ['Backspace' => [Key::Backspace], 'strzałka w lewo' => [Key::ArrowLeft]];
    }

    #[DataProvider('upKeys')]
    public function testGoesUpAndSelectsDirectoryItCameFrom(Key $key): void
    {
        $this->special($key);

        self::assertSame('/', $this->app->state->context()->path);
        self::assertSame('home', $this->app->state->context()->selection);
    }

    /**
     * `Enter` na pliku **nie robi nic** (krok 20, P3): opis wyprowadził się do
     * modułu i ma własny skrót, a `Enter` jest odtąd klawiszem zatwierdzania.
     */
    public function testEnterOnAFileDoesNothing(): void
    {
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowDown);
        $this->special(Key::Enter);

        self::assertNull($this->app->state->overlays()->current());
        self::assertSame('/home', $this->app->state->context()->path);
        self::assertSame([], $this->app->inspector->inspectedPaths);
    }

    public function testModuleShortcutOpensTheModuleScreenAndDescribesTheSelectedFile(): void
    {
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowDown);

        $this->press(KeyPress::ctrl('d'));
        self::assertSame('file-info', $this->app->screens->current()->id());

        $texts = $this->drawCurrentScreen();

        self::assertSame(['/home/notatka.txt'], $this->app->inspector->inspectedPaths);
        self::assertContains('notatka.txt', $texts);
        self::assertContains('PDF document, version 1.7', $texts);
    }

    public function testModuleShortcutClosesTheScreenItOpenedAndEscapeReturns(): void
    {
        $this->press(KeyPress::ctrl('d'));
        $this->press(KeyPress::ctrl('d'));
        self::assertSame('browser', $this->app->screens->current()->id());

        $this->press(KeyPress::ctrl('d'));
        $this->special(Key::Escape);
        self::assertSame('browser', $this->app->screens->current()->id());
    }

    /** Litera bez `Ctrl` nie jest skrótem modułu — trafia tam, gdzie każdy inny znak. */
    public function testModuleShortcutRequiresCtrl(): void
    {
        self::assertFalse($this->character('d'));
        self::assertSame('browser', $this->app->screens->current()->id());
    }

    /**
     * Katalog **jest** opisywany od kroku 25 (P3) — ale bez pytania polecenia
     * `file`, bo powiedziałoby ono tylko to, co i tak stoi wiersz wyżej.
     */
    public function testModuleScreenDescribesADirectoryWithoutRunningTheInspector(): void
    {
        $this->app->stats->add('/home/dokumenty', StubFileStat::directory());
        $this->press(KeyPress::ctrl('d'));

        $texts = implode("\n", $this->drawCurrentScreen());

        self::assertStringContainsString('module.file-info.section.identity', $texts);
        self::assertStringContainsString('module.file-info.kind.directory', $texts);
        self::assertSame([], $this->app->inspector->inspectedPaths, 'katalog nie uruchamia polecenia');
    }

    /** Wpis, którego nie ma — jedyny przypadek, w którym opisu nie ma wcale. */
    public function testModuleScreenSaysWhenThereIsNothingToDescribe(): void
    {
        $this->app->stats->deny('/home/dokumenty');
        $this->press(KeyPress::ctrl('d'));

        self::assertContains('module.file-info.nothing', $this->drawCurrentScreen());
    }

    /**
     * Widoczność wpisów ukrytych jest od kroku 21 ustawieniem **modułu**, więc
     * klawisz `.` kończy w kluczu `modules.browser.showHidden`. Sprawdzamy przy tym
     * to, co widzi użytkownik — listę — a nie stan agregatu: katalog należy dziś
     * do modułu i rdzeń go nie widzi.
     */
    public function testDotTogglesHiddenEntriesAndPersistsTheSetting(): void
    {
        $this->special(Key::Enter);
        self::assertNotContains('.szkic', $this->drawCurrentScreen());

        $this->character('.');

        self::assertContains('.szkic', $this->drawCurrentScreen());
        self::assertTrue(BrowserSettings::showHidden($this->app->state->settings()));
        self::assertTrue($this->app->settingsStore->saved[0]->moduleValue('browser', 'showHidden'));
    }

    public function testFailedReloadLeavesHiddenEntriesSettingUntouched(): void
    {
        $this->directories->makeUnreadable('/home');

        $this->character('.');

        self::assertFalse(BrowserSettings::showHidden($this->app->state->settings()));
        self::assertSame([], $this->app->settingsStore->saved);
        self::assertSame(MessageTone::Error, $this->app->state->message()?->tone);
    }

    /** @return array<string, array{Key, string}> */
    public static function screenKeys(): array
    {
        return ['F1 otwiera pomoc' => [Key::F1, 'help'], 'F2 otwiera ustawienia' => [Key::F2, 'settings']];
    }

    #[DataProvider('screenKeys')]
    public function testFunctionKeysOpenScreensAndEscapeReturns(Key $key, string $id): void
    {
        self::assertSame('browser', $this->app->screens->current()->id());

        $this->special($key);
        self::assertSame($id, $this->app->screens->current()->id());

        $this->special(Key::Escape);
        self::assertSame('browser', $this->app->screens->current()->id());
    }

    #[DataProvider('screenKeys')]
    public function testTheSameKeyClosesTheScreenItOpened(Key $key, string $id): void
    {
        $this->special($key);
        $this->special($key);

        self::assertSame('browser', $this->app->screens->current()->id());
    }

    public function testSettingsCursorStartsOnTabBarAndArrowsSwitchTabs(): void
    {
        $this->special(Key::F2);
        $this->special(Key::ArrowRight);

        self::assertTrue(self::showsTab($this->app->screens->current(), 'settings.tab.graphics'));
    }

    public function testArrowDownEntersPositionsAndArrowChangesValue(): void
    {
        $this->special(Key::F2);
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowRight);

        self::assertNotSame([], $this->app->settingsStore->saved);
    }

    /**
     * Druga droga do tego samego klucza: zakładka ustawień modułu przeglądarki.
     * Obie — klawisz `.` i ta pozycja — kończą w `modules.browser.showHidden`.
     */
    public function testChangingHiddenEntriesFromSettingsPersistsToTheModuleKey(): void
    {
        $this->openBrowserTab();
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowRight);

        self::assertTrue($this->app->settingsStore->saved[0]->moduleValue('browser', 'showHidden'));
    }

    /**
     * Od kroku 28 przycisk czynności **nie kasuje konfiguracji** — otwiera
     * pytanie. Komunikat pojawia się dopiero po odpowiedzi „tak”, a ta wymaga
     * przestawienia ognisko, bo okno startuje na „nie”.
     */
    public function testRestoreButtonAsksBeforeItRestores(): void
    {
        $this->standOnRestoreButton();
        $this->special(Key::Enter);

        self::assertSame('confirm', $this->app->state->overlays()->current()?->id());
        self::assertNull($this->app->state->message());

        // Ognisko z „nie” na „tak”, potem zatwierdzenie.
        $this->special(Key::ArrowRight);
        $this->special(Key::Enter);

        self::assertNull($this->app->state->overlays()->current());
        self::assertSame(MessageTone::Info, $this->app->state->message()?->tone);
    }

    /** Odmowa zamyka pytanie i **niczego nie zmienia** — główne kryterium kroku 28. */
    public function testRefusingTheQuestionChangesNothing(): void
    {
        $this->standOnRestoreButton();
        $this->special(Key::Enter);

        // Ognisko stoi na „nie”, więc sam `Enter` jest odmową.
        $this->special(Key::Enter);

        self::assertNull($this->app->state->overlays()->current());
        self::assertNull($this->app->state->message());
        self::assertSame([], $this->app->settingsStore->saved);
    }

    /** `Esc` znaczy dokładnie tyle, co „nie” (D56). */
    public function testEscapeRefusesJustLikeTheNoButton(): void
    {
        $this->standOnRestoreButton();
        $this->special(Key::Enter);
        $this->special(Key::Escape);

        self::assertNull($this->app->state->overlays()->current());
        self::assertSame([], $this->app->settingsStore->saved);
    }

    /** Cztery pozycje zakładki „Wygląd”, a piąte zejście staje na przycisku czynności. */
    private function standOnRestoreButton(): void
    {
        $this->special(Key::F2);

        for ($step = 0; $step < 5; ++$step) {
            $this->special(Key::ArrowDown);
        }
    }

    /**
     * Spis modułów jest **trzecią** zakładką ustawień: za dwiema rdzeniowymi,
     * przed zakładkami poszczególnych modułów.
     */
    public function testModulesTabListsTheModuleWithItsShortcut(): void
    {
        $this->openModulesTab();

        // Szerzej niż zwykle: podwójny tłumacza oddaje klucze, a te są dłuższe
        // od napisów, którymi pasek zakładek żyje w aplikacji.
        $texts = $this->drawCurrentScreen(10, 120);

        self::assertContains('settings.tab.modules', $texts, 'zakładka jest na pasku');
        self::assertContains('settings.key.startupModule', $texts, 'moduł domyślny nad spisem');
        self::assertContains('module.file-info.name   Ctrl+D', $texts);
        self::assertContains('module.browser.name   Ctrl+B', $texts);
    }

    /**
     * Pierwszy wiersz spisu to moduł domyślny, drugi — przeglądarka, dopiero
     * trzeci daje się wyłączyć. Przeglądarka jest modułem ostatniej szansy, więc
     * jej przełącznik stoi, ale mówi wyłącznie, dlaczego nie działa.
     */
    public function testSwitchingAModuleOffPersistsAndSaysItNeedsARestart(): void
    {
        $this->openModulesTab();
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowRight);

        self::assertFalse($this->app->settingsStore->saved[0]->moduleValue('file-info', 'enabled'));
        self::assertSame(MessageTone::Info, $this->app->state->message()?->tone);
    }

    public function testTheLastResortModuleCannotBeSwitchedOff(): void
    {
        $this->openModulesTab();
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowRight);

        self::assertSame([], $this->app->settingsStore->saved, 'nic się nie zapisało');
        self::assertSame(MessageTone::Warning, $this->app->state->message()?->tone);
        self::assertSame('settings.modules.essential.reason', $this->app->state->message()->text);
    }

    /** Moduł domyślny zmienia się strzałką, a jego wartości pochodzą z rejestru. */
    public function testStartupModuleCyclesThroughModulesWithAScreen(): void
    {
        $this->openModulesTab();
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowRight);

        self::assertSame('file-info', $this->app->settingsStore->saved[0]->startupModule);
    }

    public function testModuleTabChangesItsOwnSettingWithArrows(): void
    {
        $this->openModuleTab();
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowRight);

        self::assertSame(5, $this->app->settingsStore->saved[0]->moduleValue('file-info', 'timeout'));
    }

    /**
     * Pozycja tekstowa jest jedynym miejscem, które dokłada ekranowi ustawień
     * tryb: `Enter` wchodzi w edycję i zatwierdza, `Esc` wycofuje.
     */
    public function testTextPositionIsEditedWithEnterAndCommittedWithEnter(): void
    {
        $this->openModuleTab();
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowDown);
        $this->special(Key::Enter);

        $this->character('-');
        $this->character('k');
        $this->special(Key::Enter);

        self::assertSame('-k', $this->app->settingsStore->saved[0]->moduleValue('file-info', 'arguments'));
    }

    public function testEscapeDuringEditingDiscardsTheValueAndKeepsTheScreen(): void
    {
        $this->openModuleTab();
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowDown);
        $this->special(Key::Enter);

        $this->character('x');
        $this->special(Key::Escape);

        self::assertSame([], $this->app->settingsStore->saved);
        self::assertSame('settings', $this->app->screens->current()->id(), 'Esc wychodzi z edycji, nie z ekranu');
    }

    /** W trybie edycji strzałka należy do pola, a nie do paska zakładek. */
    public function testArrowsDoNotSwitchTabsWhileEditing(): void
    {
        $this->openModuleTab();
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowDown);
        $this->special(Key::Enter);
        $this->special(Key::ArrowRight);

        // Okno musi być na tyle wysokie, żeby pasek zakładek w ogóle się
        // narysował: ekran ustawień składa się z `Slot::fixed()`, więc przy
        // braku miejsca pozycje **znikają** zamiast się przewijać (reguła 11e),
        // a zakładka file-info ma od kroku 29 dziesięć pozycji.
        //
        // Szerokość urosła w kroku 49 ze 120 na 160 kolumn i powód jest
        // testowy, nie interfejsowy: **w teście zakładki nazywają się swoimi
        // kluczami** (`settings.tab.resources` to 23 znaki, przetłumaczone
        // „ZASOBY" — sześć), a `Tabs` ucina to, co nie mieści się w wierszu.
        // Czwarta zakładka rdzenia przelała ten rachunek na kluczach, nie na
        // napisach.
        self::assertContains(
            'module.file-info.name',
            $this->drawCurrentScreen(20, 160),
            'zakładka się nie zmieniła',
        );
    }

    public function testHelpHasATabPerModuleBuiltFromItsDeclaration(): void
    {
        $this->special(Key::F1);

        // Cztery razy w lewo, a nie trzy: od kroku 51 ostatnią zakładką jest
        // Docker, przed nim sesja zdalna, przed nią dźwięk, a ten test opisuje
        // zakładkę **modułu opisu pliku** — to on ma w deklaracji wszystkie
        // cztery rzeczy, które sprawdzamy niżej. Liczba rośnie z każdym modułem
        // dokładanym na koniec listy i to jest tańsze niż szukanie zakładki po
        // nazwie: test ma pokazywać, że kolejność zakładek idzie za kolejnością
        // modułów.
        $this->special(Key::ArrowLeft);
        $this->special(Key::ArrowLeft);
        $this->special(Key::ArrowLeft);
        $this->special(Key::ArrowLeft);

        $texts = implode("\n", $this->drawCurrentScreen(40, 60));

        self::assertStringContainsString('module.file-info.description', $texts, 'część automatyczna');
        self::assertStringContainsString('Ctrl+D', $texts, 'skrót z deklaracji');
        self::assertStringContainsString('module.file-info.help.checksum', $texts, 'klawisze ekranu modułu');
        self::assertStringContainsString('module.file-info.setting.timeout', $texts, 'pozycje ustawień');
        self::assertStringContainsString('module.file-info.help.enter', $texts, 'część własna');
    }

    /** Spis modułów: trzecia zakładka, za dwiema rdzeniowymi. */
    /** Spis modułów stoi za **trzema** zakładkami rdzenia — trzecią („Zasoby") dołożył krok 49. */
    private function openModulesTab(): void
    {
        $this->openTab(3);
    }

    /** Zakładka przeglądarki — pierwsza modułowa, bo taka jest kolejność deklaracji. */
    private function openBrowserTab(): void
    {
        $this->openTab(4);
    }

    /** Zakładka modułu `FileInfo` — druga modułowa. */
    private function openModuleTab(): void
    {
        $this->openTab(5);
    }

    private function openTab(int $index): void
    {
        $this->special(Key::F2);

        for ($step = 0; $step < $index; ++$step) {
            $this->special(Key::ArrowRight);
        }
    }

    public function testHelpScreenScrollsWithArrowsAndSwitchesTabs(): void
    {
        $this->special(Key::F1);

        $keys = self::textsOf($this->app->screens->current()->draw(new Rect(0, 2, 12, 60)));
        self::assertNotSame([], $keys);

        $this->special(Key::ArrowRight);
        $about = self::textsOf($this->app->screens->current()->draw(new Rect(0, 2, 12, 60)));

        self::assertNotSame($keys, $about, 'druga zakładka pokazuje co innego');
    }

    public function testHelpListsKeysOfEveryScreen(): void
    {
        $this->special(Key::F1);

        $texts = implode("\n", self::textsOf($this->app->screens->current()->draw(new Rect(0, 2, 40, 60))));

        self::assertStringContainsString('F10', $texts, 'wiązania rdzenia');

        // Ekran ustawień otwiera się kursorem na **pasku zakładek**, więc jego
        // spis mówi od kroku 40 o zmianie zakładki, a nie o zmianie wartości:
        // `←→` na pasku przewija zakładki i nigdy nie zmieniało ustawienia.
        // Do tamtego kroku spis był jeden na cały ekran i kłamał w tym miejscu.
        self::assertStringContainsString('help.key.tab', $texts, 'wiązania ekranu ustawień');

        // Klawisze przeglądarki od kroku 21 stoją na **jej** zakładce, a nie
        // w ogólnym spisie: jest modułem, a moduł dostaje własną zakładkę (P8
        // kroku 20). To jedyne miejsce, w którym przenosiny widać w interfejsie.
        self::assertStringNotContainsString('module.browser.help.up', $texts);
    }

    public function testBrowsingKeysDoNothingOnTheHelpScreen(): void
    {
        $this->special(Key::F1);
        $this->special(Key::Enter);

        self::assertSame('/home', $this->app->state->context()->path);
    }

    public function testQuitKeyWorksOnEveryScreen(): void
    {
        $this->special(Key::F1);
        self::assertTrue($this->special(Key::F10));

        $this->special(Key::F2);
        self::assertTrue($this->special(Key::F10));
    }

    /**
     * Pełny ekran wisi na `F11` **wyłącznie w torze okienkowym** (krok 37).
     * Spis klawiszy pokazuje to, co działa tu i teraz, więc w terminalu `F11`
     * nie ma prawa się w nim pojawić.
     */
    public function testFullscreenKeyBelongsToTheWindowedTrackOnly(): void
    {
        self::assertSame(
            ['help.key.help', 'help.key.settings', 'help.key.menu', 'help.key.commands', 'help.key.quit'],
            self::descriptionsOf(InputHandler::globalBindings()),
        );
        self::assertSame(
            [
                'help.key.help',
                'help.key.settings',
                'help.key.menu',
                'help.key.commands',
                'help.key.quit',
                'help.key.fullscreen',
            ],
            self::descriptionsOf(InputHandler::globalBindings(true)),
        );
    }

    public function testFullscreenKeyTogglesTheWindowWhenThereIsOneToToggle(): void
    {
        $toggles = 0;
        $handler = $this->handlerWithFullscreen(function () use (&$toggles): bool {
            ++$toggles;

            return true;
        });

        $quits = $handler->handle(KeyPress::special(Key::F11, ''), $this->app->state, self::NOW);

        self::assertSame(1, $toggles);
        self::assertFalse($quits);
    }

    /**
     * W terminalu `F11` nie ma czego przełączać, więc klawisz schodzi niżej —
     * a że żaden ekran go nie zna, nie dzieje się nic. Zaznaczenie ma zostać
     * tam, gdzie było.
     */
    public function testFullscreenKeyDoesNothingWithoutAWindow(): void
    {
        $before = $this->selectedName();

        self::assertFalse($this->special(Key::F11));
        self::assertSame($before, $this->selectedName());
    }

    /** @param callable(): bool $toggle */
    private function handlerWithFullscreen(callable $toggle): InputHandler
    {
        return new InputHandler(
            $this->app->screens,
            $this->app->help,
            $this->app->settings,
            new ProblemPresenter(new StubTranslator()),
            $this->app->commands,
            [],
            Closure::fromCallable($toggle),
        );
    }

    /**
     * @param list<KeyBinding> $bindings
     *
     * @return list<string>
     */
    private static function descriptionsOf(array $bindings): array
    {
        return array_map(static fn (KeyBinding $binding): string => $binding->descriptionKey, $bindings);
    }

    /**
     * Rysuje aktywny ekran tak, jak robi to składanie klatki: ekran modułu
     * dostaje najpierw kontekst sesji. Pełną ścieżkę — od klawisza po klatkę —
     * sprawdza `GameLoopTest`.
     *
     * @return list<string>
     */
    private function drawCurrentScreen(int $rows = 10, int $columns = 60): array
    {
        $screen = $this->app->screens->current();

        if ($screen instanceof ReadsContext) {
            $screen->useContext($this->app->state->context());
        }

        return self::textsOf($screen->draw(new Rect(0, 2, $rows, $columns)));
    }

    /**
     * @param list<\LightManager\Application\Ui\Primitive\Primitive> $primitives
     *
     * @return list<string>
     */
    private static function textsOf(array $primitives): array
    {
        $texts = [];

        foreach ($primitives as $primitive) {
            if ($primitive instanceof \LightManager\Application\Ui\Primitive\TextRun) {
                $texts[] = $primitive->text;
            }
        }

        return $texts;
    }

    private static function showsTab(ScreenInterface $screen, string $label): bool
    {
        return in_array($label, self::textsOf($screen->draw(new Rect(0, 2, 10, 60))), true);
    }
}
