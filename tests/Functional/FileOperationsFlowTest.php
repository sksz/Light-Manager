<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Infrastructure\FileSystem\FileOperationsService;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Infrastructure\EntryComparator;
use LightManager\Module\Browser\Infrastructure\FilesystemDirectoryRepository;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Tests\Support\ResetsSingletons;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * Przeglądarka **zmienia** to, co pokazuje (krok 41).
 *
 * Przebieg idzie w całości przez `InputHandler`, bo połowa czynności dzieje się
 * w oknach nakładanych — a `ScreenOutcome::opens()` bez rdzenia przepada. Dysk
 * jest **prawdziwy**: katalog tymczasowy i prawdziwe repozytorium, bo atrapa
 * systemu plików sprawdziłaby tu wyłącznie samą siebie.
 *
 * Trzy rzeczy są tu sednem: **skutek widać w tej samej klatce w obu panelach**,
 * **odmowa nie dotyka dysku** i **praca dłuższa od klatki posuwa się taktem
 * pętli**, a nie naciśnięciami klawiszy.
 */
final class FileOperationsFlowTest extends TestCase
{
    use ResetsSingletons;

    /** Ile wpisów przekracza jeden kawałek usuwania (256) — potrzebne dla okna postępu. */
    private const CHUNK_OVERFLOW = 300;

    private const NOW = 100.0;

    private ScreenFixture $app;

    private string $root = '';

    protected function setUp(): void
    {
        $this->resetSingleton(FileOperationsService::class);

        $this->root = sys_get_temp_dir() . '/lm-operacje-' . bin2hex(random_bytes(6));

        mkdir($this->root);
        file_put_contents($this->root . '/notatka.txt', 'treść');
        mkdir($this->root . '/zdjęcia');
        file_put_contents($this->root . '/zdjęcia/mapa.png', 'png');

        $this->app = $this->fixture();
    }

    protected function tearDown(): void
    {
        self::removeTree($this->root);
        $this->resetSingleton(FileOperationsService::class);
    }

    /** `F7`, nazwa, `Enter` — katalog jest na dysku i na liście, z kursorem na nim. */
    public function testCreatingADirectoryShowsUpOnTheDiskAndOnTheList(): void
    {
        $this->press(Key::F7);
        $this->type('projekty');
        $this->press(Key::Enter);

        self::assertDirectoryExists($this->root . '/projekty');
        self::assertContains('projekty', $this->names());
        self::assertSame('projekty', $this->selected(), 'kursor staje na tym, co właśnie powstało');
        self::assertStringStartsWith('module.browser.mkdir.done', (string) $this->message());
    }

    /** `Esc` w oknie nazwy nie dotyka dysku — dowód wprost. */
    public function testEscapeInThePromptTouchesNothing(): void
    {
        $before = $this->names();

        $this->press(Key::F7);
        $this->type('nie-powstanie');
        $this->press(Key::Escape);

        self::assertDirectoryDoesNotExist($this->root . '/nie-powstanie');
        self::assertSame($before, $this->names());
    }

    /** Nazwa zajęta: okno **zostaje otwarte**, bo jest co poprawić. */
    public function testATakenNameKeepsThePromptOpenWithItsSentence(): void
    {
        $this->press(Key::F7);
        $this->type('zdjęcia');
        $this->press(Key::Enter);

        self::assertSame('prompt', $this->app->state->overlays()->current()?->id());
        self::assertStringStartsWith('problem.fileops.taken', (string) $this->message());
    }

    /** Ukośnik w nazwie jest błędem: okno pyta o nazwę, nie o ścieżkę. */
    public function testASlashInTheNameIsRefusedByTheModule(): void
    {
        $this->press(Key::F7);
        $this->type('a/b');
        $this->press(Key::Enter);

        self::assertStringStartsWith('module.browser.name.separator', (string) $this->message());
        self::assertDirectoryDoesNotExist($this->root . '/a');
    }

    /** `F6` z nazwą bieżącą w polu: kursor idzie **za nową nazwą**. */
    public function testRenamingFollowsTheNewName(): void
    {
        $this->select('notatka.txt');

        $this->press(Key::F6);
        $this->clear();
        $this->type('umowa.txt');
        $this->press(Key::Enter);

        self::assertFileDoesNotExist($this->root . '/notatka.txt');
        self::assertSame('treść', file_get_contents($this->root . '/umowa.txt'));
        self::assertSame('umowa.txt', $this->selected());
        self::assertStringStartsWith('module.browser.rename.done', (string) $this->message());
    }

    /** Usunięcie pliku: pytanie w wariancie groźnym, a po zgodzie wpis znika z listy. */
    public function testDeletingAFileAsksFirstAndThenRemovesIt(): void
    {
        $this->select('notatka.txt');
        $this->press(Key::F8);

        self::assertSame('confirm', $this->app->state->overlays()->current()?->id());
        self::assertFileExists($this->root . '/notatka.txt', 'samo pytanie nie usuwa');

        $this->confirm();

        self::assertFileDoesNotExist($this->root . '/notatka.txt');
        self::assertNotContains('notatka.txt', $this->names());
        self::assertStringStartsWith('module.browser.delete.doneOne', (string) $this->message());
    }

    /** Odmowa („nie” domyślnie pod ogniskiem) nie dotyka dysku. */
    public function testRefusingTheQuestionLeavesTheFileAlone(): void
    {
        $this->select('notatka.txt');
        $this->press(Key::F8);
        $this->press(Key::Enter);

        self::assertFileExists($this->root . '/notatka.txt');
        self::assertNull($this->app->state->overlays()->current());
    }

    /** `Delete` jest drugą drogą do tej samej czynności. */
    public function testTheDeleteKeyIsTheSameAction(): void
    {
        $this->select('notatka.txt');
        $this->press(Key::Delete);

        self::assertSame('confirm', $this->app->state->overlays()->current()?->id());
    }

    /**
     * Katalog z zawartością: pytanie zna **liczbę wpisów**, bo praca policzyła je
     * przed pytaniem — a przy małym drzewie zmieściła się w pierwszym kawałku, więc
     * okna liczenia nie było w ogóle.
     */
    public function testDeletingADirectoryCountsItsContentsBeforeAsking(): void
    {
        $this->select('zdjęcia');
        $this->press(Key::F8);

        $overlay = $this->app->state->overlays()->current();

        self::assertSame('confirm', $overlay?->id(), 'małe drzewo liczy się w jednym kawałku');
        self::assertDirectoryExists($this->root . '/zdjęcia');

        $this->confirm();

        self::assertDirectoryDoesNotExist($this->root . '/zdjęcia');
        self::assertStringStartsWith('module.browser.delete.done', (string) $this->message());
    }

    /**
     * Praca dłuższa od klatki: okno postępu staje **samo**, a posuwa się taktem
     * pętli — bez ani jednego klawisza.
     */
    public function testALongDeletionOpensTheProgressWindowAndFinishesByItself(): void
    {
        $this->makeCrowdedDirectory();
        $this->select('tłum');
        $this->press(Key::F8);
        $this->confirm();

        self::assertSame('progress', $this->app->state->overlays()->current()?->id());
        self::assertDirectoryExists($this->root . '/tłum', 'jeden kawałek nie usuwa całości');

        $ticks = 0;

        while ($this->app->state->overlays()->isOpen() && $ticks++ < 20) {
            $this->app->input->advanceWork($this->app->state, self::NOW);
        }

        self::assertDirectoryDoesNotExist($this->root . '/tłum');
        self::assertStringStartsWith('module.browser.delete.done', (string) $this->message());
        self::assertNotContains('tłum', $this->names());
    }

    /**
     * Liczenie, które **nie zmieściło się w pierwszym kawałku**, dostaje własne
     * okno — i ustępuje miejsca pytaniu, gdy skończy (D75, nr 10).
     *
     * To jedyny przypadek, w którym widać cały łańcuch trzech okien: liczenie →
     * pytanie z liczbą → usuwanie. Wymaga drzewa większego niż jeden kawałek
     * liczenia (512 wpisów), więc jest tu najdroższy test przebiegu — i wart tej
     * ceny, bo tańszym nie da się sprawdzić `OverlayOutcome::replace()`.
     */
    public function testALongCountGetsItsOwnWindowAndHandsOverToTheQuestion(): void
    {
        $this->makeBranchyDirectory(600);
        $this->select('tłum');
        $this->press(Key::F8);

        self::assertSame('progress', $this->app->state->overlays()->current()?->id(), 'okno liczenia');
        self::assertDirectoryExists($this->root . '/tłum', 'liczenie nie dotyka dysku');

        $ticks = 0;

        while ($this->app->state->overlays()->current()?->id() === 'progress' && $ticks++ < 20) {
            $this->app->input->advanceWork($this->app->state, self::NOW);
        }

        self::assertSame('confirm', $this->app->state->overlays()->current()?->id(), 'policzone — pora zapytać');

        $this->confirm();

        $ticks = 0;

        while ($this->app->state->overlays()->isOpen() && $ticks++ < 20) {
            $this->app->input->advanceWork($this->app->state, self::NOW);
        }

        self::assertDirectoryDoesNotExist($this->root . '/tłum');
    }

    /** `Esc` w oknie liczenia przerywa je bez dotknięcia dysku. */
    public function testEscapeDuringCountingTouchesNothing(): void
    {
        $this->makeBranchyDirectory(600);
        $this->select('tłum');
        $this->press(Key::F8);
        $this->press(Key::Escape);

        self::assertNull($this->app->state->overlays()->current());
        self::assertStringStartsWith('module.browser.delete.abandoned', (string) $this->message());
        self::assertDirectoryExists($this->root . '/tłum');
        self::assertCount(600, (array) glob($this->root . '/tłum/*'));
    }

    /** `Esc` w oknie postępu przerywa i mówi, ile z ilu zniknęło (D75, nr 13). */
    public function testEscapeStopsALongDeletionAndSaysHowMuchIsGone(): void
    {
        $this->makeCrowdedDirectory();
        $this->select('tłum');
        $this->press(Key::F8);
        $this->confirm();

        $this->press(Key::Escape);

        self::assertNull($this->app->state->overlays()->current());
        self::assertStringStartsWith('module.browser.delete.stopped', (string) $this->message());
        self::assertDirectoryExists($this->root . '/tłum', 'część drzewa zostaje — i to jest prawda o dysku');
        self::assertLessThan(
            self::CHUNK_OVERFLOW,
            count((array) glob($this->root . '/tłum/*')),
            'pierwszy kawałek usunął, co zdążył',
        );
    }

    /**
     * Oba panele patrzą na ten sam katalog, a operacja idzie w jednym z nich —
     * i **drugi widzi zmianę bez wchodzenia do katalogu na nowo**.
     */
    public function testTheOtherPaneSeesTheChangeInTheSameFrame(): void
    {
        $this->enableSplit();
        $this->press(Key::F7);
        $this->type('wspólny');
        $this->press(Key::Enter);

        $this->press(Key::Tab);

        self::assertContains('wspólny', $this->names(), 'drugi panel czyta ten sam katalog na nowo');
    }

    /**
     * Ustawienie „pytaj przed usunięciem” wyłączone: czynność dzieje się od razu,
     * bez pytania — i to jest jedyna pozycja, którą krok dokłada.
     */
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

        self::assertNull($this->app->state->overlays()->current(), 'pytania nie ma');
        self::assertFileDoesNotExist($this->root . '/notatka.txt');
    }

    /** Komenda `browser.mkdir` robi to samo, co `F7` — jedna czynność, dwa wejścia. */
    public function testTheCommandDoesTheSameThingAsTheKey(): void
    {
        $command = $this->app->commandRegistry->find('browser.mkdir');

        self::assertNotNull($command);

        $outcome = $command->execute(new \LightManager\Application\Command\CommandInput(['name' => 'z-komendy']));

        self::assertDirectoryExists($this->root . '/z-komendy');
        self::assertStringStartsWith('module.browser.mkdir.done', (string) $outcome->message?->text);
        self::assertContains('z-komendy', $this->names());
    }

    /** Komenda z niepoprawną nazwą zostawia okno komend otwarte wraz z wierszem. */
    public function testTheCommandKeepsItsWindowOpenOnARefusal(): void
    {
        $command = $this->app->commandRegistry->find('browser.rename');

        self::assertNotNull($command);

        $outcome = $command->execute(new \LightManager\Application\Command\CommandInput(['name' => '..']));

        self::assertStringStartsWith('module.browser.name.reserved', (string) $outcome->message?->text);
        self::assertSame(
            \LightManager\Application\Command\CommandTransition::Stay,
            $outcome->transition,
        );
    }

    private function fixture(): ScreenFixture
    {
        $directories = new FilesystemDirectoryRepository(EntryComparator::create());

        return new ScreenFixture(
            $directories->get(new DirectoryPath($this->root), false),
            $directories,
            $this->app->settingsStore ?? new \LightManager\Tests\Support\InMemorySettings(),
            operations: FileOperationsService::getInstance(),
        );
    }

    /**
     * Katalog o zadanej liczbie wpisów: tyle, by przekroczyć jeden kawałek
     * **usuwania** (256 wpisów).
     */
    private function makeCrowdedDirectory(int $count = self::CHUNK_OVERFLOW): void
    {
        mkdir($this->root . '/tłum');

        for ($index = 0; $index < $count; ++$index) {
            file_put_contents(sprintf('%s/tłum/plik-%03d', $this->root, $index), 'x');
        }

        $this->app = $this->fixture();
    }

    /**
     * Katalog, którego **liczenie** nie zmieści się w jednym kawałku.
     *
     * Wpisami są **podkatalogi**, a nie pliki, i to nie jest kaprys: liczenie
     * czyta jeden katalog w całości (`scandir()` i tak oddaje wszystko naraz),
     * więc tysiąc plików w jednym katalogu policzy się w jednym kawałku, choćby
     * budżet wynosił dziesięć. Kawałków przybywa dopiero wtedy, gdy przybywa
     * **katalogów do przejścia** — i dokładnie to jest tu potrzebne.
     */
    private function makeBranchyDirectory(int $count): void
    {
        mkdir($this->root . '/tłum');

        for ($index = 0; $index < $count; ++$index) {
            mkdir(sprintf('%s/tłum/gałąź-%03d', $this->root, $index));
        }

        $this->app = $this->fixture();
    }

    private function enableSplit(): void
    {
        $settings = $this->app->state->settings()->withModuleValue(BrowserSettings::ID, BrowserSettings::SPLIT, true);
        $this->app->settingsStore->save($settings);
        $this->app->state->applySettings($settings);

        // Podział uzgadnia się przy rysowaniu klatki, więc ekran musi raz narysować
        // się, zanim `Tab` będzie miał dokąd przenieść ognisko.
        $this->draw();
    }

    private function draw(): void
    {
        $screen = $this->app->browser;

        self::assertInstanceOf(ScreenInterface::class, $screen);
        $screen->draw(new \LightManager\Application\Ui\Rect(0, 0, 20, 80));
    }

    private function press(Key $key): void
    {
        $this->app->input->handle(KeyPress::special($key, "\e"), $this->app->state, self::NOW);
    }

    private function type(string $text): void
    {
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $character) {
            $this->app->input->handle(KeyPress::character($character), $this->app->state, self::NOW);
        }
    }

    /** Wyczyszczenie pola: `Backspace` tyle razy, ile zmieści się nazw. */
    private function clear(): void
    {
        for ($index = 0; $index < 40; ++$index) {
            $this->app->input->handle(KeyPress::special(Key::Backspace, "\x7f"), $this->app->state, self::NOW);
        }
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

    /** @return list<string> nazwy wpisów widoczne w panelu z ogniskiem */
    private function names(): array
    {
        $names = [];
        $directories = new FilesystemDirectoryRepository(EntryComparator::create());
        $current = new DirectoryPath($this->app->state->context()->path);

        foreach ($directories->get($current, false)->entries() as $entry) {
            $names[] = $entry->name;
        }

        return $names;
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
