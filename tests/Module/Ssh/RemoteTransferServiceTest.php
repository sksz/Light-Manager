<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Ssh;

use LightManager\Application\Dto\TransferChoice;
use LightManager\Infrastructure\FileSystem\FileOperationsService;
use LightManager\Module\Ssh\Application\RemoteTransferItem;
use LightManager\Module\Ssh\Application\RemoteTransferStage;
use LightManager\Module\Ssh\Application\TransferDirection;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Infrastructure\RemoteTransferService;
use LightManager\Tests\Support\ResetsSingletons;
use LightManager\Tests\Support\StubBackgroundProcess;
use PHPUnit\Framework\TestCase;

/**
 * Praca przesyłu **bez ani jednego bajtu w sieci** (krok 50).
 *
 * Proces potomny zastępuje `StubBackgroundProcess`, a jego robotę — sam test:
 * pliku roboczego nikt tu nie ściąga, tylko go **tworzy**, dokładnie tak, jak
 * zrobiłby to `sftp`. Dzięki temu da się sprawdzić to, co naprawdę jest do
 * sprawdzenia: że treść ląduje pod nazwą roboczą, że zatwierdzenie jest zmianą
 * nazwy, że przerwanie nie zostawia połówki i że o kolizji pyta się **przed**
 * dotknięciem cudzego pliku.
 *
 * Port zapisu jest **prawdziwy** (`FileOperationsService`), bo to on decyduje
 * o losie pliku i podstawiony atrapą nie sprawdzałby niczego. Dysk jest za to
 * własny — katalog w `/tmp`, sprzątany w `tearDown()`.
 */
final class RemoteTransferServiceTest extends TestCase
{
    use ResetsSingletons;

    private string $root = '';

    private RemoteTransferService $transfers;

    private StubBackgroundProcess $processes;

    private HostProfile $host;

    protected function setUp(): void
    {
        $this->resetSingleton(RemoteTransferService::class);
        $this->resetSingleton(FileOperationsService::class);

        $this->root = sys_get_temp_dir() . '/lm-przesyl-' . bin2hex(random_bytes(6));
        mkdir($this->root);

        $this->host = new HostProfile('00000001', 'próba', '127.0.0.1', 2223, 'foo');
        $this->processes = new StubBackgroundProcess(pollsUntilDone: 2, output: '');
        $this->transfers = RemoteTransferService::getInstance();
        $this->transfers->useSeams($this->processes, FileOperationsService::getInstance());
    }

    protected function tearDown(): void
    {
        self::removeTree($this->root);
        $this->resetSingleton(RemoteTransferService::class);
        $this->resetSingleton(FileOperationsService::class);
    }

    /** Pobranie: treść pod nazwą roboczą, potem zmiana nazwy — i pasek zna całość od początku. */
    public function testDownloadWritesUnderAWorkingNameAndCommitsByRenaming(): void
    {
        $state = $this->beginDownload('plik.bin', 5);

        self::assertSame(RemoteTransferStage::Working, $state->stage);
        self::assertSame(5, $state->totalBytes, 'mianownik paska jest znany od pierwszej klatki');
        self::assertStringContainsString('.plik.bin.lm-part', $this->processes->startedCommands[0]);
        self::assertStringContainsString('get ', $this->processes->startedCommands[0]);

        $this->writeWorkingFile('plik.bin', 'abc');
        $state = $this->transfers->advance();

        self::assertSame(3, $state->doneBytes, 'postęp czyta rosnący plik roboczy');
        self::assertFileDoesNotExist($this->root . '/plik.bin', 'nazwa docelowa jeszcze nie istnieje');

        $this->writeWorkingFile('plik.bin', 'abcde');
        $state = $this->transfers->advance();

        self::assertSame(RemoteTransferStage::Done, $state->stage);
        self::assertSame(1, $state->doneEntries);
        self::assertFalse($state->wasStoppedEarly());
        self::assertSame('abcde', file_get_contents($this->root . '/plik.bin'));
        self::assertFileDoesNotExist($this->root . '/.plik.bin.lm-part');
    }

    /** Zajęta nazwa zatrzymuje pracę **przed** uruchomieniem potomka. */
    public function testATakenNameStopsTheWorkBeforeTouchingAnything(): void
    {
        file_put_contents($this->root . '/plik.bin', 'stare');

        $state = $this->beginDownload('plik.bin', 5);

        self::assertSame(RemoteTransferStage::Colliding, $state->stage);
        self::assertSame('plik.bin', $state->current);
        self::assertSame([], $this->processes->startedCommands, 'nic nie ruszyło, więc nic nie zdążyło nadpisać');
        self::assertSame('stare', file_get_contents($this->root . '/plik.bin'));
    }

    /** „Pomiń” kończy pracę bez przeniesienia i mówi o tym licznikiem. */
    public function testSkippingLeavesTheExistingFileAlone(): void
    {
        file_put_contents($this->root . '/plik.bin', 'stare');
        $this->beginDownload('plik.bin', 5);

        $state = $this->transfers->resolve(TransferChoice::Skip);

        self::assertSame(RemoteTransferStage::Done, $state->stage);
        self::assertSame(0, $state->doneEntries);
        self::assertSame(1, $state->skippedEntries);
        self::assertSame('stare', file_get_contents($this->root . '/plik.bin'));
    }

    /** „Nadpisz” zwalnia nazwę dopiero przy zatwierdzeniu — po udanym przesyle. */
    public function testOverwriteReplacesTheFileOnlyAfterTheContentArrived(): void
    {
        file_put_contents($this->root . '/plik.bin', 'stare');
        $this->beginDownload('plik.bin', 5);

        $this->transfers->resolve(TransferChoice::Overwrite);

        self::assertSame('stare', file_get_contents($this->root . '/plik.bin'), 'cudzy plik żyje, dopóki trwa praca');

        $this->writeWorkingFile('plik.bin', 'nowe');
        $this->transfers->advance();
        $state = $this->transfers->advance();

        self::assertSame(RemoteTransferStage::Done, $state->stage);
        self::assertSame('nowe', file_get_contents($this->root . '/plik.bin'));
    }

    /** „Zmień nazwę” przenosi plik pod nazwę wskazaną, a zajętą zostawia nietkniętą. */
    public function testRenamingPutsTheFileUnderTheNewName(): void
    {
        file_put_contents($this->root . '/plik.bin', 'stare');
        $this->beginDownload('plik.bin', 5);

        $this->transfers->resolve(TransferChoice::Rename, 'inny.bin');
        $this->writeWorkingFile('inny.bin', 'nowe');
        $this->transfers->advance();
        $state = $this->transfers->advance();

        self::assertSame(RemoteTransferStage::Done, $state->stage);
        self::assertSame('nowe', file_get_contents($this->root . '/inny.bin'));
        self::assertSame('stare', file_get_contents($this->root . '/plik.bin'));
    }

    /** Przerwanie w połowie: potomek ubity, a połówki na dysku nie ma. */
    public function testStoppingInTheMiddleLeavesNoHalfFile(): void
    {
        $this->beginDownload('plik.bin', 5);
        $this->writeWorkingFile('plik.bin', 'ab');
        $this->transfers->advance();

        $this->transfers->stop();

        self::assertSame(1, $this->processes->stopCount);
        self::assertFileDoesNotExist($this->root . '/.plik.bin.lm-part');
        self::assertFileDoesNotExist($this->root . '/plik.bin');
        self::assertSame(RemoteTransferStage::Idle, $this->transfers->state()->stage);
    }

    /**
     * Zerwana sesja: kod wyjścia niezerowy i **pusty strumień błędów**.
     *
     * Tak właśnie ginie `sftp`, gdy mistrz połączenia zniknie w środku pracy
     * (zmierzone: kod 141, `stderr` pusty), więc powód musi podać moduł.
     */
    public function testADroppedSessionIsRecognisedByTheExitCodeAlone(): void
    {
        $this->processes = new StubBackgroundProcess(pollsUntilDone: 1, output: '', exitCode: 141);
        $this->transfers->useSeams($this->processes, FileOperationsService::getInstance());

        $this->beginDownload('plik.bin', 5);
        $this->writeWorkingFile('plik.bin', 'ab');
        $state = $this->transfers->advance();

        self::assertSame(RemoteTransferStage::Failed, $state->stage);
        self::assertSame('module.ssh.transfer.dropped', $state->problemKey);
        self::assertFileDoesNotExist($this->root . '/.plik.bin.lm-part', 'połówka znika razem z niepowodzeniem');
    }

    /** Powód niepowodzenia bierze się ze strumienia błędów, gdy klient zdążył coś powiedzieć. */
    public function testTheReasonComesFromTheErrorStream(): void
    {
        $this->processes = new StubBackgroundProcess(
            pollsUntilDone: 1,
            output: '',
            exitCode: 1,
            errorOutput: 'open local "/x/.plik.bin.lm-part": Permission denied',
        );
        $this->transfers->useSeams($this->processes, FileOperationsService::getInstance());

        $this->beginDownload('plik.bin', 5);
        $state = $this->transfers->advance();

        self::assertSame('module.ssh.transfer.denied', $state->problemKey);
    }

    /**
     * Praca, której port już nie zna, kończy przesył — zwykły stan, nie awaria.
     *
     * **Do kroku 51 dochodziło się tu wyparciem**: port prowadził jedną pracę,
     * więc cudze zamówienie odbierało tę trwającą, a test zamawiał ją wprost.
     * Odkąd prac jest kilka, wyparcia nie ma i tamten test nie miałby czego
     * sprawdzić — ale samo `Idle` na uchwyt zostaje osiągalne (pracę zatrzymał
     * ten, kto trzyma jej uchwyt; jej stan wypadł z zapasu), a moduł ma je
     * rozumieć tak samo.
     */
    public function testWorkThePortNoLongerKnowsEndsTheTransfer(): void
    {
        $this->beginDownload('plik.bin', 5);
        $this->processes->forgetEverything();

        $state = $this->transfers->advance();

        self::assertSame(RemoteTransferStage::Failed, $state->stage);
        self::assertSame('module.ssh.transfer.interrupted', $state->problemKey);
    }

    /** Lista wielu źródeł idzie plik po pliku, a licznik mówi, ile ich przeszło. */
    public function testAListOfSourcesIsCarriedFileByFile(): void
    {
        $state = $this->transfers->begin(
            $this->host,
            [
                new RemoteTransferItem('/upload/a.bin', 'a.bin', 4),
                new RemoteTransferItem('/upload/b.bin', 'b.bin', 6),
            ],
            $this->root,
            TransferDirection::Download,
        );

        self::assertSame(10, $state->totalBytes);
        self::assertSame(2, $state->totalEntries);

        $this->writeWorkingFile('a.bin', 'aaaa');
        $this->transfers->advance();
        $state = $this->transfers->advance();

        self::assertSame(RemoteTransferStage::Working, $state->stage, 'praca przechodzi do drugiego pliku sama');
        self::assertSame('b.bin', $state->current);
        self::assertSame(1, $state->doneEntries);
        self::assertCount(2, $this->processes->startedCommands, 'jeden potomek na plik');

        $this->writeWorkingFile('b.bin', 'bbbbbb');
        $this->transfers->advance();
        $state = $this->transfers->advance();

        self::assertSame(RemoteTransferStage::Done, $state->stage);
        self::assertSame(2, $state->doneEntries);
        self::assertSame(10, $state->doneBytes);
    }

    /** Wysłanie: `put` pod nazwę roboczą i `rename -l`, bez usuwania celu. */
    public function testUploadCommitsWithANonClobberingRename(): void
    {
        $source = $this->root . '/lokalny.bin';
        file_put_contents($source, 'treść');

        $this->transfers->begin(
            $this->host,
            [new RemoteTransferItem($source, 'lokalny.bin', 5)],
            '/upload',
            TransferDirection::Upload,
        );

        $command = $this->processes->startedCommands[0];

        self::assertStringContainsString('put ', $command);
        self::assertStringContainsString('/upload/.lokalny.bin.lm-part', $command);
        self::assertStringContainsString('rename -l', $command);
        self::assertStringNotContainsString('-rm', $command, 'bez zgody nic nie znika po drugiej stronie');
    }

    /** Zajętość celu przy wysyłaniu bierze się z listy panelu, a nadpisanie usuwa cel jawnie. */
    public function testUploadAsksAboutNamesTakenInTheListingAndRemovesOnlyOnConsent(): void
    {
        $source = $this->root . '/lokalny.bin';
        file_put_contents($source, 'treść');

        $state = $this->transfers->begin(
            $this->host,
            [new RemoteTransferItem($source, 'lokalny.bin', 5)],
            '/upload',
            TransferDirection::Upload,
            ['inny.bin', 'lokalny.bin'],
        );

        self::assertSame(RemoteTransferStage::Colliding, $state->stage);

        $this->transfers->resolve(TransferChoice::Overwrite);

        self::assertStringContainsString('-rm', $this->processes->startedCommands[0]);
    }

    /** Przerwane wysyłanie sprząta **po drugiej stronie** — osobnym potomkiem. */
    public function testStoppingAnUploadRemovesTheRemoteHalf(): void
    {
        $source = $this->root . '/lokalny.bin';
        file_put_contents($source, 'treść');

        $this->transfers->begin(
            $this->host,
            [new RemoteTransferItem($source, 'lokalny.bin', 5)],
            '/upload',
            TransferDirection::Upload,
        );

        $this->transfers->stop();

        self::assertCount(2, $this->processes->startedCommands);
        self::assertStringContainsString('rm ', $this->processes->startedCommands[1]);
        self::assertStringContainsString('.lokalny.bin.lm-part', $this->processes->startedCommands[1]);
    }

    /** Limit czasu jest hojny, bo przesył legalnie trwa minuty (D89 nr 11). */
    public function testTheHardTimeoutIsGenerous(): void
    {
        $this->beginDownload('plik.bin', 5);

        self::assertGreaterThanOrEqual(3600, $this->processes->timeouts[0]);
    }

    private function beginDownload(string $name, int $size): \LightManager\Module\Ssh\Application\RemoteTransferState
    {
        return $this->transfers->begin(
            $this->host,
            [new RemoteTransferItem('/upload/' . $name, $name, $size)],
            $this->root,
            TransferDirection::Download,
        );
    }

    /** To, co w prawdziwym przebiegu robi `sftp`: dopisuje do pliku roboczego. */
    private function writeWorkingFile(string $name, string $content): void
    {
        file_put_contents($this->root . '/.' . $name . '.lm-part', $content);
    }

    private static function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $path . DIRECTORY_SEPARATOR . $entry;

            is_dir($child) ? self::removeTree($child) : unlink($child);
        }

        rmdir($path);
    }
}
