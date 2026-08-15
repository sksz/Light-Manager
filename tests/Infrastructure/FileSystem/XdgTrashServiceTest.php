<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\FileSystem;

use LightManager\Domain\Exception\FileOperationException;
use LightManager\Infrastructure\FileSystem\XdgTrashService;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\TestCase;

/**
 * Kosz wedle freedesktop.org — na katalogu tymczasowym, nigdy na koszu osoby
 * uruchamiającej testy (krok 44). Test, który zaśmieca prawdziwy kosz, jest
 * błędem, nie niedogodnością — dlatego każda ścieżka tu podstawiona.
 */
final class XdgTrashServiceTest extends TestCase
{
    use ResetsSingletons;

    private XdgTrashService $trash;

    private string $root = '';

    private string $bin = '';

    protected function setUp(): void
    {
        $this->resetSingleton(XdgTrashService::class);
        $this->trash = XdgTrashService::getInstance();

        $this->root = sys_get_temp_dir() . '/lm-kosz-' . bin2hex(random_bytes(6));
        $this->bin = $this->root . '/Trash';

        mkdir($this->root);
        file_put_contents($this->root . '/raport.pdf', 'treść');
    }

    protected function tearDown(): void
    {
        self::removeTree($this->root);
        $this->resetSingleton(XdgTrashService::class);
    }

    public function testDefaultDirectoryHonorsXdgDataHome(): void
    {
        $previous = getenv('XDG_DATA_HOME');
        putenv('XDG_DATA_HOME=/dane');

        try {
            self::assertSame('/dane/Trash', $this->trash->defaultDirectory());
        } finally {
            putenv($previous === false ? 'XDG_DATA_HOME' : 'XDG_DATA_HOME=' . $previous);
        }
    }

    public function testDefaultDirectoryFallsBackToHome(): void
    {
        $previousData = getenv('XDG_DATA_HOME');
        $previousHome = getenv('HOME');
        putenv('XDG_DATA_HOME');
        putenv('HOME=/home/ktoś');

        try {
            self::assertSame('/home/ktoś/.local/share/Trash', $this->trash->defaultDirectory());
        } finally {
            putenv($previousData === false ? 'XDG_DATA_HOME' : 'XDG_DATA_HOME=' . $previousData);
            putenv($previousHome === false ? 'HOME' : 'HOME=' . $previousHome);
        }
    }

    /** Wpis ląduje w `files/`, a obok staje plik informacyjny ze ścieżką powrotną. */
    public function testMoveToTrashCreatesTheLayoutAndTheInfoFile(): void
    {
        $name = $this->trash->moveToTrash($this->root . '/raport.pdf', $this->bin);

        self::assertSame('raport.pdf', $name);
        self::assertFileDoesNotExist($this->root . '/raport.pdf');
        self::assertFileExists($this->bin . '/files/raport.pdf');
        self::assertSame('treść', file_get_contents($this->bin . '/files/raport.pdf'));

        $info = (string) file_get_contents($this->bin . '/info/raport.pdf.trashinfo');

        self::assertStringContainsString("[Trash Info]\n", $info);
        self::assertStringContainsString('Path=' . $this->root . '/raport.pdf', $info);
        self::assertMatchesRegularExpression('/DeletionDate=\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $info);
    }

    /** Kolizja: sufiks liczbowy **przed rozszerzeniem**, jak w koszu środowiska (D81, nr 11). */
    public function testACollisionGetsANumericSuffixBeforeTheExtension(): void
    {
        $this->trash->moveToTrash($this->root . '/raport.pdf', $this->bin);
        file_put_contents($this->root . '/raport.pdf', 'nowszy');

        $name = $this->trash->moveToTrash($this->root . '/raport.pdf', $this->bin);

        self::assertSame('raport.1.pdf', $name);
        self::assertSame('treść', file_get_contents($this->bin . '/files/raport.pdf'), 'pierwszy wpis nietknięty');
        self::assertSame('nowszy', file_get_contents($this->bin . '/files/raport.1.pdf'));
        self::assertFileExists($this->bin . '/info/raport.1.pdf.trashinfo');
    }

    /** Katalog jedzie do kosza w całości — jedną zmianą nazwy, bez liczenia. */
    public function testADirectoryMovesWhole(): void
    {
        mkdir($this->root . '/zdjęcia');
        file_put_contents($this->root . '/zdjęcia/mapa.png', 'png');

        $name = $this->trash->moveToTrash($this->root . '/zdjęcia', $this->bin);

        self::assertSame('zdjęcia', $name);
        self::assertDirectoryDoesNotExist($this->root . '/zdjęcia');
        self::assertSame('png', file_get_contents($this->bin . '/files/zdjęcia/mapa.png'));
    }

    /** Ścieżka w pliku informacyjnym koduje się jak adres URL — i odkodowuje przy przywracaniu. */
    public function testThePathSurvivesUrlEncoding(): void
    {
        mkdir($this->root . '/moje pliki');
        file_put_contents($this->root . '/moje pliki/notatka ważna.txt', 'x');

        $name = $this->trash->moveToTrash($this->root . '/moje pliki/notatka ważna.txt', $this->bin);
        $info = (string) file_get_contents($this->bin . '/info/' . $name . '.trashinfo');

        self::assertStringContainsString('moje%20pliki', $info, 'spacja zakodowana');

        $restored = $this->trash->restore($name, $this->bin);

        self::assertSame($this->root . '/moje pliki/notatka ważna.txt', $restored);
        self::assertFileExists($restored);
    }

    public function testRestorePutsTheEntryBackAndRemovesTheInfoFile(): void
    {
        $name = $this->trash->moveToTrash($this->root . '/raport.pdf', $this->bin);

        $restored = $this->trash->restore($name, $this->bin);

        self::assertSame($this->root . '/raport.pdf', $restored);
        self::assertSame('treść', file_get_contents($restored));
        self::assertFileDoesNotExist($this->bin . '/files/raport.pdf');
        self::assertFileDoesNotExist($this->bin . '/info/raport.pdf.trashinfo');
    }

    /** Zajęte miejsce docelowe jest odmową, nie nadpisaniem. */
    public function testRestoreRefusesWhenTheTargetIsTaken(): void
    {
        $name = $this->trash->moveToTrash($this->root . '/raport.pdf', $this->bin);
        file_put_contents($this->root . '/raport.pdf', 'nowy plik pod starą nazwą');

        $this->expectException(FileOperationException::class);

        try {
            $this->trash->restore($name, $this->bin);
        } finally {
            self::assertFileExists($this->bin . '/files/raport.pdf', 'wpis zostaje w koszu');
            self::assertSame('nowy plik pod starą nazwą', file_get_contents($this->root . '/raport.pdf'));
        }
    }

    public function testRestoreRefusesWhenTheEntryIsGone(): void
    {
        $this->expectException(FileOperationException::class);

        $this->trash->restore('nie-ma-takiego', $this->bin);
    }

    /** Rezerwacja pisze sam plik informacyjny; wpis zostaje na miejscu. */
    public function testReserveWritesTheInfoFileWithoutTouchingTheEntry(): void
    {
        $name = $this->trash->reserve($this->root . '/raport.pdf', $this->bin);

        self::assertSame('raport.pdf', $name);
        self::assertFileExists($this->root . '/raport.pdf', 'rezerwacja nie przenosi');
        self::assertFileDoesNotExist($this->bin . '/files/raport.pdf');
        self::assertFileExists($this->bin . '/info/raport.pdf.trashinfo');
    }

    /** Druga rezerwacja tej samej nazwy bierze sufiks — plik informacyjny jest zamkiem. */
    public function testASecondReservationTakesTheSuffix(): void
    {
        $this->trash->reserve($this->root . '/raport.pdf', $this->bin);

        self::assertSame('raport.1.pdf', $this->trash->reserve($this->root . '/raport.pdf', $this->bin));
    }

    /**
     * Sprzątanie rezerwacji: osierocona znika, dojechana zostaje — i to ją
     * `releaseUnused()` oddaje, bo praca przerwana pyta, co naprawdę stoi w koszu.
     */
    public function testReleaseUnusedKeepsOnlyArrivedEntries(): void
    {
        $orphan = $this->trash->reserve($this->root . '/raport.pdf', $this->bin);
        $arrived = $this->trash->moveToTrash($this->root . '/raport.pdf', $this->bin);

        $kept = $this->trash->releaseUnused([$orphan, $arrived], $this->bin);

        self::assertSame([$arrived], $kept);
        self::assertFileDoesNotExist($this->bin . '/info/' . $orphan . '.trashinfo', 'sierota sprzątnięta');
        self::assertFileExists($this->bin . '/info/' . $arrived . '.trashinfo', 'dojechany nietknięty');
    }

    /** Ten sam system plików: kosz przyjmuje; katalog kosza nie musi jeszcze istnieć. */
    public function testAcceptsJudgesByDeviceEvenBeforeTheTrashExists(): void
    {
        self::assertTrue($this->trash->accepts($this->root . '/raport.pdf', $this->bin));
        self::assertFalse($this->trash->accepts($this->root . '/nie-ma-takiego', $this->bin));
    }

    /** Wpis bez rozszerzenia i wpis ukryty: sufiks dokleja się na końcu. */
    public function testSuffixLandsAtTheEndForNamesWithoutAnExtension(): void
    {
        file_put_contents($this->root . '/notatki', 'a');
        file_put_contents($this->root . '/.ukryty', 'b');

        $this->trash->moveToTrash($this->root . '/notatki', $this->bin);
        file_put_contents($this->root . '/notatki', 'c');

        self::assertSame('notatki.1', $this->trash->moveToTrash($this->root . '/notatki', $this->bin));

        $this->trash->moveToTrash($this->root . '/.ukryty', $this->bin);
        file_put_contents($this->root . '/.ukryty', 'd');

        self::assertSame('.ukryty.1', $this->trash->moveToTrash($this->root . '/.ukryty', $this->bin));
    }

    private static function removeTree(string $path): void
    {
        if (!is_dir($path)) {
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
