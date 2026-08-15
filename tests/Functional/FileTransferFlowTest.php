<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Infrastructure\FileSystem\FileTransferService;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Infrastructure\EntryComparator;
use LightManager\Module\Browser\Infrastructure\FilesystemDirectoryRepository;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\ResetsSingletons;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * Kopiowanie i przenoszenie całą drogą użytkownika (krok 42).
 *
 * Przebieg idzie przez `InputHandler`, bo praca dzieje się w łańcuchu okien —
 * ścieżka celu, liczenie, postęp, kolizja — a `ScreenOutcome::opens()` bez rdzenia
 * przepada. Dysk jest **prawdziwy**: katalog tymczasowy i prawdziwe repozytorium.
 *
 * Trzy rzeczy są tu sednem: **praca posuwa się taktem pętli**, a nie klawiszami;
 * **przerwanie nie zostawia pliku wyglądającego na gotowy**; **przeniesienie
 * usuwa źródło dopiero po zapisaniu celu**.
 */
final class FileTransferFlowTest extends TestCase
{
    use ResetsSingletons;

    private const NOW = 100.0;

    /** Ile taktów wolno pracy zająć, zanim test uzna ją za niekończącą się. */
    private const TICKS = 40;

    private ScreenFixture $app;

    private string $root = '';

    protected function setUp(): void
    {
        $this->resetSingleton(FileTransferService::class);

        $this->root = sys_get_temp_dir() . '/lm-kopiowanie-' . bin2hex(random_bytes(6));

        mkdir($this->root);
        mkdir($this->root . '/dokad');
        file_put_contents($this->root . '/notatka.txt', 'treść');
        mkdir($this->root . '/zdjęcia');
        file_put_contents($this->root . '/zdjęcia/mapa.png', 'png');

        $this->app = $this->fixture();
    }

    protected function tearDown(): void
    {
        self::removeTree($this->root);
        $this->resetSingleton(FileTransferService::class);
    }

    /** `F5` otwiera okno ze ścieżką celu — wypełnioną katalogiem **drugiego panelu**. */
    public function testCopyKeyOpensThePathWindowFilledWithTheOtherPane(): void
    {
        $this->select('notatka.txt');
        $this->press(Key::F5);

        self::assertSame('prompt', $this->app->state->overlays()->current()?->id());
        self::assertStringContainsString($this->root, $this->overlayText(), 'w polu stoi katalog, nie pustka');
    }

    /** Krótka praca kończy się bez okna postępu: mignięcie oknem czyta się jak usterka. */
    public function testCopyingASmallFileNeedsNoProgressWindow(): void
    {
        $this->copyTo('notatka.txt', $this->root . '/dokad');

        self::assertFalse($this->app->state->overlays()->isOpen());
        self::assertSame('treść', file_get_contents($this->root . '/dokad/notatka.txt'));
        self::assertSame('treść', file_get_contents($this->root . '/notatka.txt'), 'źródło zostaje');
        self::assertStringStartsWith('module.browser.copy.done', (string) $this->message());
    }

    public function testCopyingADirectoryRepeatsItWhole(): void
    {
        $this->copyTo('zdjęcia', $this->root . '/dokad');

        self::assertSame('png', file_get_contents($this->root . '/dokad/zdjęcia/mapa.png'));
        self::assertFileExists($this->root . '/zdjęcia/mapa.png');
    }

    /**
     * Praca dłuższa od klatki: okno postępu staje **samo**, a posuwa się taktem
     * pętli — bez ani jednego klawisza.
     */
    public function testALongCopyOpensTheProgressWindowAndFinishesByItself(): void
    {
        $this->makeBigFile(9 * 1024 * 1024);
        $this->select('duży.bin');
        $this->toPath($this->root . '/dokad');

        self::assertSame('progress', $this->app->state->overlays()->current()?->id());
        self::assertLessThan(
            9 * 1024 * 1024,
            (int) filesize($this->root . '/dokad/duży.bin'),
            'jeden kawałek nie kopiuje całości',
        );

        $this->work();

        self::assertFalse($this->app->state->overlays()->isOpen());
        self::assertSame(9 * 1024 * 1024, filesize($this->root . '/dokad/duży.bin'));
        self::assertStringStartsWith('module.browser.copy.done', (string) $this->message());
    }

    /**
     * Licznik przy **jednym** pliku pokazuje sam rozmiar — bez „0/1”.
     *
     * Poprawka po obejrzeniu okna w prawdziwym terminalu: przy drzewie „5/20”
     * mówi dokładnie to, czego brakuje bajtom, ale przy jednym pliku stoi na
     * zerze przez cały czas pracy i czyta się jak usterka.
     */
    public function testTheCounterOfASingleFileShowsSizeAloneWithoutTheEntryTally(): void
    {
        $this->makeBigFile(9 * 1024 * 1024);
        $this->select('duży.bin');
        $this->toPath($this->root . '/dokad');

        // Napis czytamy bez odstępów, bo pasek **dzieli go na dwa prymitywy**
        // tam, gdzie przechodzi przez wypełnienie — to ta sama reguła, która
        // zmienia rolę liter w środku paska (krok 23).
        $counter = str_replace(' ', '', $this->overlayText());

        self::assertStringContainsString('module.browser.transfer.counter.size(', $counter);
        self::assertStringNotContainsString('0/1', $counter);
    }

    /** `Esc` w oknie postępu: cel **znika**, źródło zostaje nietknięte. */
    public function testEscapeDuringCopyingLeavesNoHalfWrittenFile(): void
    {
        $this->makeBigFile(9 * 1024 * 1024);
        $this->select('duży.bin');
        $this->toPath($this->root . '/dokad');

        $this->press(Key::Escape);

        self::assertNull($this->app->state->overlays()->current());
        self::assertFileDoesNotExist($this->root . '/dokad/duży.bin');
        self::assertSame(9 * 1024 * 1024, filesize($this->root . '/duży.bin'));
        self::assertStringStartsWith('module.browser.copy.stopped', (string) $this->message());
    }

    /**
     * Przeniesienie w obrębie jednego systemu plików: źródło znika, cel jest
     * w całości, a okno postępu nie miało czego pokazać.
     */
    public function testMovingWithinOneFilesystemIsImmediate(): void
    {
        $this->select('zdjęcia');
        $this->toPath($this->root . '/dokad', Key::F6);

        self::assertFalse($this->app->state->overlays()->isOpen());
        self::assertDirectoryDoesNotExist($this->root . '/zdjęcia');
        self::assertSame('png', file_get_contents($this->root . '/dokad/zdjęcia/mapa.png'));
        self::assertStringStartsWith('module.browser.move.done', (string) $this->message());
    }

    /** Kolizja staje oknem wyboru, a dysk czeka na odpowiedź. */
    public function testACollisionAsksBeforeItOverwritesAnything(): void
    {
        file_put_contents($this->root . '/dokad/notatka.txt', 'stara');
        $this->app = $this->fixture();

        $this->select('notatka.txt');
        $this->toPath($this->root . '/dokad');

        self::assertSame('choice', $this->app->state->overlays()->current()?->id());
        self::assertSame('stara', file_get_contents($this->root . '/dokad/notatka.txt'));
    }

    /** Pierwsza odpowiedź to „nadpisz” — `Enter` bez strzałek zastępuje plik. */
    public function testOverwriteAnswerReplacesTheFile(): void
    {
        file_put_contents($this->root . '/dokad/notatka.txt', 'stara');
        $this->app = $this->fixture();

        $this->select('notatka.txt');
        $this->toPath($this->root . '/dokad');
        $this->press(Key::Enter);
        $this->work();

        self::assertSame('treść', file_get_contents($this->root . '/dokad/notatka.txt'));
        self::assertStringStartsWith('module.browser.copy.done', (string) $this->message());
    }

    /** „Pomiń” zostawia obie strony nietknięte. */
    public function testSkipAnswerLeavesBothSides(): void
    {
        file_put_contents($this->root . '/dokad/notatka.txt', 'stara');
        $this->app = $this->fixture();

        $this->select('notatka.txt');
        $this->toPath($this->root . '/dokad');

        // Trzecia pozycja: nadpisz, nadpisz wszystkie, pomiń.
        $this->press(Key::ArrowDown);
        $this->press(Key::ArrowDown);
        $this->press(Key::Enter);
        $this->work();

        self::assertSame('stara', file_get_contents($this->root . '/dokad/notatka.txt'));
        self::assertSame('treść', file_get_contents($this->root . '/notatka.txt'));
    }

    /** „Zapisz pod inną nazwą” pyta jeszcze o nazwę — piąte okno łańcucha. */
    public function testRenameAnswerAsksForTheNameAndUsesIt(): void
    {
        file_put_contents($this->root . '/dokad/notatka.txt', 'stara');
        $this->app = $this->fixture();

        $this->select('notatka.txt');
        $this->toPath($this->root . '/dokad');

        for ($step = 0; $step < 4; ++$step) {
            $this->press(Key::ArrowDown);
        }

        $this->press(Key::Enter);

        self::assertSame('prompt', $this->app->state->overlays()->current()?->id());

        $this->clear();
        $this->type('kopia.txt');
        $this->press(Key::Enter);
        $this->work();

        self::assertSame('treść', file_get_contents($this->root . '/dokad/kopia.txt'));
        self::assertSame('stara', file_get_contents($this->root . '/dokad/notatka.txt'));
    }

    /** `Esc` w oknie wyboru znaczy odpowiedź ostatnią, czyli „przerwij”. */
    public function testEscapeInTheChoiceWindowStopsTheWork(): void
    {
        file_put_contents($this->root . '/dokad/notatka.txt', 'stara');
        $this->app = $this->fixture();

        $this->select('notatka.txt');
        $this->toPath($this->root . '/dokad');
        $this->press(Key::Escape);

        self::assertNull($this->app->state->overlays()->current());
        self::assertSame('stara', file_get_contents($this->root . '/dokad/notatka.txt'));
    }

    /** Kopiowanie do katalogu, w którym wpis już leży, odmawia z własnym zdaniem. */
    public function testCopyingIntoTheOwnDirectoryIsRefused(): void
    {
        $this->select('notatka.txt');
        $this->toPath($this->root);

        self::assertFalse($this->app->state->overlays()->isOpen());
        self::assertStringStartsWith('problem.transfer.sameDirectory', (string) $this->message());
    }

    /** Kopiowanie katalogu do jego własnego wnętrza jest niemożliwe i mówi dlaczego. */
    public function testCopyingADirectoryIntoItselfIsRefused(): void
    {
        $this->select('zdjęcia');
        $this->toPath($this->root . '/zdjęcia');

        self::assertStringStartsWith('problem.transfer.intoItself', (string) $this->message());
        self::assertSame(['.', '..', 'mapa.png'], scandir($this->root . '/zdjęcia') ?: []);
    }

    /** Ścieżka, która nie jest ścieżką: okno **zostaje otwarte** wraz z tym, co wpisano. */
    public function testARelativePathIsCountedFromTheCurrentDirectory(): void
    {
        $this->select('notatka.txt');
        $this->press(Key::F5);
        $this->clear();
        $this->type('dokad');
        $this->press(Key::Enter);
        $this->work();

        self::assertSame('treść', file_get_contents($this->root . '/dokad/notatka.txt'));
    }

    /** `browser.copy <ścieżka>` robi to samo, co klawisz — bez okna ze ścieżką. */
    public function testCopyCommandWithAPathNeedsNoPathWindow(): void
    {
        $this->select('notatka.txt');
        $this->command('browser.copy ' . $this->root . '/dokad');
        $this->work();

        self::assertSame('treść', file_get_contents($this->root . '/dokad/notatka.txt'));
    }

    /** `browser.move` bez ścieżki otwiera to samo okno, co `F6`. */
    public function testMoveCommandWithoutAPathOpensTheWindow(): void
    {
        $this->select('notatka.txt');
        $this->command('browser.move');

        self::assertSame('prompt', $this->app->state->overlays()->current()?->id());
    }

    private function copyTo(string $name, string $target): void
    {
        $this->select($name);
        $this->toPath($target);
        $this->work();
    }

    /** `F5` (albo `F6`), wpisanie ścieżki i `Enter` — cała droga do pierwszego kawałka. */
    private function toPath(string $target, Key $key = Key::F5): void
    {
        $this->press($key);
        $this->clear();
        $this->type($target);
        $this->press(Key::Enter);
    }

    /** Takty pętli, dopóki praca ma się czym posuwać. */
    private function work(): void
    {
        $ticks = 0;

        while ($this->app->state->overlays()->isOpen() && $ticks++ < self::TICKS) {
            $this->app->input->advanceWork($this->app->state, self::NOW);
        }
    }

    private function makeBigFile(int $bytes): void
    {
        file_put_contents($this->root . '/duży.bin', str_repeat('x', $bytes));
        $this->app = $this->fixture();
    }

    private function command(string $line): void
    {
        $this->press(Key::F12);
        $this->type($line);
        $this->press(Key::Enter);
    }

    private function fixture(): ScreenFixture
    {
        $directories = new FilesystemDirectoryRepository(EntryComparator::create());

        return new ScreenFixture(
            $directories->get(new DirectoryPath($this->root), false),
            $directories,
            new InMemorySettings(),
            transfers: FileTransferService::getInstance(),
        );
    }

    /** Treść okna — czytana z prymitywów, bo tak widzi ją użytkownik. */
    private function overlayText(): string
    {
        $overlay = $this->app->state->overlays()->current();

        self::assertNotNull($overlay);

        $text = '';

        foreach ($overlay->draw(new Rect(0, 0, 6, 80)) as $primitive) {
            if ($primitive instanceof TextRun) {
                $text .= ' ' . $primitive->text;
            }
        }

        return $text;
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

    private function clear(): void
    {
        for ($index = 0; $index < 120; ++$index) {
            $this->app->input->handle(KeyPress::special(Key::Backspace, "\x7f"), $this->app->state, self::NOW);
        }
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
