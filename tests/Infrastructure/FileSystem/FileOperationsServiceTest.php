<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\FileSystem;

use LightManager\Application\Dto\RemovalStage;
use LightManager\Domain\Exception\FileOperationException;
use LightManager\Infrastructure\FileSystem\FileOperationsService;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\TestCase;

/**
 * Usługa **naprawdę pisze po dysku**, więc test naprawdę dotyka katalogu
 * tymczasowego — atrapa systemu plików sprawdziłaby tu wyłącznie samą siebie
 * (krok 41).
 *
 * Sprzątanie idzie przez `tearDown()` i przez własne usuwanie rekurencyjne
 * usługi tylko wtedy, gdy testowany przypadek go nie zużył: katalog zostawiony
 * po nieudanym teście byłby śmieciem w `/tmp`, a nie dowodem.
 */
final class FileOperationsServiceTest extends TestCase
{
    use ResetsSingletons;

    private string $root = '';

    private FileOperationsService $operations;

    protected function setUp(): void
    {
        $this->resetSingleton(FileOperationsService::class);
        $this->operations = FileOperationsService::getInstance();
        $this->root = sys_get_temp_dir() . '/lm-fileops-' . bin2hex(random_bytes(6));

        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        self::removeTree($this->root);
        $this->resetSingleton(FileOperationsService::class);
    }

    public function testRenameChangesTheNameInPlace(): void
    {
        file_put_contents($this->root . '/przed.txt', 'treść');

        $this->operations->rename($this->root . '/przed.txt', 'po.txt');

        self::assertFileDoesNotExist($this->root . '/przed.txt');
        self::assertSame('treść', file_get_contents($this->root . '/po.txt'));
    }

    public function testRenameRefusesWhenTheNameIsTaken(): void
    {
        file_put_contents($this->root . '/a.txt', 'a');
        file_put_contents($this->root . '/b.txt', 'b');

        $problem = self::catchProblem(fn (): null => $this->operations->rename($this->root . '/a.txt', 'b.txt'));

        self::assertSame('problem.fileops.taken', $problem->problemKey());
        self::assertSame(['name' => 'b.txt'], $problem->problemParameters());
        self::assertSame('a', file_get_contents($this->root . '/a.txt'), 'odmowa nie dotyka dysku');
    }

    public function testRenameRefusesWhenTheEntryIsGone(): void
    {
        $problem = self::catchProblem(fn (): null => $this->operations->rename($this->root . '/nie-ma.txt', 'x'));

        self::assertSame('problem.fileops.missing', $problem->problemKey());
    }

    public function testCreateDirectoryMakesOneLevel(): void
    {
        $this->operations->createDirectory($this->root . '/nowy');

        self::assertDirectoryExists($this->root . '/nowy');
    }

    /** Jedna czynność to jeden poziom — katalogów pośrednich usługa nie dorabia. */
    public function testCreateDirectoryRefusesWhenTheParentIsMissing(): void
    {
        $problem = self::catchProblem(
            fn (): null => $this->operations->createDirectory($this->root . '/nie-ma/glebiej'),
        );

        self::assertSame('problem.fileops.missing', $problem->problemKey());
        self::assertDirectoryDoesNotExist($this->root . '/nie-ma');
    }

    public function testDeleteRemovesAFileAndAnEmptyDirectory(): void
    {
        file_put_contents($this->root . '/plik', 'x');
        mkdir($this->root . '/pusty');

        $this->operations->delete($this->root . '/plik');
        $this->operations->delete($this->root . '/pusty');

        self::assertFileDoesNotExist($this->root . '/plik');
        self::assertDirectoryDoesNotExist($this->root . '/pusty');
    }

    /**
     * Dowiązanie znika samo, a jego cel zostaje — dlatego `is_link()` sprawdza się
     * **przed** `is_dir()`.
     */
    public function testDeleteRemovesTheSymlinkAndNotItsTarget(): void
    {
        mkdir($this->root . '/cel');
        file_put_contents($this->root . '/cel/plik', 'x');
        symlink($this->root . '/cel', $this->root . '/skrót');

        $this->operations->delete($this->root . '/skrót');

        self::assertFalse(is_link($this->root . '/skrót'));
        self::assertFileExists($this->root . '/cel/plik');
    }

    public function testDeleteRefusesANonEmptyDirectoryWithItsOwnSentence(): void
    {
        mkdir($this->root . '/pelny');
        file_put_contents($this->root . '/pelny/plik', 'x');

        $problem = self::catchProblem(fn (): null => $this->operations->delete($this->root . '/pelny'));

        self::assertSame('problem.fileops.notEmpty', $problem->problemKey());
        self::assertFileExists($this->root . '/pelny/plik');
    }

    /**
     * Sedno pracy kawałkowej: liczenie zna **całość dopiero na końcu**, a usuwanie
     * posuwa się o tyle wpisów, ile pozwoli budżet — i dopiero wtedy melduje
     * „gotowe”.
     */
    public function testRemovalCountsFirstAndDeletesInChunks(): void
    {
        $this->makeTree();

        $state = $this->operations->beginRemoval($this->root . '/drzewo');

        self::assertSame(RemovalStage::Scanning, $state->stage);
        self::assertNull($state->total, 'przy liczeniu całości jeszcze nie znamy');

        // Kawałek po jednym wpisie: liczenie musi przejść przez kilka taktów.
        $guard = 0;

        while ($state->stage === RemovalStage::Scanning && $guard++ < 100) {
            $state = $this->operations->advanceRemoval(1);
        }

        // Katalog `drzewo`, w nim plik i katalog `glebiej`, a w nim dwa pliki.
        self::assertSame(RemovalStage::Ready, $state->stage);
        self::assertSame(5, $state->total, 'liczba obejmuje sam katalog wskazany');
        self::assertFileExists($this->root . '/drzewo/plik', 'liczenie nie dotyka dysku');

        $state = $this->operations->confirmRemoval();
        self::assertSame(RemovalStage::Deleting, $state->stage);

        $guard = 0;

        while ($state->isRunning() && $guard++ < 100) {
            $state = $this->operations->advanceRemoval(1);
        }

        self::assertSame(RemovalStage::Done, $state->stage);
        self::assertSame(5, $state->done);
        self::assertDirectoryDoesNotExist($this->root . '/drzewo');
    }

    /** Kolejność jest cała treścią usuwania drzewa: katalog daje się usunąć wyłącznie pusty. */
    public function testRemovalDeletesChildrenBeforeTheirParents(): void
    {
        $this->makeTree();

        $this->operations->beginRemoval($this->root . '/drzewo');

        while ($this->operations->removalState()->stage === RemovalStage::Scanning) {
            $this->operations->advanceRemoval(64);
        }

        $this->operations->confirmRemoval();
        $state = $this->operations->advanceRemoval(64);

        self::assertSame(RemovalStage::Done, $state->stage, 'jeden hojny kawałek wystarcza na całe drzewo');
        self::assertDirectoryDoesNotExist($this->root . '/drzewo');
    }

    /**
     * Przerwana praca zostawia to, co już usunęła — i to jest zapisany wyjątek od
     * zasady „w całości albo wcale” (D75, rozstrzygnięcie 13).
     */
    public function testStoppingHalfWayLeavesWhatWasAlreadyDeleted(): void
    {
        $this->makeTree();

        $this->operations->beginRemoval($this->root . '/drzewo');

        while ($this->operations->removalState()->stage === RemovalStage::Scanning) {
            $this->operations->advanceRemoval(64);
        }

        $this->operations->confirmRemoval();
        $state = $this->operations->advanceRemoval(1);

        self::assertSame(RemovalStage::Deleting, $state->stage);
        self::assertSame(1, $state->done);

        $this->operations->stopRemoval();

        self::assertSame(RemovalStage::Idle, $this->operations->removalState()->stage);
        self::assertDirectoryExists($this->root . '/drzewo', 'katalog zostaje — usunięto z niego jeden wpis');
    }

    /** Nowa praca porzuca poprzednią: port prowadzi jedną (reguła 11d). */
    public function testBeginningAnotherRemovalForgetsThePreviousOne(): void
    {
        $this->makeTree();
        mkdir($this->root . '/drugie');

        $this->operations->beginRemoval($this->root . '/drzewo');
        $state = $this->operations->beginRemoval($this->root . '/drugie');

        while ($state->stage === RemovalStage::Scanning) {
            $state = $this->operations->advanceRemoval(64);
        }

        self::assertSame(1, $state->total, 'pusty katalog to jeden wpis do usunięcia');

        $this->operations->confirmRemoval();
        $this->operations->advanceRemoval(64);

        self::assertDirectoryDoesNotExist($this->root . '/drugie');
        self::assertDirectoryExists($this->root . '/drzewo', 'porzucona praca niczego nie usuwa');
    }

    /** Dowiązanie jest **wpisem**, a nie gałęzią: praca nie wchodzi w jego cel. */
    public function testRemovalTreatsASymlinkAsASingleEntry(): void
    {
        mkdir($this->root . '/cel');
        file_put_contents($this->root . '/cel/plik', 'x');
        symlink($this->root . '/cel', $this->root . '/skrót');

        $state = $this->operations->beginRemoval($this->root . '/skrót');

        self::assertSame(RemovalStage::Ready, $state->stage, 'nie ma czego liczyć');
        self::assertSame(1, $state->total);

        $this->operations->confirmRemoval();
        $this->operations->advanceRemoval(8);

        self::assertFalse(is_link($this->root . '/skrót'));
        self::assertFileExists($this->root . '/cel/plik');
    }

    public function testAdvancingWithoutWorkChangesNothing(): void
    {
        self::assertSame(RemovalStage::Idle, $this->operations->advanceRemoval(10)->stage);
        self::assertSame(RemovalStage::Idle, $this->operations->confirmRemoval()->stage);
    }

    /** `drzewo/plik`, `drzewo/glebiej/a`, `drzewo/glebiej/b` — pięć wpisów z katalogami. */
    private function makeTree(): void
    {
        mkdir($this->root . '/drzewo/glebiej', 0o700, true);
        file_put_contents($this->root . '/drzewo/plik', 'x');
        file_put_contents($this->root . '/drzewo/glebiej/a', 'a');
        file_put_contents($this->root . '/drzewo/glebiej/b', 'b');
    }

    /** @param callable(): void $action */
    private static function catchProblem(callable $action): FileOperationException
    {
        try {
            $action();
        } catch (FileOperationException $problem) {
            return $problem;
        }

        self::fail('czynność miała odmówić, a się udała');
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
