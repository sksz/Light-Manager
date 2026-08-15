<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Infrastructure\FileSystem\FileOperationsService;
use LightManager\Infrastructure\FileSystem\XdgTrashService;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Browser\Infrastructure\EntryComparator;
use LightManager\Module\Browser\Infrastructure\FilesystemDirectoryRepository;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\ResetsSingletons;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubTrash;
use PHPUnit\Framework\TestCase;

/**
 * Kosz całą drogą użytkownika (krok 44, D81).
 *
 * Dysk jest **prawdziwy**: katalog tymczasowy, prawdziwe repozytorium
 * i prawdziwa usługa kosza — a sam kosz jest **podstawiony** pozycją ustawień
 * modułu, więc test nie dosypuje niczego do kosza osoby, która go uruchamia.
 *
 * Trzy rzeczy są tu sednem: **klawisz domyślny wiezie do kosza** (D81, nr 2
 * i 9), **wpis w koszu ma plik informacyjny ze ścieżką powrotną** (miara
 * powodzenia kroku) i **wpis spoza systemu plików kosza dostaje pytanie
 * o trzech odpowiedziach, nigdy cichą decyzję** (nr 5).
 */
final class TrashFlowTest extends TestCase
{
    use ResetsSingletons;

    private const NOW = 100.0;

    private ScreenFixture $app;

    private string $root = '';

    private string $bin = '';

    protected function setUp(): void
    {
        $this->resetSingleton(FileOperationsService::class);
        $this->resetSingleton(XdgTrashService::class);

        $this->root = sys_get_temp_dir() . '/lm-kosz-flow-' . bin2hex(random_bytes(6));
        $this->bin = $this->root . '/.kosz';

        mkdir($this->root);
        file_put_contents($this->root . '/notatka.txt', 'treść');
        file_put_contents($this->root . '/raport.pdf', 'pdf');
        mkdir($this->root . '/zdjęcia');
        file_put_contents($this->root . '/zdjęcia/mapa.png', 'png');

        $this->app = $this->fixture();
        $this->useTrashDirectory($this->bin);
    }

    protected function tearDown(): void
    {
        self::removeTree($this->root);
        $this->resetSingleton(FileOperationsService::class);
        $this->resetSingleton(XdgTrashService::class);
    }

    /**
     * **Miara powodzenia kroku**: wpis usunięty domyślnym klawiszem leży
     * w koszu wraz z poprawnym plikiem informacyjnym, a jedno cofnięcie
     * przywraca go z powrotem — tu połowa pierwsza, przywracanie sprawdza
     * `UndoFlowTest`.
     */
    public function testTheDefaultKeySendsTheEntryToTheTrashWithItsInfoFile(): void
    {
        $this->select('notatka.txt');
        $this->press(Key::F8);

        self::assertSame('confirm', $this->app->state->overlays()->current()?->id());
        self::assertStringContainsString('module.browser.trash.confirm.file', $this->overlayText());
        self::assertFileExists($this->root . '/notatka.txt', 'samo pytanie nie przenosi');

        $this->confirm();

        self::assertFileDoesNotExist($this->root . '/notatka.txt');
        self::assertFileExists($this->bin . '/files/notatka.txt');

        $info = (string) file_get_contents($this->bin . '/info/notatka.txt.trashinfo');

        self::assertStringContainsString('Path=' . $this->root . '/notatka.txt', $info);
        self::assertStringStartsWith('module.browser.trash.doneOne', (string) $this->message());
        self::assertSame('raport.pdf', $this->selected(), 'kursor spada na następcę');
    }

    /** Katalog z zawartością jedzie do kosza **bez liczenia i bez okien pracy** — jedną zmianą nazwy. */
    public function testADirectoryGoesWholeWithoutAnyWorkWindows(): void
    {
        $this->select('zdjęcia');
        $this->press(Key::F8);

        self::assertSame('confirm', $this->app->state->overlays()->current()?->id());

        $this->confirm();

        self::assertNull($this->app->state->overlays()->current(), 'zero okien pracy — to jest zysk kosza');
        self::assertDirectoryDoesNotExist($this->root . '/zdjęcia');
        self::assertSame('png', file_get_contents($this->bin . '/files/zdjęcia/mapa.png'));
    }

    /** Ustawienie „pytaj przed usunięciem” rządzi odtąd koszem: wyłączone — wpis jedzie od razu. */
    public function testWithTheQuestionTurnedOffTheEntryGoesAtOnce(): void
    {
        $settings = $this->app->state->settings()->withModuleValue(
            BrowserSettings::ID,
            BrowserSettings::ASK_BEFORE_DELETE,
            false,
        );
        $this->app->settingsStore->save($settings);
        $this->app = $this->fixture();

        $this->select('notatka.txt');
        $this->press(Key::F8);

        self::assertNull($this->app->state->overlays()->current(), 'pytania nie ma — czynność jest odwracalna');
        self::assertFileExists($this->bin . '/files/notatka.txt');
    }

    /** Zbiór zaznaczonych: jedno pytanie z liczbą, a w koszu staje komplet. */
    public function testAMarkedSetGoesWithOneQuestion(): void
    {
        $this->select('notatka.txt');
        $this->mark();
        $this->mark();

        $this->press(Key::F8);

        self::assertStringContainsString('module.browser.trash.confirm.many', $this->overlayText());

        $this->confirm();

        self::assertFileExists($this->bin . '/files/notatka.txt');
        self::assertFileExists($this->bin . '/files/raport.pdf');
        self::assertStringStartsWith('module.browser.trash.done', (string) $this->message());
    }

    /** Kolizja nazw w koszu: sufiks liczbowy, bez pytania (D81, nr 11). */
    public function testANameCollisionInTheTrashTakesASuffixSilently(): void
    {
        $this->trashEntry('notatka.txt');
        file_put_contents($this->root . '/notatka.txt', 'drugi plik o tej nazwie');
        $this->app = $this->fixture();

        $this->trashEntry('notatka.txt');

        self::assertSame('treść', file_get_contents($this->bin . '/files/notatka.txt'));
        self::assertSame('drugi plik o tej nazwie', file_get_contents($this->bin . '/files/notatka.1.txt'));
    }

    /** Ustawienie przestawione: goły klawisz usuwa trwale, a `Shift` wiezie do kosza. */
    public function testTheSettingSwapsTheMeaningOfBothRoads(): void
    {
        $settings = $this->app->state->settings()->withModuleValue(
            BrowserSettings::ID,
            BrowserSettings::DELETE_TO_TRASH,
            false,
        );
        $this->app->settingsStore->save($settings);
        $this->app = $this->fixture();

        $this->select('notatka.txt');
        $this->pressShifted(Key::F8);

        self::assertStringContainsString('module.browser.trash.confirm.file', $this->overlayText());

        $this->confirm();

        self::assertFileExists($this->bin . '/files/notatka.txt', 'Shift robi zawsze to drugie');
    }

    /**
     * Wpis spoza systemu plików kosza: ostrzeżenie i pytanie o trzech
     * odpowiedziach (D81, nr 5). Przebieg idzie na atrapach, bo prawdziwej
     * granicy wolumenów nie da się postawić w katalogu tymczasowym.
     */
    public function testAForeignEntryGetsTheThreeWayQuestion(): void
    {
        $app = $this->foreignFixture();

        $this->driveTo($app, 'obcy.txt');
        $app->input->handle(KeyPress::special(Key::F8, "\e"), $app->state, self::NOW);

        self::assertSame('choice', $app->state->overlays()->current()?->id());

        $text = self::textOf($app);

        self::assertStringContainsString('module.browser.trash.foreign', $text);
        self::assertStringContainsString('module.browser.trash.foreign.copy', $text);
        self::assertStringContainsString('module.browser.trash.foreign.delete', $text);
        self::assertStringContainsString('module.browser.trash.foreign.abort', $text);
    }

    /** Odpowiedź „przerwij”: nic się nie dzieje i zdanie mówi to wprost. */
    public function testAbortingTheForeignQuestionTouchesNothing(): void
    {
        $app = $this->foreignFixture();

        $this->driveTo($app, 'obcy.txt');
        $app->input->handle(KeyPress::special(Key::F8, "\e"), $app->state, self::NOW);
        $app->input->handle(KeyPress::special(Key::Escape, "\e"), $app->state, self::NOW);

        self::assertNull($app->state->overlays()->current());
        self::assertStringStartsWith('module.browser.trash.abandoned', (string) $app->state->message()?->text);

        $trash = $app->trash;

        self::assertInstanceOf(StubTrash::class, $trash);
        self::assertSame([], $trash->performed, 'kosz nietknięty');
    }

    /** Odpowiedź „usuń trwale” prowadzi w drogę z kroku 41 — wraz z jej groźnym pytaniem. */
    public function testChoosingPermanentDeletionAsksTheDangerousQuestion(): void
    {
        $app = $this->foreignFixture();

        $this->driveTo($app, 'obcy.txt');
        $app->input->handle(KeyPress::special(Key::F8, "\e"), $app->state, self::NOW);

        // Druga pozycja listy — „usuń trwale”.
        $app->input->handle(KeyPress::special(Key::ArrowDown, "\e"), $app->state, self::NOW);
        $app->input->handle(KeyPress::special(Key::Enter, "\r"), $app->state, self::NOW);

        self::assertSame('confirm', $app->state->overlays()->current()?->id());
        self::assertStringContainsString('module.browser.delete.confirm.file', self::textOf($app));
    }

    /** Odpowiedź „skopiuj”: nazwa rezerwuje się w koszu, a praca dostaje ją mapą. */
    public function testChoosingCopySendsTheReservedNameToTheTransfer(): void
    {
        $app = $this->foreignFixture();

        $this->driveTo($app, 'obcy.txt');
        $app->input->handle(KeyPress::special(Key::F8, "\e"), $app->state, self::NOW);
        $app->input->handle(KeyPress::special(Key::Enter, "\r"), $app->state, self::NOW);

        $trash = $app->trash;

        self::assertInstanceOf(StubTrash::class, $trash);
        self::assertContains('reserve:/obcy.txt', $trash->performed, 'plik informacyjny przed kopiowaniem');

        $transfers = $app->transfers;

        self::assertInstanceOf(\LightManager\Tests\Support\StubFileTransfers::class, $transfers);
        self::assertNotSame([], $transfers->performed);
        self::assertStringContainsString(
            'move:/obcy.txt→/stub/Trash/files jako obcy.txt',
            $transfers->performed[0],
            'praca dostaje nazwę zarezerwowaną w koszu',
        );
    }

    /** Zestaw na atrapach: jeden wpis, którego kosz nie przyjmie zmianą nazwy. */
    private function foreignFixture(): ScreenFixture
    {
        $directories = (new InMemoryDirectoryRepository())->add('/', [
            Entry::file('obcy.txt', 5),
            Entry::file('swój.txt', 5),
        ]);

        return new ScreenFixture(
            $directories->get(new DirectoryPath('/'), false),
            $directories,
            trash: new StubTrash(foreign: ['/obcy.txt']),
        );
    }

    private function driveTo(ScreenFixture $app, string $name): void
    {
        $guard = 0;

        while ($app->state->context()->selection !== $name && $guard++ < 20) {
            $app->input->handle(KeyPress::special(Key::ArrowDown, "\e"), $app->state, self::NOW);
        }

        self::assertSame($name, $app->state->context()->selection);
    }

    private static function textOf(ScreenFixture $app): string
    {
        $overlay = $app->state->overlays()->current();

        self::assertNotNull($overlay);

        $text = '';

        foreach ($overlay->draw(new Rect(0, 0, 10, 100)) as $primitive) {
            if ($primitive instanceof TextRun) {
                $text .= ' ' . $primitive->text;
            }
        }

        return $text;
    }

    /** Cała droga jednego wpisu do kosza: pytanie i zgoda. */
    private function trashEntry(string $name): void
    {
        $this->select($name);
        $this->press(Key::F8);
        $this->confirm();
    }

    private function useTrashDirectory(string $directory): void
    {
        $settings = $this->app->state->settings()->withModuleValue(
            BrowserSettings::ID,
            BrowserSettings::TRASH_DIRECTORY,
            $directory,
        );
        $this->app->settingsStore->save($settings);
        $this->app = $this->fixture();
    }

    private function fixture(): ScreenFixture
    {
        $directories = new FilesystemDirectoryRepository(EntryComparator::create());

        return new ScreenFixture(
            $directories->get(new DirectoryPath($this->root), false),
            $directories,
            $this->app->settingsStore ?? new InMemorySettings(),
            operations: FileOperationsService::getInstance(),
            trash: XdgTrashService::getInstance(),
        );
    }

    private function overlayText(): string
    {
        return self::textOf($this->app);
    }

    private function press(Key $key): void
    {
        $this->app->input->handle(KeyPress::special($key, "\e"), $this->app->state, self::NOW);
    }

    private function pressShifted(Key $key): void
    {
        $this->app->input->handle(KeyPress::shifted($key, "\e"), $this->app->state, self::NOW);
    }

    private function mark(): void
    {
        $this->app->input->handle(KeyPress::character(' '), $this->app->state, self::NOW);
    }

    /** Zgoda w oknie potwierdzenia: ognisko startuje na „nie”, więc najpierw strzałka. */
    private function confirm(): void
    {
        $this->press(Key::ArrowRight);
        $this->press(Key::Enter);
    }

    private function select(string $name): void
    {
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

        foreach ((array) scandir($path) as $name) {
            if ($name === '.' || $name === '..' || !is_string($name)) {
                continue;
            }

            $child = $path . '/' . $name;
            is_dir($child) && !is_link($child) ? self::removeTree($child) : unlink($child);
        }

        rmdir($path);
    }
}
