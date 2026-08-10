<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Cli;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Rect;
use LightManager\Domain\ValueObject\Entry;
use LightManager\Domain\ValueObject\MessageTone;
use LightManager\Presentation\Ui\Module\ReadsContext;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\ScreenFixture;
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

    protected function setUp(): void
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/', [Entry::directory('home')])
            ->add('/home', [Entry::directory('dokumenty'), Entry::directory('obrazy'), Entry::file('notatka.txt', 12)])
            ->add('/home/dokumenty', [Entry::file('umowa.pdf', 2048), Entry::file('.szkic', 10)])
            ->add('/home/obrazy', []);

        $this->app = new ScreenFixture(
            $directories->get(new \LightManager\Domain\ValueObject\DirectoryPath('/home'), false),
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

    private function selectedIndex(): ?int
    {
        return $this->app->state->directory()->selection()?->index;
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
        self::assertSame(0, $this->selectedIndex());

        $this->special(Key::ArrowDown);
        self::assertSame(1, $this->selectedIndex());

        $this->special(Key::ArrowUp);
        self::assertSame(0, $this->selectedIndex());
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

        self::assertSame('/home/dokumenty', $this->app->state->directory()->path()->value);
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

        self::assertSame('/', $this->app->state->directory()->path()->value);
        self::assertSame('home', $this->app->state->directory()->selectedEntry()?->name);
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
        self::assertSame('/home', $this->app->state->directory()->path()->value);
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

    /** Moduł bez zaznaczonego pliku mówi o tym własnym napisem, a nie pustym panelem. */
    public function testModuleScreenSaysWhenThereIsNothingToDescribe(): void
    {
        $this->press(KeyPress::ctrl('d'));

        $texts = $this->drawCurrentScreen();

        self::assertContains('module.file-info.nothing', $texts, 'zaznaczony jest katalog');
        self::assertSame([], $this->app->inspector->inspectedPaths);
    }

    public function testDotTogglesHiddenEntriesAndPersistsTheSetting(): void
    {
        $this->special(Key::Enter);
        self::assertCount(1, $this->app->state->directory()->entries());

        $this->character('.');

        self::assertCount(2, $this->app->state->directory()->entries());
        self::assertTrue($this->app->state->showsHiddenEntries());
        self::assertTrue($this->app->settingsStore->saved[0]->showHiddenEntries);
    }

    public function testFailedReloadLeavesHiddenEntriesSettingUntouched(): void
    {
        $this->app->directories->makeUnreadable('/home');

        $this->character('.');

        self::assertFalse($this->app->state->showsHiddenEntries());
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

    public function testChangingHiddenEntriesFromSettingsReloadsNothingButPersists(): void
    {
        $this->special(Key::F2);
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowRight);

        self::assertTrue($this->app->settingsStore->saved[0]->showHiddenEntries);
    }

    public function testRestoreButtonSitsUnderTheLastPositionAndReportsWhatItDid(): void
    {
        $this->special(Key::F2);

        // Trzy pozycje zakładki „Wygląd”, a czwarte zejście staje na przycisku.
        for ($step = 0; $step < 4; ++$step) {
            $this->special(Key::ArrowDown);
        }

        $this->special(Key::Enter);

        self::assertSame(MessageTone::Info, $this->app->state->message()?->tone);
    }

    /**
     * Spis modułów jest **trzecią** zakładką ustawień: za dwiema rdzeniowymi,
     * przed zakładkami poszczególnych modułów.
     */
    public function testModulesTabListsTheModuleWithItsShortcut(): void
    {
        $this->special(Key::F2);
        $this->special(Key::ArrowRight);
        $this->special(Key::ArrowRight);

        // Szerzej niż zwykle: podwójny tłumacza oddaje klucze, a te są dłuższe
        // od napisów, którymi pasek zakładek żyje w aplikacji.
        $texts = $this->drawCurrentScreen(10, 120);

        self::assertContains('settings.tab.modules', $texts, 'zakładka jest na pasku');
        self::assertContains('module.file-info.name   Ctrl+D', $texts);
    }

    public function testSwitchingAModuleOffPersistsAndSaysItNeedsARestart(): void
    {
        $this->special(Key::F2);
        $this->special(Key::ArrowRight);
        $this->special(Key::ArrowRight);
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowRight);

        self::assertFalse($this->app->settingsStore->saved[0]->moduleValue('file-info', 'enabled'));
        self::assertSame(MessageTone::Info, $this->app->state->message()?->tone);
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

        self::assertContains(
            'module.file-info.name',
            $this->drawCurrentScreen(10, 120),
            'zakładka się nie zmieniła',
        );
    }

    public function testHelpHasATabPerModuleBuiltFromItsDeclaration(): void
    {
        $this->special(Key::F1);
        $this->special(Key::ArrowLeft);

        $texts = implode("\n", $this->drawCurrentScreen(40, 60));

        self::assertStringContainsString('module.file-info.description', $texts, 'część automatyczna');
        self::assertStringContainsString('Ctrl+D', $texts, 'skrót z deklaracji');
        self::assertStringContainsString('help.key.scroll', $texts, 'klawisze ekranu modułu');
        self::assertStringContainsString('module.file-info.setting.timeout', $texts, 'pozycje ustawień');
        self::assertStringContainsString('module.file-info.help.enter', $texts, 'część własna');
    }

    /** Otwiera zakładkę modułu: dwie rdzeniowe, spis modułów, dopiero potem ona. */
    private function openModuleTab(): void
    {
        $this->special(Key::F2);

        for ($step = 0; $step < 3; ++$step) {
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
        self::assertStringContainsString('Backspace / ←', $texts, 'wiązania przeglądarki');
        self::assertStringContainsString('help.key.change', $texts, 'wiązania ekranu ustawień');
    }

    public function testBrowsingKeysDoNothingOnTheHelpScreen(): void
    {
        $this->special(Key::F1);
        $this->special(Key::Enter);

        self::assertSame('/home', $this->app->state->directory()->path()->value);
    }

    public function testQuitKeyWorksOnEveryScreen(): void
    {
        $this->special(Key::F1);
        self::assertTrue($this->special(Key::F10));

        $this->special(Key::F2);
        self::assertTrue($this->special(Key::F10));
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
