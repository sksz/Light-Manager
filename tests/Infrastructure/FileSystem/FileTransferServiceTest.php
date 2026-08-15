<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\FileSystem;

use LightManager\Application\Dto\TransferChoice;
use LightManager\Application\Dto\TransferStage;
use LightManager\Application\Dto\TransferState;
use LightManager\Infrastructure\FileSystem\FileTransferService;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\TestCase;

/**
 * Usługa **naprawdę kopiuje pliki**, więc test naprawdę dotyka katalogu
 * tymczasowego (krok 42) — atrapa systemu plików sprawdzałaby tu samą siebie,
 * a stawką są cudze dane.
 *
 * Praca jest kawałkowa, więc testy popychają ją `advance()` w pętli
 * z ogranicznikiem: pętla bez ogranicznika w teście, który sprawdza pracę
 * potrafiącą się nie skończyć, jest testem, który wiesza przebieg.
 */
final class FileTransferServiceTest extends TestCase
{
    use ResetsSingletons;

    /** Budżet kawałka: mały z rozmysłu, żeby test naprawdę przeszedł przez kilka taktów. */
    private const CHUNK = 8;

    /** Ogranicznik pętli — praca, która nie skończy się w tylu krokach, jest usterką. */
    private const STEPS = 500;

    private string $root = '';

    private FileTransferService $transfers;

    protected function setUp(): void
    {
        $this->resetSingleton(FileTransferService::class);
        $this->transfers = FileTransferService::getInstance();
        $this->root = sys_get_temp_dir() . '/lm-transfer-' . bin2hex(random_bytes(6));

        mkdir($this->root);
        mkdir($this->root . '/skad');
        mkdir($this->root . '/dokad');
    }

    protected function tearDown(): void
    {
        $this->transfers->stop();
        self::removeTree($this->root);
        $this->resetSingleton(FileTransferService::class);
    }

    public function testCopyingAFileCarriesItsContentPermissionsAndTime(): void
    {
        $source = $this->root . '/skad/plik.txt';
        file_put_contents($source, str_repeat('a', 100));
        chmod($source, 0640);
        touch($source, 1000000);

        $state = $this->transfer([$source], $this->root . '/dokad');

        self::assertSame(TransferStage::Done, $state->stage);
        self::assertSame(str_repeat('a', 100), file_get_contents($this->root . '/dokad/plik.txt'));
        self::assertSame('0640', substr(sprintf('%o', fileperms($this->root . '/dokad/plik.txt')), -4));
        self::assertSame(1000000, filemtime($this->root . '/dokad/plik.txt'));
        self::assertFileExists($source, 'kopiowanie zostawia źródło');
    }

    /** Praca liczy **przed** kopiowaniem, więc mianownik jest znany od pierwszego bajtu. */
    public function testCountingRunsBeforeTheFirstByte(): void
    {
        file_put_contents($this->root . '/skad/a.txt', str_repeat('x', 64));
        mkdir($this->root . '/skad/gałąź');
        file_put_contents($this->root . '/skad/gałąź/b.txt', str_repeat('y', 36));

        $state = $this->transfers->begin([$this->root . '/skad'], $this->root . '/dokad', false);

        self::assertSame(TransferStage::Scanning, $state->stage);
        self::assertNull($state->totalBytes, 'przy liczeniu całości jeszcze nie znamy');

        $state = $this->advanceUntil(static fn (TransferState $s): bool => $s->stage !== TransferStage::Scanning);

        self::assertSame(TransferStage::Working, $state->stage);
        self::assertSame(100, $state->totalBytes);
        self::assertSame(4, $state->totalEntries, 'dwa pliki, katalog źródłowy i jego gałąź');
        self::assertSame(0, $state->doneBytes);
    }

    public function testCopyingATreeRepeatsItWholeWithDirectoryPermissions(): void
    {
        mkdir($this->root . '/skad/gałąź');
        file_put_contents($this->root . '/skad/gałąź/b.txt', 'b');
        file_put_contents($this->root . '/skad/a.txt', 'a');
        chmod($this->root . '/skad/gałąź', 0750);

        $this->transfer([$this->root . '/skad'], $this->root . '/dokad');

        self::assertSame('a', file_get_contents($this->root . '/dokad/skad/a.txt'));
        self::assertSame('b', file_get_contents($this->root . '/dokad/skad/gałąź/b.txt'));
        self::assertSame('0750', substr(sprintf('%o', fileperms($this->root . '/dokad/skad/gałąź')), -4));
    }

    /** Dowiązanie kopiuje się jako dowiązanie — inaczej dowiązanie do `/` kopiowałoby system. */
    public function testSymbolicLinkIsCopiedAsALink(): void
    {
        file_put_contents($this->root . '/skad/cel.txt', 'treść');
        symlink($this->root . '/skad/cel.txt', $this->root . '/skad/wskaźnik');

        $this->transfer([$this->root . '/skad/wskaźnik'], $this->root . '/dokad');

        self::assertTrue(is_link($this->root . '/dokad/wskaźnik'));
        self::assertSame($this->root . '/skad/cel.txt', readlink($this->root . '/dokad/wskaźnik'));
    }

    /** Chodzenie po drzewie w dowiązania **nie wchodzi**, więc pętli nie ma czego wykrywać. */
    public function testWalkingDoesNotFollowLinksIntoTheirTargets(): void
    {
        mkdir($this->root . '/skad/gałąź');
        file_put_contents($this->root . '/skad/gałąź/w-środku.txt', 'x');
        symlink($this->root . '/skad/gałąź', $this->root . '/skad/skrót');

        $this->transfer([$this->root . '/skad'], $this->root . '/dokad');

        // Kopia dowiązania nadal wskazuje **oryginalną** gałąź, więc plik przez nią
        // widać — dowodem, że nikt tam nie wszedł, jest brak drugiej kopii pliku
        // w miejscu, w którym stanęłaby, gdyby chodzenie po drzewie podążyło za
        // dowiązaniem.
        self::assertTrue(is_link($this->root . '/dokad/skad/skrót'));
        self::assertSame($this->root . '/skad/gałąź', readlink($this->root . '/dokad/skad/skrót'));
        self::assertSame(
            ['.', '..', 'gałąź', 'skrót'],
            scandir($this->root . '/dokad/skad') ?: [],
            'w kopii stoi dowiązanie, a nie drugi katalog',
        );
    }

    /**
     * Przeniesienie w obrębie jednego systemu plików kończy się **w klatce,
     * w której się zaczęło**: nie liczy i nie kopiuje ani bajtu.
     */
    public function testMovingOnTheSameFilesystemNeitherCountsNorCopies(): void
    {
        mkdir($this->root . '/skad/drzewo');
        file_put_contents($this->root . '/skad/drzewo/plik.txt', 'treść');

        $state = $this->transfers->begin([$this->root . '/skad/drzewo'], $this->root . '/dokad', true);

        self::assertSame(TransferStage::Working, $state->stage, 'liczenia nie ma po co zaczynać');

        $state = $this->transfers->advance(self::CHUNK);

        self::assertSame(TransferStage::Done, $state->stage);
        self::assertSame(0, $state->doneBytes, 'zmiana nazwy nie przenosi bajtów');
        self::assertDirectoryDoesNotExist($this->root . '/skad/drzewo');
        self::assertSame('treść', file_get_contents($this->root . '/dokad/drzewo/plik.txt'));
    }

    /** Przerwanie usuwa plik zapisany w połowie — plik wyglądający na gotowy jest gorszy niż jego brak. */
    public function testStoppingRemovesTheHalfWrittenFile(): void
    {
        file_put_contents($this->root . '/skad/duży.txt', str_repeat('z', 400));

        $this->transfers->begin([$this->root . '/skad/duży.txt'], $this->root . '/dokad', false);
        $this->transfers->advance(self::CHUNK);

        self::assertFileExists($this->root . '/dokad/duży.txt', 'plik jest w połowie zapisany');

        $this->transfers->stop();

        self::assertFileDoesNotExist($this->root . '/dokad/duży.txt');
        self::assertSame(400, filesize($this->root . '/skad/duży.txt'), 'źródło nietknięte');
    }

    /**
     * Przeniesienie między systemami plików usuwa źródło **dopiero po zapisaniu
     * celu w całości** — a przerwane w połowie zostawia je nietknięte.
     *
     * Systemów plików test nie ma dwóch, więc sprawdza tę samą regułę na drodze
     * kopiowania: dopóki plik nie jest zapisany, źródło stoi.
     */
    public function testMovingRemovesTheSourceOnlyAfterTheTargetIsWhole(): void
    {
        $source = $this->root . '/skad/duży.txt';
        file_put_contents($source, str_repeat('z', 400));

        $this->transfers->begin([$source], $this->root . '/dokad', false);
        $this->transfers->advance(self::CHUNK);

        self::assertFileExists($source);

        $this->transfers->stop();

        self::assertFileExists($source, 'przerwanie w połowie zostawia źródło');
    }

    public function testCollisionStopsTheWorkAndAsks(): void
    {
        file_put_contents($this->root . '/skad/plik.txt', 'nowa');
        file_put_contents($this->root . '/dokad/plik.txt', 'stara');

        $state = $this->advanceFrom([$this->root . '/skad/plik.txt'], $this->root . '/dokad');

        self::assertSame(TransferStage::Colliding, $state->stage);
        self::assertSame('plik.txt', $state->current);
        self::assertSame('stara', file_get_contents($this->root . '/dokad/plik.txt'), 'pytanie niczego nie tknęło');
    }

    public function testOverwriteAnswerReplacesTheFile(): void
    {
        file_put_contents($this->root . '/skad/plik.txt', 'nowa');
        file_put_contents($this->root . '/dokad/plik.txt', 'stara');

        $this->advanceFrom([$this->root . '/skad/plik.txt'], $this->root . '/dokad');
        $this->transfers->resolve(TransferChoice::Overwrite);
        $this->finish();

        self::assertSame('nowa', file_get_contents($this->root . '/dokad/plik.txt'));
    }

    public function testSkipAnswerLeavesBothSides(): void
    {
        file_put_contents($this->root . '/skad/plik.txt', 'nowa');
        file_put_contents($this->root . '/dokad/plik.txt', 'stara');

        $this->advanceFrom([$this->root . '/skad/plik.txt'], $this->root . '/dokad');
        $state = $this->transfers->resolve(TransferChoice::Skip);
        $state = $this->finish();

        self::assertSame(TransferStage::Done, $state->stage);
        self::assertSame('stara', file_get_contents($this->root . '/dokad/plik.txt'));
        self::assertSame('nowa', file_get_contents($this->root . '/skad/plik.txt'));
    }

    /**
     * „Zmień nazwę” przepisuje cel — i **wszystko, co leżało w środku** zmienianego
     * katalogu.
     *
     * Kolizją katalogu jest **plik** o tej samej nazwie: katalog o tej samej
     * nazwie byłby scaleniem, a nie kolizją. Cele wpisów policzono przy liczeniu,
     * więc bez przepisania całej gałęzi zawartość poszłaby pod starą ścieżkę,
     * której nikt już nie tworzy.
     */
    public function testRenameAnswerRetargetsTheWholeBranch(): void
    {
        mkdir($this->root . '/skad/drzewo');
        file_put_contents($this->root . '/skad/drzewo/plik.txt', 'treść');
        file_put_contents($this->root . '/dokad/drzewo', 'zajęte przez plik');

        $state = $this->advanceFrom([$this->root . '/skad/drzewo'], $this->root . '/dokad');

        self::assertSame(TransferStage::Colliding, $state->stage);
        self::assertSame('drzewo', $state->current);

        $this->transfers->resolve(TransferChoice::Rename, 'inne');
        $this->finish();

        self::assertSame('treść', file_get_contents($this->root . '/dokad/inne/plik.txt'));
        self::assertSame('zajęte przez plik', file_get_contents($this->root . '/dokad/drzewo'));
    }

    public function testAbortAnswerEndsTheWorkWhereItStood(): void
    {
        file_put_contents($this->root . '/skad/plik.txt', 'nowa');
        file_put_contents($this->root . '/dokad/plik.txt', 'stara');

        $this->advanceFrom([$this->root . '/skad/plik.txt'], $this->root . '/dokad');
        $state = $this->transfers->resolve(TransferChoice::Abort);

        self::assertSame(TransferStage::Done, $state->stage);
        self::assertTrue($state->wasStoppedEarly());
        self::assertSame('stara', file_get_contents($this->root . '/dokad/plik.txt'));
    }

    /** Katalog o tej samej nazwie **nie jest kolizją**, tylko scaleniem: nic nie ginie. */
    public function testAnExistingDirectoryIsMergedWithoutAsking(): void
    {
        mkdir($this->root . '/skad/drzewo');
        file_put_contents($this->root . '/skad/drzewo/nowy.txt', 'nowy');
        mkdir($this->root . '/dokad/drzewo');
        file_put_contents($this->root . '/dokad/drzewo/stary.txt', 'stary');

        $state = $this->transfer([$this->root . '/skad/drzewo'], $this->root . '/dokad');

        self::assertSame(TransferStage::Done, $state->stage);
        self::assertSame('nowy', file_get_contents($this->root . '/dokad/drzewo/nowy.txt'));
        self::assertSame('stary', file_get_contents($this->root . '/dokad/drzewo/stary.txt'));
    }

    public function testCopyingIntoItselfIsRefusedWithItsOwnSentence(): void
    {
        mkdir($this->root . '/skad/drzewo');

        $state = $this->transfers->begin([$this->root . '/skad'], $this->root . '/skad/drzewo', false);

        self::assertSame(TransferStage::Failed, $state->stage);
        self::assertSame('problem.transfer.intoItself', $state->problemKey);
    }

    public function testCopyingIntoTheOwnDirectoryIsRefused(): void
    {
        file_put_contents($this->root . '/skad/plik.txt', 'x');

        $state = $this->transfers->begin([$this->root . '/skad/plik.txt'], $this->root . '/skad', false);

        self::assertSame(TransferStage::Failed, $state->stage);
        self::assertSame('problem.transfer.sameDirectory', $state->problemKey);
    }

    public function testMissingTargetIsRefusedBeforeAnythingHappens(): void
    {
        file_put_contents($this->root . '/skad/plik.txt', 'x');

        $state = $this->transfers->begin([$this->root . '/skad/plik.txt'], $this->root . '/nie-ma', false);

        self::assertSame(TransferStage::Failed, $state->stage);
        self::assertSame('problem.transfer.noTarget', $state->problemKey);
    }

    public function testMissingSourceIsRefused(): void
    {
        $state = $this->transfers->begin([$this->root . '/skad/nie-ma.txt'], $this->root . '/dokad', false);

        self::assertSame(TransferStage::Failed, $state->stage);
        self::assertSame('problem.fileops.missing', $state->problemKey);
    }

    /** Nowa praca przerywa poprzednią wraz z jej niedokończonym plikiem (reguła 11d). */
    public function testBeginningAgainDropsTheWorkThatStood(): void
    {
        file_put_contents($this->root . '/skad/duży.txt', str_repeat('z', 400));
        file_put_contents($this->root . '/skad/mały.txt', 'x');

        $this->transfers->begin([$this->root . '/skad/duży.txt'], $this->root . '/dokad', false);
        $this->transfers->advance(self::CHUNK);
        $this->transfer([$this->root . '/skad/mały.txt'], $this->root . '/dokad');

        self::assertFileDoesNotExist($this->root . '/dokad/duży.txt');
        self::assertSame('x', file_get_contents($this->root . '/dokad/mały.txt'));
    }

    /** @param list<string> $sources */
    private function transfer(array $sources, string $target, bool $move = false): TransferState
    {
        $this->transfers->begin($sources, $target, $move);

        return $this->finish();
    }

    /**
     * Praca do pierwszego przystanku: kolizji, końca albo niepowodzenia.
     *
     * @param list<string> $sources
     */
    private function advanceFrom(array $sources, string $target, bool $move = false): TransferState
    {
        $this->transfers->begin($sources, $target, $move);

        return $this->finish();
    }

    private function finish(): TransferState
    {
        return $this->advanceUntil(static fn (TransferState $state): bool => !$state->isRunning());
    }

    /** @param callable(TransferState): bool $until */
    private function advanceUntil(callable $until): TransferState
    {
        $state = $this->transfers->state();

        for ($step = 0; $step < self::STEPS && !$until($state); ++$step) {
            $state = $this->transfers->advance(self::CHUNK);
        }

        self::assertTrue($until($state), 'praca nie skończyła się w ' . self::STEPS . ' krokach');

        return $state;
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
