<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Browser\Presentation;

use LightManager\Application\Dto\Settings;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Application\UseCase\ExpandBranchUseCase;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Browser\Presentation\BrowserState;
use LightManager\Module\Browser\Presentation\BrowserTree;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Component\TreeNode;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Drzewo katalogów po stronie modułu (krok 31): spłaszczanie, odczyt gałęzi na
 * żądanie i limit głębokości.
 *
 * Ten zestaw pilnuje trzech obietnic, których nie widać w komponencie, bo
 * komponent dostaje drzewo **już spłaszczone**: że gałąź czyta się dopiero przy
 * rozwinięciu, że jeden odczyt starcza na całe życie gałęzi i że rozwinięcia
 * przeżywają wyjście z katalogu.
 */
final class BrowserTreeTest extends TestCase
{
    private InMemoryDirectoryRepository $directories;

    private LoopState $state;

    private BrowserState $pane;

    private BrowserTree $tree;

    protected function setUp(): void
    {
        $this->directories = (new InMemoryDirectoryRepository())
            ->add('/', [Entry::directory('home')])
            ->add('/home', [Entry::directory('projekty'), Entry::file('notatka.txt', 1024)])
            ->add('/home/projekty', [Entry::directory('lm'), Entry::file('plan.md', 2048)])
            ->add('/home/projekty/lm', [Entry::directory('src')])
            ->add('/home/projekty/lm/src', [Entry::directory('Domain')])
            ->add('/home/projekty/lm/src/Domain', [Entry::file('Message.php', 512)]);

        $this->state = new LoopState(new Settings());
        $this->pane = new BrowserState(
            $this->state,
            $this->directories->get(new DirectoryPath('/home'), false),
        );
        $this->tree = $this->treeOf();
    }

    /** Drzewo nietknięte pokazuje sam katalog panelu — i nie czyta niczego więcej. */
    public function testAFreshTreeShowsOnlyTheRootLevel(): void
    {
        $reads = $this->directories->reads;

        self::assertSame(['projekty/', 'notatka.txt'], self::labelsOf($this->tree->nodes()));
        self::assertSame($reads, $this->directories->reads, 'drzewo nietknięte nie sięga na dysk ani razu');
    }

    public function testExpandingReadsTheBranchOnceAndShowsItsEntries(): void
    {
        $reads = $this->directories->reads;
        $this->expand('/home/projekty');

        self::assertSame(['projekty/', 'lm/', 'plan.md', 'notatka.txt'], self::labelsOf($this->tree->nodes()));
        self::assertSame($reads + 1, $this->directories->reads, 'rozwinięcie to dokładnie jeden odczyt');
    }

    public function testCollapsingAndExpandingAgainCostsNoSecondRead(): void
    {
        $this->expand('/home/projekty');
        $reads = $this->directories->reads;

        $this->tree->collapse($this->nodeAt('/home/projekty'));
        $this->expand('/home/projekty');

        self::assertSame($reads, $this->directories->reads, 'gałąź raz przeczytana zostaje w pamięci');
    }

    /** Głębokość jest długością prowadnic, więc dzieci stoją poziom niżej od rodzica. */
    public function testChildrenSitOneLevelBelowTheirParent(): void
    {
        $this->expand('/home/projekty');

        $nodes = $this->tree->nodes();

        self::assertSame(0, $nodes[0]->depth());
        self::assertSame(1, $nodes[1]->depth());
        self::assertTrue($nodes[0]->expanded);
        self::assertFalse($nodes[0]->last, 'katalog nie jest ostatnim wpisem, więc prowadnica biegnie dalej');
    }

    /**
     * Kryterium kroku wyrażone wprost: rozwinięcia wracają w tym samym stanie po
     * powrocie do katalogu.
     */
    public function testExpansionsComeBackWhenThePaneReturnsToTheDirectory(): void
    {
        $this->expand('/home/projekty');

        $this->pane->enter($this->directories->get(new DirectoryPath('/'), false));
        self::assertSame(['home/'], self::labelsOf($this->tree->nodes()));

        $this->pane->enter($this->directories->get(new DirectoryPath('/home'), false));

        self::assertSame(['projekty/', 'lm/', 'plan.md', 'notatka.txt'], self::labelsOf($this->tree->nodes()));
    }

    /**
     * Limit głębokości: ósmy poziom jest domyślny, a przy dwóch trzeci węzeł nie
     * ma prawa powstać. Odmowa jest **jawna**, bo ekran ma z czego złożyć zdanie.
     */
    public function testDepthLimitRefusesToOpenABranchTooDeep(): void
    {
        $this->useDepth('2');
        $this->tree = $this->treeOf();

        self::assertTrue($this->tree->expand($this->nodeAt('/home/projekty')));

        $deeper = $this->nodeAt('/home/projekty/lm');

        self::assertFalse($this->tree->expand($deeper), 'drugi poziom jest ostatnim, jaki wolno pokazać');
        self::assertSame(2, $this->tree->limit());
    }

    public function testWithoutALimitTheTreeGoesAsDeepAsTheUserOpensIt(): void
    {
        $this->useDepth(BrowserSettings::TREE_DEPTH_UNLIMITED);
        $this->tree = $this->treeOf();

        foreach (['/home/projekty', '/home/projekty/lm', '/home/projekty/lm/src'] as $path) {
            self::assertTrue($this->tree->expand($this->nodeAt($path)));
        }

        self::assertNull($this->tree->limit());
        self::assertContains('Domain/', self::labelsOf($this->tree->nodes()));
    }

    /** Zmniejszenie limitu chowa to, co już było rozwinięte — bez ponownego odczytu. */
    public function testLoweringTheLimitHidesBranchesAlreadyOpen(): void
    {
        $this->expand('/home/projekty');
        $this->expand('/home/projekty/lm');

        self::assertContains('src/', self::labelsOf($this->tree->nodes()));

        $this->useDepth('2');

        self::assertNotContains('src/', self::labelsOf($this->tree->nodes()));
    }

    /** Kursor wskazuje węzeł, a `cursorDirectory()` — katalog, w którym ten węzeł leży. */
    public function testCursorDirectoryPointsAtTheOwnerOfTheNodeUnderTheCursor(): void
    {
        $this->expand('/home/projekty');
        $this->tree->state()->moveTo('/home/projekty/plan.md');

        $directory = $this->tree->cursorDirectory();

        self::assertSame('/home/projekty', $directory->path()->value);
        self::assertSame('plan.md', $directory->selectedEntry()?->name);
    }

    /**
     * Węzeł z pierwszego poziomu przesuwa zaznaczenie **listy**, bo katalogiem
     * panelu jest wtedy ten sam obiekt. Dzięki temu powrót do widoku listy staje
     * tam, gdzie stało drzewo.
     */
    public function testCursorOnTheRootLevelMovesTheSelectionOfThePaneItself(): void
    {
        $this->tree->state()->moveTo($this->nodeAt('/home/notatka.txt')->key);
        $this->tree->cursorDirectory();

        self::assertSame('notatka.txt', $this->pane->directory()->selectedEntry()?->name);
    }

    /**
     * Drzewo bez kursora zaczyna od zaznaczenia listy, a nie od pierwszego
     * wiersza. Kursor powstaje przy pierwszym spłaszczeniu, więc pytamy o niego
     * dopiero po nim — tak samo, jak robi to ekran, który najpierw rysuje.
     */
    public function testCursorStartsOnWhateverTheListHadSelected(): void
    {
        $this->pane->directory()->selectEntryNamed('notatka.txt');
        $tree = $this->treeOf();
        $tree->nodes();

        self::assertSame('/home/notatka.txt', $tree->state()->cursor());
    }

    /**
     * Gałąź pusta rozwija się **na znacznik, a nie na wiersze**: `▼` bez dzieci.
     *
     * Katalogu pustego nie odróżnia się od niepustego, dopóki się go nie
     * przeczyta, więc znacznik dostają wszystkie — i dopiero rozwinięcie mówi
     * prawdę. To jest ta sama uczciwość, co przy `hasChildren` w `TreeNode`.
     */
    public function testAnEmptyBranchOpensOnItsMarkerAndAddsNoRows(): void
    {
        $this->directories->add('/home/projekty', []);
        $this->pane->enter($this->directories->get(new DirectoryPath('/home'), false));
        $this->expand('/home/projekty');

        self::assertSame(['projekty/', 'notatka.txt'], self::labelsOf($this->tree->nodes()));
        self::assertTrue($this->nodeAt('/home/projekty')->expanded);
        self::assertTrue($this->nodeAt('/home/projekty')->hasChildren, 'katalog zostaje katalogiem, choć jest pusty');
    }

    /** Gałąź nieczytelna rozwija się **pusta**, a nie wyjątkiem — inaczej `/proc` gasiłby ekran. */
    public function testAnUnreadableBranchOpensEmpty(): void
    {
        $this->directories->makeUnreadable('/home/projekty');
        $this->expand('/home/projekty');

        self::assertSame(['projekty/', 'notatka.txt'], self::labelsOf($this->tree->nodes()));
    }

    /**
     * Rodzica szuka się wstecz po spłaszczonej liście, więc znajduje się go także
     * spod gałęzi rozwiniętej głęboko.
     */
    public function testFocusParentClimbsOneLevelAndStopsAtTheRootLevel(): void
    {
        $this->expand('/home/projekty');
        $this->tree->state()->moveTo('/home/projekty/plan.md');

        self::assertTrue($this->tree->focusParent($this->nodeAt('/home/projekty/plan.md')));
        self::assertSame('/home/projekty', $this->tree->state()->cursor());

        self::assertFalse(
            $this->tree->focusParent($this->nodeAt('/home/projekty')),
            'pierwszy poziom nie ma rodzica w drzewie — wyżej leży już dysk',
        );
    }

    private function expand(string $path): void
    {
        $this->tree->expand($this->nodeAt($path));
    }

    private function nodeAt(string $key): TreeNode
    {
        foreach ($this->tree->nodes() as $node) {
            if ($node->key === $key) {
                return $node;
            }
        }

        self::fail('brak węzła ' . $key . ' w drzewie');
    }

    private function useDepth(string $value): void
    {
        $this->state->applySettings($this->state->settings()->withModuleValue(
            BrowserSettings::ID,
            BrowserSettings::TREE_DEPTH,
            $value,
        ));
    }

    private function treeOf(): BrowserTree
    {
        return new BrowserTree(
            $this->pane,
            new ExpandBranchUseCase($this->directories),
            $this->state,
            new StubTranslator(),
        );
    }

    /**
     * @param list<TreeNode> $nodes
     *
     * @return list<string>
     */
    private static function labelsOf(array $nodes): array
    {
        return array_map(static fn (TreeNode $node): string => $node->label, $nodes);
    }
}
