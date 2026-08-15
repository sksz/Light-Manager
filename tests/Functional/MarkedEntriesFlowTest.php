<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Infrastructure\FileSystem\FileOperationsService;
use LightManager\Infrastructure\FileSystem\FileTransferService;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Infrastructure\EntryComparator;
use LightManager\Module\Browser\Infrastructure\FilesystemDirectoryRepository;
use LightManager\Presentation\Ui\Module\ReadsContext;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Tests\Support\ResetsSingletons;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * Zaznaczenie wielokrotne jako mnożnik operacji — krok 43.
 *
 * Miara powodzenia całego kroku brzmi: **zaznaczenie dwunastu plików
 * i naciśnięcie klawisza usuwania usuwa dwanaście plików, a pytanie mówi „12”,
 * a nie nazwę pierwszego z nich**. Test sprawdza dokładnie to zdanie — i cztery
 * rzeczy, na których ono stoi: znacznik widać w klatce, zbiór przeżywa filtr,
 * `Esc` zdejmuje warstwy po kolei, a po operacji zaznaczone zostaje to, czego
 * nie dotknęła.
 *
 * Dysk jest **prawdziwy**, jak w przebiegach kroków 41 i 42: zbiór ma tu być
 * mnożnikiem czynności, która naprawdę coś zmienia, a atrapa portu sprawdziłaby
 * wyłącznie samą siebie.
 */
final class MarkedEntriesFlowTest extends TestCase
{
    use ResetsSingletons;

    private const NOW = 100.0;

    /** Dwanaście — liczba z celu kroku, przepisana wprost. */
    private const MARKED = 12;

    private ScreenFixture $app;

    private string $root = '';

    protected function setUp(): void
    {
        $this->resetSingleton(FileOperationsService::class);
        $this->resetSingleton(FileTransferService::class);

        $this->root = sys_get_temp_dir() . '/lm-zaznaczenie-' . bin2hex(random_bytes(6));

        mkdir($this->root);

        // Piętnaście plików o znanych rozmiarach i jeden katalog: zbiór ma się
        // dać zaznaczyć w części, a suma rozmiarów — sprawdzić w liczbach.
        for ($index = 0; $index < 15; ++$index) {
            file_put_contents(sprintf('%s/plik-%02d.txt', $this->root, $index), str_repeat('x', 10));
        }

        mkdir($this->root . '/zdjęcia');
        file_put_contents($this->root . '/zdjęcia/mapa.png', 'png');

        $this->app = $this->fixture();
    }

    protected function tearDown(): void
    {
        self::removeTree($this->root);
        $this->resetSingleton(FileOperationsService::class);
        $this->resetSingleton(FileTransferService::class);
    }

    /** Spacja zaznacza wpis pod kursorem **i schodzi wiersz niżej** (rozstrzygnięcie 2). */
    public function testSpaceMarksTheEntryAndStepsDown(): void
    {
        $first = $this->selected();

        $this->mark();

        self::assertNotSame($first, $this->selected(), 'kursor zszedł niżej');
        self::assertSame(1, $this->markedCount());

        $this->mark();

        self::assertSame(2, $this->markedCount(), 'drugie naciśnięcie zaznacza następny, a nie odznacza pierwszy');
    }

    /** Spacja na wpisie już zaznaczonym odznacza go — przełącznik, nie dodawanie. */
    public function testSpaceOnAMarkedEntryUnmarksIt(): void
    {
        $this->mark();

        self::assertSame(1, $this->markedCount());

        $this->press(Key::ArrowUp);
        $this->mark();

        self::assertSame(0, $this->markedCount());
    }

    /**
     * `Shift`+strzałki — zaznaczanie zakresem (krok 44, D81 nr 12): spacja bez
     * podnoszenia palca. `Shift`+`↓` robi dokładnie to, co spacja, `Shift`+`↑`
     * to samo w górę — **przełącznik na wpisie, z którego wychodzi**, jak w Far:
     * zmiana kierunku najpierw dociąga wpis pod kursorem, a zdejmuje dopiero
     * drugie naciśnięcie, wracające po własnym śladzie.
     */
    public function testShiftArrowsMarkARange(): void
    {
        $first = $this->selected();

        $this->pressShifted(Key::ArrowDown);
        $this->pressShifted(Key::ArrowDown);
        $this->pressShifted(Key::ArrowDown);

        self::assertSame(3, $this->markedCount(), 'trzy wpisy jednym gestem');
        self::assertNotSame($first, $this->selected(), 'kursor zszedł z zakresem');

        // Zmiana kierunku: wpis pod kursorem (niezaznaczony) dołącza do zakresu…
        $this->pressShifted(Key::ArrowUp);

        self::assertSame(4, $this->markedCount());

        // …a powrót po własnym śladzie zdejmuje to, co zaznaczone.
        $this->pressShifted(Key::ArrowUp);

        self::assertSame(3, $this->markedCount());
    }

    /** Goła strzałka nie zaznacza — zakres wisi wyłącznie na `Shift` (reguła 11j dla nazw). */
    public function testAPlainArrowDoesNotMark(): void
    {
        $this->press(Key::ArrowDown);
        $this->press(Key::ArrowDown);

        self::assertSame(0, $this->markedCount());
    }

    /**
     * Zaznaczony wiersz niesie **dwa sygnały naraz** (rozstrzygnięcie 5): własną
     * kolumnę znacznika i rolę `Warning` na nazwie.
     *
     * Treści znacznika test nie sprawdza i nie jest to przeoczenie: atrapa
     * tłumacza oddaje **klucz**, a klucz przycina się do jednego znaku kolumny.
     * Prawdziwy znak stoi we wzorcu złotej klatki scenariusza `marked`, gdzie
     * napisy nie idą przez katalog (D33).
     */
    public function testAMarkedRowCarriesBothTheMarkerAndItsOwnRole(): void
    {
        $this->select('plik-00.txt');
        $this->mark();

        $rows = $this->rows();
        $name = self::runOf($rows, 'plik-00.txt');

        self::assertNotNull($name);
        self::assertSame(Role::Marked, $name->role, 'nazwa zaznaczonego mówi własną rolą');
        // Spacja zeszła wiersz niżej, więc kursor stoi na sąsiedzie — i to jest
        // dowód, że **oba stany widać naraz**: zaznaczenie rolą, kursor paskiem.
        self::assertSame(Role::SelectionText, self::runOf($rows, 'plik-01.txt')?->role);
        self::assertSame(Role::Text, self::runOf($rows, 'plik-02.txt')?->role, 'wiersz zwykły bez zmian');

        $markers = array_values(array_filter(
            $rows,
            static fn (TextRun $run): bool => $run->row === $name->row && $run->column < $name->column,
        ));

        self::assertCount(1, $markers, 'jeden znacznik, przed nazwą');
        self::assertSame(Role::Marked, $markers[0]->role);

        // Wiersz zaznaczony **i** pod kursorem: znacznik zostaje, a napis bierze
        // rolę paska — bez znacznika byłby nieodróżnialny od zwykłego wiersza
        // pod kursorem.
        $this->press(Key::ArrowUp);
        $rows = $this->rows();
        $name = self::runOf($rows, 'plik-00.txt');

        self::assertNotNull($name);
        self::assertSame(Role::SelectionText, $name->role);
        self::assertCount(1, array_filter(
            $rows,
            static fn (TextRun $run): bool => $run->row === $name->row && $run->column < $name->column,
        ), 'znacznik przeżywa kursor');
    }

    /**
     * **Kryterium zgodności wstecznej**: panel bez zaznaczenia wygląda co do znaku
     * jak przed krokiem — kolumna znacznika w ogóle nie powstaje, więc nazwa stoi
     * tam, gdzie stała.
     */
    public function testAnUnmarkedListLooksExactlyAsItDidBefore(): void
    {
        $before = self::columnOf($this->rows(), 'plik-00.txt');

        $this->mark();

        self::assertNotSame($before, self::columnOf($this->rows(), 'plik-00.txt'), 'znacznik przesuwa nazwę');

        $this->press(Key::Escape);

        self::assertSame($before, self::columnOf($this->rows(), 'plik-00.txt'), 'zbiór pusty — nazwa wraca');
    }

    /** Podsumowanie w pasie ścieżki mówi „ile z ilu” — z **pełnego** katalogu. */
    public function testThePathLineSummarisesTheSet(): void
    {
        $this->mark();
        $this->mark();

        self::assertStringContainsString('module.browser.marked.summary', $this->header());
    }

    /** Zbiór z katalogiem mówi wprost, że suma rozmiarów go pomija (rozstrzygnięcie 7). */
    public function testASetWithADirectorySaysTheSumLeavesItOut(): void
    {
        $this->select('zdjęcia');
        $this->mark();

        self::assertStringContainsString('module.browser.marked.summary.dirs', $this->header());
    }

    /** `*` odwraca zaznaczenie: puste staje się pełne, pełne — puste. */
    public function testTheStarInvertsTheMarks(): void
    {
        $this->press(Key::Character, '*');

        self::assertSame(16, $this->markedCount(), 'piętnaście plików i katalog');

        $this->press(Key::Character, '*');

        self::assertSame(0, $this->markedCount());
    }

    /**
     * **Zbiór przeżywa zawężenie filtrem** (rozstrzygnięcie 4): wpis wypchnięty
     * poza widok nadal do niego należy, a `*` odwraca wyłącznie to, co widać
     * (rozstrzygnięcie 8).
     */
    public function testTheSetSurvivesTheFilterAndTheStarTouchesOnlyWhatIsVisible(): void
    {
        $this->select('zdjęcia');
        $this->mark();

        self::assertSame(1, $this->markedCount());

        $this->filter('plik-0');
        $this->press(Key::Enter);

        self::assertSame(1, $this->markedCount(), 'katalog wypadł z widoku, ale nie ze zbioru');

        $this->press(Key::Character, '*');

        self::assertSame(11, $this->markedCount(), 'dziesięć widocznych plus katalog spoza widoku');
    }

    /**
     * `Esc` zdejmuje **warstwy po kolei**: najpierw filtr, potem zaznaczenie
     * (rozstrzygnięcie 3).
     */
    public function testEscapeDropsTheFilterFirstAndTheMarksSecond(): void
    {
        $this->mark();
        $this->filter('plik');
        $this->press(Key::Enter);

        $this->press(Key::Escape);

        self::assertSame(16, $this->visibleCount(), 'filtr zdjęty');
        self::assertSame(1, $this->markedCount(), 'zaznaczenie jeszcze stoi');

        $this->press(Key::Escape);

        self::assertSame(0, $this->markedCount(), 'drugie naciśnięcie zdejmuje zbiór');
    }

    /** Zbiór ginie przy wejściu do innego katalogu — jak filtr i z tego samego powodu. */
    public function testTheSetDiesWithTheDirectory(): void
    {
        $this->select('plik-00.txt');
        $this->mark();
        $this->select('zdjęcia');
        $this->press(Key::Enter);

        self::assertSame($this->root . '/zdjęcia', $this->app->state->context()->path);
        self::assertSame(0, $this->markedCount());
    }

    /**
     * **Miara powodzenia całego kroku.** Dwanaście zaznaczonych plików, jedno
     * naciśnięcie `Shift`+`F8` — pytanie mówi liczbą, a po zgodzie znika
     * dwanaście plików. (Do kroku 44 wisiało to na gołym `F8`; goły klawisz
     * wiezie odtąd do kosza, a zbiór w koszu sprawdza `TrashFlowTest`.)
     */
    public function testTwelveMarkedEntriesAreDeletedByOneKeyAndOneQuestion(): void
    {
        $this->select('plik-00.txt');
        $this->markMany(self::MARKED);

        $this->pressShifted(Key::F8);

        self::assertSame('confirm', $this->app->state->overlays()->current()?->id());
        self::assertCount(16, (array) glob($this->root . '/*'), 'samo pytanie nie usuwa');

        $this->confirm();
        $this->settle();

        self::assertCount(4, (array) glob($this->root . '/*'), 'dwanaście plików zniknęło');
        self::assertStringStartsWith('module.browser.delete.done', (string) $this->message());
    }

    /** Pytanie o zbiór mówi **liczbą**, a nie nazwą pierwszego z dwunastu. */
    public function testTheQuestionAboutASetSpeaksWithANumber(): void
    {
        $this->select('plik-00.txt');
        $this->markMany(self::MARKED);
        $this->pressShifted(Key::F8);

        $question = $this->overlayText();

        self::assertStringContainsString('module.browser.delete.confirm.many', $question);
        self::assertStringNotContainsString('plik-00.txt', $question);
    }

    /**
     * **Reguła pustego zbioru**: bez zaznaczenia czynność dotyczy wpisu pod
     * kursorem, dokładnie jak przed tym krokiem.
     */
    public function testWithNothingMarkedTheActionStillMeansTheEntryUnderTheCursor(): void
    {
        $this->select('plik-03.txt');
        $this->pressShifted(Key::F8);

        self::assertStringContainsString('module.browser.delete.confirm.file', $this->overlayText());

        $this->confirm();
        $this->settle();

        self::assertFileDoesNotExist($this->root . '/plik-03.txt');
        self::assertFileExists($this->root . '/plik-04.txt', 'sąsiad nietknięty');
    }

    /**
     * **Po operacji zaznaczone zostaje to, czego nie dotknęła.** Tu dotknęła
     * wszystkiego, co było w zbiorze — więc zbiór zostaje pusty, a wpisy spoza
     * niego nie zostają zaznaczone przy okazji.
     */
    public function testAfterTheDeletionTheSetHoldsOnlyWhatSurvived(): void
    {
        $this->select('plik-00.txt');
        $this->markMany(3);

        self::assertSame(3, $this->markedCount());

        $this->pressShifted(Key::F8);
        $this->confirm();
        $this->settle();

        self::assertSame(0, $this->markedCount(), 'usunięte wypadły ze zbioru');
        self::assertSame(13, $this->visibleCount());
    }

    /** Kopiowanie bierze **cały zbiór**, a nie wpis pod kursorem. */
    public function testCopyingTakesTheWholeSet(): void
    {
        mkdir($this->root . '/cel');
        $this->app = $this->fixture();

        $this->select('plik-00.txt');
        $this->markMany(3);

        $this->press(Key::F5);
        $this->clear();
        $this->type($this->root . '/cel');
        $this->press(Key::Enter);
        $this->settle();

        self::assertCount(3, (array) glob($this->root . '/cel/*'), 'trzy zaznaczone pliki są w celu');
        self::assertFileExists($this->root . '/plik-00.txt', 'kopiowanie zostawia źródło');
    }

    /** Kursor po usunięciu zbioru **przeskakuje resztę zaznaczonych**. */
    public function testTheCursorLandsBelowTheLastDeletedEntry(): void
    {
        $this->select('plik-00.txt');
        $this->markMany(3);
        $this->pressShifted(Key::F8);
        $this->confirm();
        $this->settle();

        self::assertSame('plik-03.txt', $this->selected());
    }

    /**
     * Zaznaczenie jest własnością **listy**: w drzewie spacja nie zaznacza,
     * a zbiór z listy przestaje istnieć dla wszystkich (D80, rozstrzygnięcie 9).
     */
    public function testTheTreeNeitherMarksNorSeesTheSet(): void
    {
        $this->mark();

        self::assertSame(1, $this->markedCount());

        $this->app->input->handle(KeyPress::ctrl('t'), $this->app->state, self::NOW);

        self::assertStringNotContainsString('module.browser.marked.summary', $this->header());

        $this->mark();

        self::assertStringNotContainsString('module.browser.marked.summary', $this->header());

        $this->app->input->handle(KeyPress::ctrl('t'), $this->app->state, self::NOW);

        self::assertSame(1, $this->markedCount(), 'powrót do listy zastaje zbiór takim, jaki był');
    }

    /** Moduł opisu pliku jest odbiorcą zbioru w kontekście sesji (reguła 13). */
    public function testTheFileInfoModuleReadsTheSetFromTheSessionContext(): void
    {
        $this->markMany(2);

        $screen = $this->app->fileInfo;

        self::assertInstanceOf(ReadsContext::class, $screen);
        $screen->useContext($this->app->state->context());
        $header = $screen->header();

        self::assertNotNull($header);
        self::assertStringContainsString(
            'module.file-info.marked',
            implode('', array_map(
                static fn (TextRun $run): string => $run->text,
                self::runsOf($header->content->draw(new Rect(0, 0, 1, 120))),
            )),
        );
    }

    /** Zaznaczenie tylu wpisów, ile trzeba — spacja sama schodzi niżej. */
    private function markMany(int $count): void
    {
        for ($index = 0; $index < $count; ++$index) {
            $this->mark();
        }

        self::assertSame($count, $this->markedCount());
    }

    private function mark(): void
    {
        $this->press(Key::Character, ' ');
    }

    private function filter(string $fragment): void
    {
        $this->press(Key::Character, '/');
        $this->type($fragment);
    }

    /** Praca kawałkowa posuwa się taktem pętli, nie klawiszem. */
    private function settle(): void
    {
        $ticks = 0;

        while ($this->app->state->overlays()->isOpen() && $ticks++ < 40) {
            $this->app->input->advanceWork($this->app->state, self::NOW);
        }
    }

    private function markedCount(): int
    {
        return $this->app->state->context()->markedCount;
    }

    private function visibleCount(): int
    {
        return count(self::namesIn($this->rows()));
    }

    private function header(): string
    {
        $screen = $this->app->browser;
        $header = $screen->header();

        if ($header === null) {
            return '';
        }

        return implode('', array_map(
            static fn (TextRun $run): string => $run->text,
            self::runsOf($header->content->draw(new Rect(0, 0, 1, 200))),
        ));
    }

    private function overlayText(): string
    {
        $overlay = $this->app->state->overlays()->current();

        if ($overlay === null) {
            return '';
        }

        return implode('|', array_map(
            static fn (TextRun $run): string => $run->text,
            self::runsOf($overlay->draw($overlay->bounds(30, 100))),
        ));
    }

    /**
     * Wiersze listy — panel szeroki, bo atrapa tłumacza oddaje **klucze**, a te
     * są dłuższe od napisów i w wąskiej kolumnie kończą się wielokropkiem.
     *
     * @return list<TextRun>
     */
    private function rows(): array
    {
        $screen = $this->app->browser;

        self::assertInstanceOf(ScreenInterface::class, $screen);

        return self::runsOf($screen->draw(new Rect(0, 0, 25, 120)));
    }

    /** @param list<TextRun> $runs */
    private static function columnOf(array $runs, string $text): ?int
    {
        return self::runOf($runs, $text)?->column;
    }

    /** @param list<TextRun> $runs */
    private static function runOf(array $runs, string $text): ?TextRun
    {
        foreach ($runs as $run) {
            if ($run->text === $text) {
                return $run;
            }
        }

        return null;
    }

    /**
     * @param list<TextRun> $runs
     *
     * @return list<string>
     */
    private static function namesIn(array $runs): array
    {
        $names = [];

        foreach ($runs as $run) {
            if (str_starts_with($run->text, 'plik-') || $run->text === 'zdjęcia/') {
                $names[] = $run->text;
            }
        }

        return $names;
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<TextRun>
     */
    private static function runsOf(array $primitives): array
    {
        $runs = [];

        foreach ($primitives as $primitive) {
            if ($primitive instanceof TextRun) {
                $runs[] = $primitive;
            }
        }

        return $runs;
    }

    private function fixture(): ScreenFixture
    {
        $directories = new FilesystemDirectoryRepository(EntryComparator::create());

        return new ScreenFixture(
            $directories->get(new DirectoryPath($this->root), false),
            $directories,
            $this->app->settingsStore ?? new \LightManager\Tests\Support\InMemorySettings(),
            operations: FileOperationsService::getInstance(),
            transfers: FileTransferService::getInstance(),
        );
    }

    /** `Shift`+klawisz — od kroku 44 droga trwała przy domyślnych ustawieniach. */
    private function pressShifted(Key $key): void
    {
        $this->app->input->handle(KeyPress::shifted($key, "\e"), $this->app->state, self::NOW);
    }

    private function press(Key $key, string $raw = "\e"): void
    {
        $press = $key === Key::Character ? KeyPress::character($raw) : KeyPress::special($key, $raw);

        $this->app->input->handle($press, $this->app->state, self::NOW);
    }

    private function type(string $text): void
    {
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $this->app->input->handle(KeyPress::character($character), $this->app->state, self::NOW);
        }
    }

    private function clear(): void
    {
        for ($index = 0; $index < 80; ++$index) {
            $this->app->input->handle(KeyPress::special(Key::Backspace, "\x7f"), $this->app->state, self::NOW);
        }
    }

    /** Zgoda w oknie potwierdzenia: ognisko startuje na „nie”, więc najpierw strzałka. */
    private function confirm(): void
    {
        $this->press(Key::ArrowRight);
        $this->press(Key::Enter);
    }

    /**
     * Kursor na wskazanym wpisie — **od góry listy**, bo katalogi stoją w niej
     * przed plikami, a spacja schodzi w dół i potrafi wpis wyprzedzić.
     */
    private function select(string $name): void
    {
        for ($index = 0; $index < 30; ++$index) {
            $this->press(Key::ArrowUp);
        }

        $guard = 0;

        while ($this->selected() !== $name && $guard++ < 50) {
            $this->press(Key::ArrowDown);
        }

        self::assertSame($name, $this->selected());
    }

    private function selected(): ?string
    {
        return $this->app->state->context()->selection;
    }

    private function message(): ?string
    {
        return $this->app->state->message()?->text;
    }

    private static function removeTree(string $path): void
    {
        if ($path === '' || !is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $child = $path . '/' . $name;

            if (!is_link($child) && is_dir($child)) {
                self::removeTree($child);

                continue;
            }

            unlink($child);
        }

        rmdir($path);
    }
}
