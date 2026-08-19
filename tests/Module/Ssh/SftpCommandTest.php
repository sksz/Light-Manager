<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Ssh;

use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Domain\ValueObject\RemotePath;
use LightManager\Module\Ssh\Infrastructure\SftpCommand;
use LightManager\Module\Ssh\Infrastructure\SftpFailureReader;
use PHPUnit\Framework\TestCase;

/**
 * Budowa polecenia odczytu i rozczytanie powodów niepowodzenia (krok 49).
 *
 * Obie klasy są **czyste** — nie uruchamiają niczego i niczego nie czytają —
 * więc sprawdzają się bez ani jednego bajtu w sieci. To jest to samo kryterium
 * podziału, które w kroku 48 zebrało `KnownHostsReader`, `FingerprintParser`
 * i `SshFailureReader` w jednym pliku.
 *
 * Cytowań pilnuje się tu **dwóch naraz** i to jest główny powód istnienia tego
 * testu: powłoka cytuje po swojemu (`escapeshellarg`), a `sftp` czyta swój wsad
 * własnym parserem. Pomylenie ich kończy się listowaniem nie tego katalogu,
 * o który prosił użytkownik.
 */
final class SftpCommandTest extends TestCase
{
    private const SOCKET = '/home/anna/.light-manager/ssh-abc123.sock';

    public function testListingAsksForTheDirectoryThroughTheMasterSocket(): void
    {
        $command = SftpCommand::listing(self::host(), RemotePath::of('/var/log'), false, self::SOCKET);

        self::assertStringContainsString('sftp -b -', $command, 'wsad idzie potokiem, bo port nie umie podać wejścia');
        self::assertStringContainsString(self::SOCKET, $command, 'wchodzimy przez gniazdo stojącego mistrza');
        self::assertStringContainsString('BatchMode=yes', $command, 'bez tego zerwana sesja pytałaby o hasło');
        self::assertStringContainsString('-P 2222', $command);
        self::assertStringContainsString("'anna@example.com'", $command);
    }

    /**
     * **Najważniejsze zdanie tego pliku**: strumieni nie wolno scalać.
     *
     * `2>&1` w tym poleceniu gubiło dwie trzecie dużego katalogu i robiło to po
     * cichu — `ssh` przy `ControlPath` przekazuje deskryptory mistrzowi
     * połączenia, ten ustawia im tryb nieblokujący (bo obsługuje wiele sesji
     * w jednej pętli), a tryb jest własnością **opisu pliku**, więc scalony
     * strumień błędów przenosił go na wyjście `sftp`. Zapis zwracał wtedy
     * `EAGAIN`, OpenSSH porzucał porcję wypisu i kończył się **kodem zero**:
     * zmierzone 130 KB ze 419 KB.
     *
     * Powód niepowodzenia idzie osobnym strumieniem, który port niesie od kroku
     * 49 własnym polem — patrz `BackgroundState::$errorOutput`.
     */
    public function testStreamsAreNeverMerged(): void
    {
        $command = SftpCommand::listing(self::host(), RemotePath::of('/var/log'), false, self::SOCKET);

        self::assertStringNotContainsString('2>&1', $command);
    }

    /** Wpisy ukryte to **inne polecenie**, a nie odfiltrowanie tego, co przyszło. */
    public function testHiddenEntriesChangeTheFlags(): void
    {
        $visible = SftpCommand::listing(self::host(), RemotePath::of('/var'), false, self::SOCKET);
        $hidden = SftpCommand::listing(self::host(), RemotePath::of('/var'), true, self::SOCKET);

        self::assertStringContainsString("'ls -lf ", $visible);
        self::assertStringContainsString("'ls -laf ", $hidden);
    }

    /** Brak ścieżki znaczy „katalog startowy": `pwd` i listowanie **w jednym wywołaniu**. */
    public function testTheHomeDirectoryIsAskedForAndListedInOneCall(): void
    {
        $command = SftpCommand::listing(self::host(), null, false, self::SOCKET);

        self::assertStringContainsString("'pwd'", $command);
        self::assertStringContainsString("'ls -lf'", $command);
        self::assertSame(1, substr_count($command, 'sftp -b -'), 'jedno wywołanie, nie dwa');
    }

    /**
     * Cytowanie dla parsera `sftp`: cudzysłów obejmuje całość, a odwrotny
     * ukośnik chroni cudzysłów i sam siebie.
     */
    public function testQuotingFollowsTheSftpParserNotTheShell(): void
    {
        self::assertSame('"/kat ze spacja"', SftpCommand::quote('/kat ze spacja'));
        self::assertSame('"/a\\"b"', SftpCommand::quote('/a"b'));
        self::assertSame('"/a\\\\b"', SftpCommand::quote('/a\\b'));
    }

    /** Ścieżka z odstępem przechodzi przez **oba** cytowania nietknięta. */
    public function testAPathWithASpaceSurvivesBothQuotings(): void
    {
        $command = SftpCommand::listing(self::host(), RemotePath::of('/kat ze spacja'), false, self::SOCKET);

        self::assertStringContainsString('ls -lf "/kat ze spacja"', $command);
    }

    /** Pobranie: jedno wywołanie, treść pod nazwą roboczą, bez scalania strumieni (krok 50). */
    public function testDownloadWritesUnderAWorkingName(): void
    {
        $command = SftpCommand::download(
            self::host(),
            RemotePath::of('/upload/plik.bin'),
            '/home/anna/.plik.bin.lm-part',
            self::SOCKET,
        );

        self::assertStringContainsString('get "/upload/plik.bin" "/home/anna/.plik.bin.lm-part"', $command);
        self::assertSame(1, substr_count($command, 'sftp -b -'), 'jeden potomek na plik');
        self::assertStringNotContainsString('2>&1', $command);
    }

    /**
     * **Najważniejsze zdanie o wysyłaniu**: zatwierdzenie idzie `rename -l`.
     *
     * Zwykłe `rename` idzie rozszerzeniem `posix-rename@openssh.com`, które
     * **nadpisuje cicho** — sprawdzone na żywym serwerze: kod zero na zajętej
     * nazwie. `-l` wymusza `SSH_FXP_RENAME`, czyli odmowę. Nadpisanie ma być
     * skutkiem odpowiedzi użytkownika, nie właściwością protokołu.
     */
    public function testUploadCommitsWithANonClobberingRenameAndRemovesNothingByItself(): void
    {
        $command = SftpCommand::upload(
            self::host(),
            '/home/anna/plik.bin',
            RemotePath::of('/upload/.plik.bin.lm-part'),
            RemotePath::of('/upload/plik.bin'),
            false,
            self::SOCKET,
        );

        self::assertStringContainsString('put "/home/anna/plik.bin" "/upload/.plik.bin.lm-part"', $command);
        self::assertStringContainsString('rename -l "/upload/.plik.bin.lm-part" "/upload/plik.bin"', $command);
        self::assertStringNotContainsString('rm ', $command);
        self::assertStringNotContainsString('2>&1', $command);
    }

    /** Nadpisanie zwalnia nazwę **jawnie**, a myślnik pozwala pracy iść dalej, gdy celu już nie ma. */
    public function testOverwritingFreesTheNameExplicitly(): void
    {
        $command = SftpCommand::upload(
            self::host(),
            '/home/anna/plik.bin',
            RemotePath::of('/upload/.plik.bin.lm-part'),
            RemotePath::of('/upload/plik.bin'),
            true,
            self::SOCKET,
        );

        self::assertStringContainsString('-rm "/upload/plik.bin"', $command);
        self::assertTrue(
            strpos($command, '-rm') < strpos($command, 'rename -l'),
            'najpierw zwolnienie nazwy, potem zatwierdzenie',
        );
    }

    /** Sprzątanie zdalnej połówki to osobne, najkrótsze możliwe wywołanie. */
    public function testRemovingTheRemoteHalfIsASingleCommand(): void
    {
        $command = SftpCommand::remove(self::host(), RemotePath::of('/upload/.plik.bin.lm-part'), self::SOCKET);

        self::assertStringContainsString('rm "/upload/.plik.bin.lm-part"', $command);
        self::assertStringNotContainsString('2>&1', $command);
    }

    /** Nazwa z odstępem i cudzysłowem przechodzi przez oba cytowania także w przesyle. */
    public function testTransferQuotingSurvivesAwkwardNames(): void
    {
        $command = SftpCommand::download(
            self::host(),
            RemotePath::of('/upload/plik z "cudzysłowem".bin'),
            '/home/anna/cel.bin',
            self::SOCKET,
        );

        self::assertStringContainsString('get "/upload/plik z \\"cudzysłowem\\".bin"', $command);
    }

    /** Powody przesyłu są **osobne** od powodów odczytu, bo te same słowa znaczą tam co innego. */
    public function testTransferProblemsHaveTheirOwnSentences(): void
    {
        self::assertSame(
            'module.ssh.transfer.nameTaken',
            SftpFailureReader::readTransfer('remote rename "/upload/.a.lm-part" to "/upload/a": Failure'),
        );
        self::assertSame(
            'module.ssh.transfer.denied',
            SftpFailureReader::readTransfer('dest open "/x.bin": Permission denied'),
        );
        self::assertSame(
            'module.ssh.transfer.missingTarget',
            SftpFailureReader::readTransfer('open local "/nie-ma/x.bin": No such file or directory'),
        );
        self::assertSame(
            'module.ssh.transfer.missingSource',
            SftpFailureReader::readTransfer('File "/upload/nie-ma.bin" not found.'),
        );
        self::assertSame(
            'module.ssh.transfer.dropped',
            SftpFailureReader::readTransfer('Connection closed'),
        );
        self::assertSame(
            'module.ssh.transfer.failed',
            SftpFailureReader::readTransfer('coś zupełnie nowego'),
        );
    }

    /**
     * Powody odczytu rozstrzygają się **przed** powodami połączenia, bo
     * „odmowa dostępu" pada w obu, a `ls` wyłącznie w tym pierwszym.
     */
    public function testListingProblemsAreToldApartFromConnectionProblems(): void
    {
        self::assertSame(
            'module.ssh.listing.missing',
            SftpFailureReader::read('Can\'t ls: "/nie/ma" not found'),
        );
        self::assertSame(
            'module.ssh.listing.denied',
            SftpFailureReader::read('Can\'t ls: "/root" Permission denied'),
        );
        // Napis z prawdziwego przebiegu: katalog o prawach 000 narzeka właśnie
        // tak, a nie „Can't ls". Bez tego wzorca wpadał pod ogólne „Permission
        // denied" i mówił „sesja zerwana" o żywej sesji.
        self::assertSame(
            'module.ssh.listing.denied',
            SftpFailureReader::read('remote readdir("/upload/zamkniety/"): Permission denied'),
        );
        self::assertSame(
            'module.ssh.listing.dropped',
            SftpFailureReader::read('anna@example.com: Permission denied (publickey).'),
        );
        self::assertSame('module.ssh.listing.dropped', SftpFailureReader::read('Connection closed'));
    }

    /** Nieznane narzekanie kończy się zdaniem ogólnym — aplikacja nigdy nie milczy. */
    public function testAnUnknownComplaintStillGetsASentence(): void
    {
        self::assertSame('module.ssh.listing.failed', SftpFailureReader::read('coś zupełnie nowego'));
    }

    private static function host(): HostProfile
    {
        return new HostProfile('biuro', 'example.com', 2222, 'anna');
    }
}
