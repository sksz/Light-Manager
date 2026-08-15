<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Ssh;

use LightManager\Module\Ssh\Infrastructure\FingerprintParser;
use LightManager\Module\Ssh\Infrastructure\KnownHostsReader;
use LightManager\Module\Ssh\Infrastructure\SshFailureReader;
use PHPUnit\Framework\TestCase;

/**
 * Trzy klasy czyste kroku 48: znane hosty, odciski i powody niepowodzeń.
 *
 * Stoją w jednym pliku, bo są jedną rzeczą: **wszystkim, co w tym module daje
 * się sprawdzić bez ani jednego bajtu w sieci**. To nie jest wygoda testu, tylko
 * kryterium ich podziału — reszta modułu rozmawia z procesem potomnym, więc
 * wszystko, co dało się z niej wyjąć jako czysty rachunek, zostało wyjęte tutaj.
 *
 * Wpisy zahaszowane w tych testach **pochodzą z prawdziwego `ssh-keygen -H`**,
 * a nie z ręcznego rachunku — inaczej test potwierdzałby wyłącznie, że kod
 * zgadza się sam ze sobą.
 */
final class KnownHostsReaderTest extends TestCase
{
    /**
     * Wpis zahaszowany dla `przyklad.example.com` na porcie 22 — wygenerowany
     * poleceniem `ssh-keygen -H` na maszynie projektu.
     */
    private const HASHED = '|1|3NA33SUOL6OVJV8JNMtif3mpp5M=|8KbEovBPfk7LHSvknfIPEzEsudY= '
        . 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIJHhSJp8V+VZC0iXKUXQ+d5v1lBnQ8qKZ5nJv7uCq0Xr';

    /** Ten sam plik, wpis dla `[inny.example.com]:2222`. */
    private const HASHED_WITH_PORT = '|1|CHc+TLX7zKcFATYbqOPPJsHtnps=|m854RO1T9MQR7kZFgMlGrBY/IT4= '
        . 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIJHhSJp8V+VZC0iXKUXQ+d5v1lBnQ8qKZ5nJv7uCq0Xr';

    /**
     * **Rdzeń całego rachunku**: kluczem HMAC jest sól, a nazwa jest treścią —
     * odwrotnie, niż podpowiada kolejność argumentów `hash_hmac()`.
     */
    public function testHashedEntryIsMatched(): void
    {
        self::assertTrue(KnownHostsReader::matches(self::HASHED, 'przyklad.example.com', 22));
    }

    public function testHashedEntryDoesNotMatchAnotherHost(): void
    {
        self::assertFalse(KnownHostsReader::matches(self::HASHED, 'obcy.example.com', 22));
    }

    /**
     * Port niedomyślny zapisuje się jako `[host]:port` — i **tylko** tak.
     * Ten sam host na porcie 22 to dla `ssh` inny wpis, i słusznie.
     */
    public function testPortIsPartOfTheHashedName(): void
    {
        self::assertTrue(KnownHostsReader::matches(self::HASHED_WITH_PORT, 'inny.example.com', 2222));
        self::assertFalse(KnownHostsReader::matches(self::HASHED_WITH_PORT, 'inny.example.com', 22));
    }

    public function testPlainEntryIsMatchedRegardlessOfLetterCase(): void
    {
        $content = "Przyklad.Example.COM ssh-rsa AAAAB3Nza\n";

        self::assertTrue(KnownHostsReader::matches($content, 'przyklad.example.com', 22));
    }

    public function testPlainEntryCanListSeveralNames(): void
    {
        $content = "alfa.example.com,beta.example.com,10.0.0.1 ssh-rsa AAAAB3Nza\n";

        self::assertTrue(KnownHostsReader::matches($content, 'beta.example.com', 22));
        self::assertTrue(KnownHostsReader::matches($content, '10.0.0.1', 22));
    }

    /**
     * Wzorca **nie rozwijamy**, i to jest rozstrzygnięcie, nie brak.
     *
     * Pomyłka w tę stronę kończy się zbędnym pytaniem o odcisk; pomyłka
     * w drugą — połączeniem bez pytania. Pierwsza kosztuje jedno naciśnięcie
     * klawisza, druga jest luką w zaufaniu.
     */
    public function testWildcardEntryIsNotTreatedAsKnown(): void
    {
        self::assertFalse(KnownHostsReader::matches("*.example.com ssh-rsa AAAAB3Nza\n", 'a.example.com', 22));
    }

    public function testCommentsAndMarkersAreSkipped(): void
    {
        $content = "# komentarz\n@revoked przyklad.example.com ssh-rsa AAAAB3Nza\n\n";

        self::assertFalse(KnownHostsReader::matches($content, 'przyklad.example.com', 22));
    }

    public function testMissingFileMeansTheHostIsUnknown(): void
    {
        $reader = new KnownHostsReader('/nie/ma/takiego/pliku/known_hosts');

        self::assertFalse($reader->knows('przyklad.example.com', 22));
    }

    /**
     * **Ścieżka pliku jest wystawiona, bo musi ją poznać `ssh`** (poprawka z próby
     * z żywym serwerem, krok 48).
     *
     * Klient rozwija `~` w `UserKnownHostsFile` z wpisu w `passwd`, a moduł
     * z `HOME` — na zwykłej maszynie to ten sam plik, ale z przypadku. W próbie
     * te drogi się rozeszły: `ssh` zapisał wpis, a czytający go nie zobaczył.
     * Odtąd usługa narzuca klientowi **dokładnie tę** ścieżkę.
     */
    public function testTheReaderTellsWhichFileItReads(): void
    {
        self::assertSame('/wskazany/known_hosts', (new KnownHostsReader('/wskazany/known_hosts'))->location());
    }

    public function testWithoutAnExplicitPathTheReaderFollowsTheHomeDirectory(): void
    {
        $previous = getenv('HOME');
        putenv('HOME=/dom/anny');

        try {
            self::assertSame('/dom/anny/.ssh/known_hosts', (new KnownHostsReader())->location());
        } finally {
            putenv($previous === false ? 'HOME' : 'HOME=' . $previous);
        }
    }

    /**
     * Wiersz `ssh-keygen -lf` — postać sprawdzona na maszynie projektu.
     *
     * Zysk, którego plan nie zakładał: to jest **SHA256**, czyli dokładnie ten
     * napis, który pokazuje `ssh`. Odrzucony wariant na `ext-ssh2` umiał
     * wyłącznie SHA1 i krok godził się go pokazywać.
     */
    public function testFingerprintLineIsRead(): void
    {
        $output = '256 SHA256:EZQpKi4iUrJWT2nvMqRy5H6Xxy5R1PX65l6pJhzgxjo host.example.com (ED25519)';

        $fingerprints = FingerprintParser::parse($output);

        self::assertCount(1, $fingerprints);
        self::assertSame('ED25519', $fingerprints[0]->type);
        self::assertSame('SHA256:EZQpKi4iUrJWT2nvMqRy5H6Xxy5R1PX65l6pJhzgxjo', $fingerprints[0]->value);
        self::assertSame(256, $fingerprints[0]->bits);
        self::assertSame('ED25519 SHA256:EZQpKi4iUrJWT2nvMqRy5H6Xxy5R1PX65l6pJhzgxjo', $fingerprints[0]->describe());
    }

    /** Serwer podaje zwykle kilka kluczy — do pytania idą wszystkie. */
    public function testSeveralFingerprintsAreRead(): void
    {
        $output = "256 SHA256:aaa host (ED25519)\n3072 SHA256:bbb host (RSA)\n256 SHA256:ccc host (ECDSA)";

        self::assertCount(3, FingerprintParser::parse($output));
    }

    /** Wiersz nie do rozczytania wypada, a reszta zostaje — jak pozycja playlisty bez ścieżki. */
    public function testUnreadableLinesFallOutAndTheRestSurvives(): void
    {
        $output = "coś zupełnie innego\n256 SHA256:aaa host (ED25519)\n";

        self::assertCount(1, FingerprintParser::parse($output));
    }

    public function testEmptyKeyscanOutputMeansNoFingerprints(): void
    {
        self::assertSame([], FingerprintParser::parse(''));
    }

    /**
     * Kolejność wzorców niepowodzeń **jest istotna**: zmieniony klucz wypisuje
     * ostrzeżenie *i* „Host key verification failed", więc ogólniejszy wzorzec
     * stojący pierwszy schowałby najgroźniejszy przypadek pod łagodniejszym.
     */
    public function testChangedHostKeyWinsOverTheGeneralRejection(): void
    {
        $output = "@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@\n"
            . "WARNING: REMOTE HOST IDENTIFICATION HAS CHANGED!\n"
            . "Host key verification failed.\n";

        self::assertSame('module.ssh.problem.key-changed', SshFailureReader::read($output));
    }

    public function testRefusedConnectionIsRecognised(): void
    {
        self::assertSame(
            'module.ssh.problem.refused',
            SshFailureReader::read('ssh: connect to host example.com port 22: Connection refused'),
        );
    }

    public function testDeniedAuthenticationIsRecognised(): void
    {
        self::assertSame(
            'module.ssh.problem.denied',
            SshFailureReader::read('anna@example.com: Permission denied (publickey).'),
        );
    }

    public function testUnknownComplaintStillGetsASentence(): void
    {
        self::assertSame('module.ssh.problem.failed', SshFailureReader::read('coś się popsuło'));
    }
}
