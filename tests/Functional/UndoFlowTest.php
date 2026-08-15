<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Infrastructure\FileSystem\FileOperationsService;
use LightManager\Infrastructure\FileSystem\FileTransferService;
use LightManager\Infrastructure\FileSystem\XdgTrashService;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Infrastructure\EntryComparator;
use LightManager\Module\Browser\Infrastructure\FilesystemDirectoryRepository;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\ResetsSingletons;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * Cofanie całą drogą użytkownika (krok 44, D81 nr 6–8).
 *
 * Dysk jest **prawdziwy** — katalog tymczasowy, prawdziwe usługi zapisu
 * i prawdziwy kosz podstawiony pozycją ustawień — bo cofnięcie sprawdza
 * wykonalność na dysku i atrapa sprawdzałaby samą siebie.
 *
 * Sedno w czterech zdaniach: **`Alt`+`u` cofa najnowszą operację odwracalną**;
 * **widok `F3` pozwala cofnąć dowolną pozycję, z pominięciem nieodwracalnych**;
 * **cofnięcie nieudane mówi dlaczego i nie zdejmuje zapisu**; **kursor staje na
 * wpisie przywróconym**, bo to on jest odpowiedzią na pytanie „czy się udało”.
 */
final class UndoFlowTest extends TestCase
{
    use ResetsSingletons;

    private const NOW = 100.0;

    private const TICKS = 40;

    private ScreenFixture $app;

    private string $root = '';

    private string $bin = '';

    protected function setUp(): void
    {
        $this->resetSingleton(FileOperationsService::class);
        $this->resetSingleton(FileTransferService::class);
        $this->resetSingleton(XdgTrashService::class);

        $this->root = sys_get_temp_dir() . '/lm-cofanie-' . bin2hex(random_bytes(6));
        $this->bin = $this->root . '/.kosz';

        mkdir($this->root);
        file_put_contents($this->root . '/notatka.txt', 'treść');
        mkdir($this->root . '/dokad');

        $this->app = $this->fixture();
        $this->useTrashDirectory($this->bin);
    }

    protected function tearDown(): void
    {
        self::removeTree($this->root);
        $this->resetSingleton(FileOperationsService::class);
        $this->resetSingleton(FileTransferService::class);
        $this->resetSingleton(XdgTrashService::class);
    }

    /** Zmiana nazwy cofa się zmianą nazwy — a kursor staje na przywróconej. */
    public function testUndoRestoresARenamedEntry(): void
    {
        $this->select('notatka.txt');
        $this->press(Key::F4);
        $this->clear();
        $this->type('umowa.txt');
        $this->press(Key::Enter);

        self::assertFileExists($this->root . '/umowa.txt');

        $this->undo();

        self::assertFileExists($this->root . '/notatka.txt');
        self::assertFileDoesNotExist($this->root . '/umowa.txt');
        self::assertSame('notatka.txt', $this->selected(), 'kursor staje na przywróconym');
        self::assertStringStartsWith('module.browser.undo.done.rename', (string) $this->message());
    }

    /** Nowy katalog cofa się usunięciem — dopóki pozostał pusty (D81, nr 10). */
    public function testUndoRemovesACreatedDirectoryWhileItIsEmpty(): void
    {
        $this->press(Key::F7);
        $this->type('projekty');
        $this->press(Key::Enter);

        self::assertDirectoryExists($this->root . '/projekty');

        $this->undo();

        self::assertDirectoryDoesNotExist($this->root . '/projekty');
        self::assertStringStartsWith('module.browser.undo.done.mkdir', (string) $this->message());
    }

    /** Katalog, do którego coś przybyło, odmawia — i **nie zdejmuje zapisu**. */
    public function testUndoRefusesWhenTheCreatedDirectoryGainedContent(): void
    {
        $this->press(Key::F7);
        $this->type('projekty');
        $this->press(Key::Enter);

        file_put_contents($this->root . '/projekty/plik.txt', 'przybysz');

        $this->undo();

        self::assertDirectoryExists($this->root . '/projekty', 'cofnięcie nie usuwa cudzej treści');
        self::assertStringStartsWith('problem.fileops.notEmpty', (string) $this->message());

        // Zapis został: po sprzątnięciu przybysza to samo cofnięcie się udaje.
        unlink($this->root . '/projekty/plik.txt');
        $this->undo();

        self::assertDirectoryDoesNotExist($this->root . '/projekty');
    }

    /** Druga połowa miary powodzenia kroku: jedno naciśnięcie przywraca wpis z kosza. */
    public function testUndoBringsATrashedEntryBack(): void
    {
        $this->select('notatka.txt');
        $this->press(Key::F8);
        $this->confirm();

        self::assertFileExists($this->bin . '/files/notatka.txt');

        $this->undo();

        self::assertFileExists($this->root . '/notatka.txt');
        self::assertSame('treść', file_get_contents($this->root . '/notatka.txt'));
        self::assertFileDoesNotExist($this->bin . '/files/notatka.txt');
        self::assertFileDoesNotExist($this->bin . '/info/notatka.txt.trashinfo');
        self::assertSame('notatka.txt', $this->selected());
        self::assertStringStartsWith('module.browser.undo.done.trashOne', (string) $this->message());
    }

    /** Usunięcie trwałe nie udaje, że da się je cofnąć: klawisz mówi, że nie ma czego. */
    public function testPermanentDeletionLeavesNothingToUndo(): void
    {
        $this->select('notatka.txt');
        $this->pressShifted(Key::F8);
        $this->confirm();

        self::assertFileDoesNotExist($this->root . '/notatka.txt');

        $this->undo();

        self::assertStringStartsWith('module.browser.undo.empty', (string) $this->message());
        self::assertFileDoesNotExist($this->root . '/notatka.txt', 'nic nie wróciło, bo nie miało skąd');
    }

    /** Przeniesienie cofa się przeniesieniem z powrotem — tą samą pracą kawałkową. */
    public function testUndoMovesTheEntryBackToItsPreviousPlace(): void
    {
        $this->select('notatka.txt');
        $this->press(Key::F6);
        $this->clear();
        $this->type($this->root . '/dokad');
        $this->press(Key::Enter);
        $this->work();

        self::assertFileExists($this->root . '/dokad/notatka.txt');
        self::assertFileDoesNotExist($this->root . '/notatka.txt');

        $this->undo();
        $this->work();

        self::assertFileExists($this->root . '/notatka.txt', 'wpis wrócił na poprzednie miejsce');
        self::assertFileDoesNotExist($this->root . '/dokad/notatka.txt');
        self::assertStringStartsWith('module.browser.undo.done.move', (string) $this->message());
    }

    /** Kopiowanie jest nieodwracalne z rozmysłu — po nim klawisz cofania nie ma nic do wzięcia. */
    public function testCopyingIsRecordedButNotUndoable(): void
    {
        $this->select('notatka.txt');
        $this->press(Key::F5);
        $this->clear();
        $this->type($this->root . '/dokad');
        $this->press(Key::Enter);
        $this->work();

        self::assertFileExists($this->root . '/dokad/notatka.txt');

        $this->undo();

        self::assertStringStartsWith('module.browser.undo.empty', (string) $this->message());
        self::assertFileExists($this->root . '/dokad/notatka.txt', 'kopia zostaje — jej usunięcie nie jest powrotem');

        // W widoku kopia stoi jako historia — wyszarzona, ale widoczna (D81, nr 8).
        $this->press(Key::F3);

        self::assertSame('undo', $this->app->state->overlays()->current()?->id());
        self::assertStringContainsString('module.browser.undo.entry.copy', $this->overlayText());
    }

    /** Widok `F3`: cofnąć wolno dowolną pozycję, nie tylko wierzchołek (D81, nr 6). */
    public function testTheViewUndoesAPickedEntryFromTheMiddleOfTheStack(): void
    {
        // Starsza operacja: zmiana nazwy; nowsza: nowy katalog.
        $this->select('notatka.txt');
        $this->press(Key::F4);
        $this->clear();
        $this->type('umowa.txt');
        $this->press(Key::Enter);

        $this->press(Key::F7);
        $this->type('projekty');
        $this->press(Key::Enter);

        $this->press(Key::F3);

        self::assertSame('undo', $this->app->state->overlays()->current()?->id());

        // Kursor stoi na najnowszej; strzałka w dół schodzi na zmianę nazwy.
        $this->press(Key::ArrowDown);
        $this->press(Key::Enter);

        self::assertFileExists($this->root . '/notatka.txt', 'cofnięta starsza pozycja');
        self::assertDirectoryExists($this->root . '/projekty', 'nowsza nietknięta');

        // Nowsza pozycja została w stosie — drugie cofnięcie bierze ją klawiszem.
        $this->undo();

        self::assertDirectoryDoesNotExist($this->root . '/projekty');
    }

    /** Pusty stos to zdanie w pasku, nie puste okno. */
    public function testAnEmptyStackIsASentenceNotAWindow(): void
    {
        $this->press(Key::F3);

        self::assertNull($this->app->state->overlays()->current());
        self::assertStringStartsWith('module.browser.undo.empty', (string) $this->message());
    }

    /** `Alt`+`u` — cofnięcie najnowszej operacji odwracalnej. */
    private function undo(): void
    {
        $this->app->input->handle(KeyPress::alt('u'), $this->app->state, self::NOW);
    }

    /** Takty pętli, dopóki praca ma się czym posuwać. */
    private function work(): void
    {
        $ticks = 0;

        while ($this->app->state->overlays()->isOpen() && $ticks++ < self::TICKS) {
            $this->app->input->advanceWork($this->app->state, self::NOW);
        }
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
            transfers: FileTransferService::getInstance(),
            trash: XdgTrashService::getInstance(),
        );
    }

    private function overlayText(): string
    {
        $overlay = $this->app->state->overlays()->current();

        self::assertNotNull($overlay);

        $text = '';

        foreach ($overlay->draw(new Rect(0, 0, 12, 100)) as $primitive) {
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

    private function pressShifted(Key $key): void
    {
        $this->app->input->handle(KeyPress::shifted($key, "\e"), $this->app->state, self::NOW);
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
