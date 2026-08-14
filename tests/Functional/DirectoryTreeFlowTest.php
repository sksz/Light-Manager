<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Presentation\Cli\FrameComposer;
use LightManager\Presentation\Ui\Component\TreeView;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Tests\Support\FixedViewport;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\RecordingRenderer;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Drzewo katalogów widziane od strony użytkownika (krok 31): jeden klawisz
 * zamienia panel w drzewo, strzałki poziome rozwijają i zwijają gałęzie, a to,
 * co rozwinięte, wraca po powrocie.
 *
 * Test patrzy na **klatkę i na klawisze**, a nie na klasy, bo błąd, który tu
 * naprawdę grozi, jest rozjazdem między nimi: kursor drzewa a zaznaczenie listy,
 * spis klawiszy a to, co klawisz robi, kontekst sesji a węzeł pod kursorem.
 */
final class DirectoryTreeFlowTest extends TestCase
{
    private ScreenFixture $app;

    protected function setUp(): void
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/', [Entry::directory('home')])
            ->add('/home', [Entry::directory('projekty'), Entry::file('notatka.txt', 12)])
            ->add('/home/projekty', [Entry::directory('lm'), Entry::file('plan.md', 2048)])
            ->add('/home/projekty/lm', [Entry::file('README.md', 128)]);

        $this->app = new ScreenFixture($directories->get(new DirectoryPath('/home'), false), $directories);
    }

    /** Przed naciśnięciem klawisza panel wygląda co do znaku tak, jak przed krokiem 31. */
    public function testTheListStaysAListUntilTheKeyIsPressed(): void
    {
        $texts = $this->contentTexts();

        self::assertContains('projekty/', $texts);
        self::assertSame([], self::treeRowsOf($texts), 'ani jednej prowadnicy przed przełączeniem widoku');
    }

    public function testCtrlTTurnsTheFocusedPaneIntoATree(): void
    {
        $this->app->browser->handle(KeyPress::ctrl('t'));

        $rows = self::treeRowsOf($this->contentTexts());

        self::assertNotSame([], $rows);
        self::assertStringEndsWith('projekty/', $rows[0]);
        self::assertStringEndsWith('notatka.txt', $rows[1]);
    }

    public function testCtrlTSwitchesBackToTheList(): void
    {
        $this->app->browser->handle(KeyPress::ctrl('t'));
        $this->app->browser->handle(KeyPress::ctrl('t'));

        self::assertSame([], self::treeRowsOf($this->contentTexts()));
    }

    /** `→` rozwija gałąź — jednym odczytem katalogu, w tej samej klatce. */
    public function testRightArrowExpandsTheBranchUnderTheCursor(): void
    {
        $this->app->browser->handle(KeyPress::ctrl('t'));
        $this->app->browser->handle(new KeyPress(Key::ArrowRight, ''));

        $rows = self::treeRowsOf($this->contentTexts());

        self::assertStringEndsWith('lm/', $rows[1]);
        self::assertStringEndsWith('plan.md', $rows[2]);
        self::assertStringContainsString(TreeView::TRUNK, $rows[2], 'dziecko wisi na prowadnicy rodzica');
    }

    public function testLeftArrowCollapsesTheBranchAndKeepsTheCursorOnIt(): void
    {
        $this->app->browser->handle(KeyPress::ctrl('t'));
        $this->app->browser->handle(new KeyPress(Key::ArrowRight, ''));
        $this->app->browser->handle(new KeyPress(Key::ArrowLeft, ''));

        $rows = self::treeRowsOf($this->contentTexts());

        self::assertCount(2, $rows, 'gałąź zwinięta, więc zostają dwa wpisy katalogu');
        self::assertSame('projekty', $this->app->state->context()->selection, 'kursor stoi na zwiniętej gałęzi');
    }

    /**
     * `←` na zwiniętym węźle pierwszego poziomu wychodzi **katalog wyżej** — czyli
     * robi to samo, co w liście. Poziom nad pierwszym leży już na dysku.
     */
    public function testLeftArrowOnTheFirstLevelLeavesTheDirectory(): void
    {
        $this->app->browser->handle(KeyPress::ctrl('t'));
        $this->app->browser->handle(new KeyPress(Key::ArrowLeft, ''));

        self::assertSame('/', $this->app->state->context()->path);
    }

    /** `Enter` na węźle drzewa czyni go katalogiem panelu — z dowolnego poziomu. */
    public function testEnterMakesADeepNodeTheDirectoryOfThePane(): void
    {
        $this->app->browser->handle(KeyPress::ctrl('t'));
        $this->app->browser->handle(new KeyPress(Key::ArrowRight, ''));
        $this->app->browser->handle(new KeyPress(Key::ArrowDown, ''));
        $this->app->browser->handle(new KeyPress(Key::Enter, "\r"));

        self::assertSame('/home/projekty/lm', $this->app->state->context()->path);
        self::assertStringContainsString('/home/projekty/lm', $this->headerText());
    }

    /**
     * Kursor drzewa **jest** wskazaniem panelu: moduł opisujący plik ma pokazać
     * węzeł, na którym stoi użytkownik, a nie zaznaczenie sprzed przełączenia
     * widoku.
     */
    public function testTheTreeCursorPublishesTheSessionContext(): void
    {
        $this->app->browser->handle(KeyPress::ctrl('t'));
        $this->app->browser->handle(new KeyPress(Key::ArrowRight, ''));
        $this->app->browser->handle(new KeyPress(Key::ArrowDown, ''));

        self::assertSame('/home/projekty', $this->app->state->context()->path);
        self::assertSame('lm', $this->app->state->context()->selection);
    }

    /** Rozwinięcia przeżywają wyjście z katalogu i powrót do niego. */
    public function testExpansionsComeBackAfterLeavingTheDirectory(): void
    {
        $this->app->browser->handle(KeyPress::ctrl('t'));
        $this->app->browser->handle(new KeyPress(Key::ArrowRight, ''));
        $this->app->browser->handle(new KeyPress(Key::Backspace, ''));

        self::assertSame('/', $this->app->state->context()->path);

        $this->app->browser->handle(new KeyPress(Key::Enter, "\r"));

        $rows = self::treeRowsOf($this->contentTexts());

        self::assertStringEndsWith('lm/', $rows[1], 'gałąź wraca rozwinięta, bo klucz jest bezwzględny');
    }

    /**
     * Spis klawiszy pokazuje wyłącznie to, co działa tu i teraz (precedens kroku
     * 30): w drzewie strzałki poziome znaczą co innego niż w liście.
     */
    public function testTheKeyListingChangesTogetherWithTheView(): void
    {
        self::assertContains('module.browser.help.open', self::bindingKeysOf($this->app->browser->bindings()));
        self::assertNotContains(
            'module.browser.help.tree.expand',
            self::bindingKeysOf($this->app->browser->bindings()),
        );

        $this->app->browser->handle(KeyPress::ctrl('t'));
        $keys = self::bindingKeysOf($this->app->browser->bindings());

        self::assertContains('module.browser.help.tree.expand', $keys);
        self::assertContains('module.browser.help.tree.collapse', $keys);
        self::assertContains('module.browser.help.tree', $keys, 'klawisz widoku widać w obu widokach');
    }

    /** Widok należy do **panelu**: drugi zostaje listą, choć pierwszy stał się drzewem. */
    public function testTheOtherPaneKeepsItsList(): void
    {
        $this->app->state->applySettings($this->app->state->settings()->withModuleValue(
            BrowserSettings::ID,
            BrowserSettings::SPLIT,
            true,
        ));

        $this->app->browser->handle(KeyPress::ctrl('t'));
        $texts = $this->contentTexts();

        self::assertNotSame([], self::treeRowsOf($texts), 'panel z ogniskiem pokazuje drzewo');
        self::assertContains('projekty/', $texts, 'panel obok pokazuje wpis listy bez prowadnicy');
    }

    /** Głębokość ograniczona ustawieniem: odmowa melduje się zdaniem, nie ciszą. */
    public function testTheDepthLimitReportsItselfInsteadOfDoingNothing(): void
    {
        $this->app->state->applySettings($this->app->state->settings()->withModuleValue(
            BrowserSettings::ID,
            BrowserSettings::TREE_DEPTH,
            '2',
        ));

        $this->app->browser->handle(KeyPress::ctrl('t'));
        $this->app->browser->handle(new KeyPress(Key::ArrowRight, ''));
        $this->app->browser->handle(new KeyPress(Key::ArrowDown, ''));
        $outcome = $this->app->browser->handle(new KeyPress(Key::ArrowRight, ''));

        self::assertNotNull($outcome->message);
        self::assertStringContainsString('module.browser.tree.depth', $outcome->message->text);
    }

    /** @return list<string> */
    private function contentTexts(): array
    {
        $renderer = new RecordingRenderer();
        (new FrameComposer($renderer, new FixedViewport(30, 100), new StubTranslator()))
            ->render($this->app->browser, $this->app->state);

        $frame = $renderer->last();

        self::assertNotNull($frame);
        $texts = [];

        foreach ($frame->planes as $plane) {
            if ($plane->id !== 'content') {
                continue;
            }

            foreach ($plane->primitives as $primitive) {
                if ($primitive instanceof TextRun) {
                    $texts[] = $primitive->text;
                }
            }
        }

        return $texts;
    }

    private function headerText(): string
    {
        $header = $this->app->browser->header();

        self::assertNotNull($header);

        return implode('', array_map(
            static fn (Primitive $primitive): string => $primitive instanceof TextRun ? $primitive->text : '',
            $header->content->draw(new Rect(0, 0, 1, 80)),
        ));
    }

    /**
     * Wiersze drzewa poznaje się po znaczniku odgałęzienia — jest w każdym i nie
     * ma go w żadnym wierszu listy.
     *
     * @param list<string> $texts
     *
     * @return list<string>
     */
    private static function treeRowsOf(array $texts): array
    {
        return array_values(array_filter(
            $texts,
            static fn (string $text): bool => str_contains($text, TreeView::BRANCH)
                || str_contains($text, TreeView::LAST),
        ));
    }

    /**
     * @param list<KeyBinding> $bindings
     *
     * @return list<string>
     */
    private static function bindingKeysOf(array $bindings): array
    {
        return array_map(static fn (KeyBinding $binding): string => $binding->descriptionKey, $bindings);
    }
}
