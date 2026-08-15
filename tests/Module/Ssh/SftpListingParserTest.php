<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Ssh;

use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntryType;
use LightManager\Module\Ssh\Domain\ValueObject\RemotePath;
use LightManager\Module\Ssh\Infrastructure\SftpListingParser;
use PHPUnit\Framework\TestCase;

/**
 * Rozczytanie wypisu `sftp ls -l` (krok 49).
 *
 * **Wejście pochodzi z prawdziwego przebiegu** przeciwko serwerowi SFTP
 * (OpenSSH 9.6, kontener `atmoz/sftp:alpine` na pętli zwrotnej), skopiowane
 * bajt w bajt razem z nazwami, które wymyślono po to, żeby parser się na nich
 * wywrócił: odstępy, cudzysłów, apostrof, znaki spoza ASCII, myślnik na
 * początku, dowiązanie zerwane, plik starszy niż pół roku i nazwa ze **znakiem
 * nowej linii**. Bez tego test potwierdzałby wyłącznie, że kod zgadza się sam
 * ze sobą.
 *
 * Wypis powstał z `TZ=UTC`, bo tak go zamawia `SftpDirectoryService` — czas
 * formatuje **potomek**, więc bez narzuconej strefy daty zdalne rozjeżdżałyby
 * się z lokalnymi o tyle, ile wynosi różnica stref.
 */
final class SftpListingParserTest extends TestCase
{
    /** Wypis `ls -laf "/upload"` z żywego serwera, bajt w bajt. */
    private const OUTPUT = <<<'TEXT'
        sftp> ls -laf "/upload"
        drwxr-xr-x    ? 0        0            4096 Aug 15 18:32 /upload/kat ze spacja
        -rw-rw-r--    ? 1000     1000            2 Aug 15 18:04 /upload/-myslnik-na-poczatku
        drwxrwxr-x    ? 1000     1000       163840 Aug 15 18:06 /upload/wiele
        -rw-rw-r--    ? 1000     1000            2 Aug 15 18:04 /upload/cudzysłów"i'apostrof.txt
        -rw-rw-r--    ? 1000     1000            2 Aug 15 18:04 /upload/nazwa ze spacjami.txt
        -rw-rw-r--    ? 1000     1000            0 Mar  7  2023 /upload/stary.txt
        drwxr-xr-x    ? 0        0            4096 Aug 15 18:05 /upload/..
        lrwxrwxrwx    ? 1000     1000            8 Aug 15 18:04 /upload/dowiazanie.txt
        lrwxrwxrwx    ? 1000     1000           15 Aug 15 18:04 /upload/zerwane.txt
        drwxrwxr-x    ? 1000     1000         4096 Aug 15 18:32 /upload/.
        -rw-rw-r--    ? 1000     1000            2 Aug 15 18:04 /upload/zażółć gęślą jaźń.txt
        -rw-rw-r--    ? 1000     1000            1 Aug 15 18:06 /upload/nowa
        linia.txt
        drwxrwxr-x    ? 1000     1000         4096 Aug 15 18:04 /upload/podkatalog
        -rw-rw-r--    ? 1000     1000            7 Aug 15 18:04 /upload/plik.txt
        -rw-rw-r--    ? 1000     1000        12345 Aug 15 18:04 /upload/duzy.bin
        TEXT;

    /** Chwila odniesienia dla rachunku roku: 2026-08-15 12:00 UTC, tego samego dnia co przebieg. */
    private const NOW = 1_786_795_200;

    public function testEveryEntryIsReadWithItsName(): void
    {
        $names = array_map(
            static fn ($entry): string => $entry->name,
            SftpListingParser::parse(self::OUTPUT, RemotePath::of('/upload'), self::NOW)->entries,
        );

        self::assertContains('kat ze spacja', $names, 'odstęp w nazwie');
        self::assertContains('cudzysłów"i\'apostrof.txt', $names, 'cudzysłów i apostrof');
        self::assertContains('zażółć gęślą jaźń.txt', $names, 'znaki spoza ASCII');
        self::assertContains('-myslnik-na-poczatku', $names, 'myślnik na początku');
        self::assertContains('duzy.bin', $names);
    }

    /** Kropka i dwukropka wypadają — tak samo, jak w liście lokalnej. */
    public function testDotEntriesAreDropped(): void
    {
        $names = array_map(
            static fn ($entry): string => $entry->name,
            SftpListingParser::parse(self::OUTPUT, RemotePath::of('/upload'), self::NOW)->entries,
        );

        self::assertNotContains('.', $names);
        self::assertNotContains('..', $names);
    }

    public function testKindsComeFromTheFirstCharacter(): void
    {
        $entries = self::byName();

        self::assertSame(RemoteEntryType::Directory, $entries['podkatalog']->type);
        self::assertSame(RemoteEntryType::File, $entries['plik.txt']->type);
        self::assertSame(RemoteEntryType::Symlink, $entries['dowiazanie.txt']->type, 'ls -l widzi jak lstat');
        self::assertSame(RemoteEntryType::Symlink, $entries['zerwane.txt']->type, 'zerwane też jest dowiązaniem');
    }

    public function testSizeIsReadForFilesAndSkippedForDirectories(): void
    {
        $entries = self::byName();

        self::assertSame(12_345, $entries['duzy.bin']->sizeInBytes);
        self::assertNull($entries['podkatalog']->sizeInBytes, 'katalog nie ma rozmiaru do pokazania');
    }

    public function testPermissionsAreReadBackFromTheirTextForm(): void
    {
        $entries = self::byName();

        self::assertSame(0o664, $entries['plik.txt']->permissions);
        self::assertSame(0o775, $entries['podkatalog']->permissions);
        self::assertSame('rw-rw-r--', $entries['plik.txt']->permissionsAsText());
    }

    /**
     * Czas wypisany z godziną **nie niesie roku** — bierze go rachunek, tak samo
     * jak `ls`.
     */
    public function testTimeWithoutAYearTakesTheCurrentOne(): void
    {
        $stamp = self::byName()['plik.txt']->modifiedAt;

        self::assertNotNull($stamp);
        self::assertSame('2026-08-15 18:04', gmdate('Y-m-d H:i', $stamp));
    }

    /** Plik starszy niż pół roku ma odwrotnie: rok jest, godziny nie ma. */
    public function testTimeWithAYearIsTakenLiterally(): void
    {
        $stamp = self::byName()['stary.txt']->modifiedAt;

        self::assertNotNull($stamp);
        self::assertSame('2023-03-07 00:00', gmdate('Y-m-d H:i', $stamp));
    }

    /**
     * Data, która wypadłaby **w przyszłości**, należy do roku poprzedniego.
     *
     * To jest ta sama reguła, którą stosuje `ls`, i jedyny sposób, żeby grudniowy
     * plik oglądany w styczniu nie postarzał się o rok.
     */
    public function testATimeInTheFutureBelongsToThePreviousYear(): void
    {
        $line = '-rw-r--r--    ? 0        0               5 Dec 24 23:30 /upload/kolacja.txt';
        $january = (int) gmmktime(12, 0, 0, 1, 3, 2026);

        $entry = SftpListingParser::parse($line, RemotePath::of('/upload'), $january)->entries[0];

        self::assertNotNull($entry->modifiedAt);
        self::assertSame('2025-12-24 23:30', gmdate('Y-m-d H:i', $entry->modifiedAt));
    }

    /**
     * **Granica tej drogi, znana i zapisana**: nazwa ze znakiem nowej linii
     * rozpada się na dwa wiersze.
     *
     * Wpis zostaje z pierwszą linią swojej nazwy, a druga ląduje wśród
     * komunikatów — bo doklejanie wierszy nie do rozczytania do poprzedniej nazwy
     * doklejałoby także narzekanie klienta, które idzie tym samym strumieniem.
     */
    public function testANameWithANewlineKeepsItsFirstLineAndLeavesTheRestAsAMessage(): void
    {
        $listing = SftpListingParser::parse(self::OUTPUT, RemotePath::of('/upload'), self::NOW);
        $names = array_map(static fn ($entry): string => $entry->name, $listing->entries);

        self::assertContains('nowa', $names);
        self::assertContains('linia.txt', $listing->messages);
    }

    /** Wiersz odbity przez `sftp` nie jest wpisem i nie ma prawa nim zostać. */
    public function testTheEchoedCommandIsNotAnEntry(): void
    {
        $listing = SftpListingParser::parse(self::OUTPUT, RemotePath::of('/upload'), self::NOW);

        self::assertNotContains('ls -laf "/upload"', $listing->messages);
        self::assertSame(
            ['linia.txt'],
            $listing->messages,
            'komunikatem zostaje wyłącznie druga linia nazwy z nową linią — kropka i dwukropka wypadają cicho',
        );
    }

    /**
     * Listowanie katalogu bieżącego oddaje nazwy **gołe**, a `pwd` mówi, gdzie
     * jesteśmy. Tą drogą idzie pierwszy odczyt po połączeniu.
     */
    public function testTheWorkingDirectoryIsReadAndNamesComeBare(): void
    {
        $output = "sftp> pwd\nRemote working directory: /home/anna\nsftp> ls -lf\n"
            . "drwxr-xr-x    ? 1000     1000         4096 Aug 15 18:04 dokumenty\n";

        $listing = SftpListingParser::parse($output, null, self::NOW);

        self::assertSame('/home/anna', $listing->workingDirectory);
        self::assertSame('dokumenty', $listing->entries[0]->name);
    }

    /**
     * Wpis z cudzego katalogu odpada **po cichu**: przedrostek, który się nie
     * zgadza, znaczy bzdurną ścieżkę po pierwszym wejściu.
     *
     * Po cichu, bo wiersz **daje się rozczytać** — nie jest niczyim narzekaniem,
     * więc nie ma czego pokazywać użytkownikowi.
     */
    public function testAnEntryFromAnotherDirectoryIsDropped(): void
    {
        $line = '-rw-r--r--    ? 0        0               5 Aug 15 18:04 /gdzie/indziej/plik.txt';

        $listing = SftpListingParser::parse($line, RemotePath::of('/upload'), self::NOW);

        self::assertSame([], $listing->entries);
        self::assertSame([], $listing->messages);
    }

    /** @return array<string, \LightManager\Module\Ssh\Domain\ValueObject\RemoteEntry> */
    private static function byName(): array
    {
        $entries = [];

        foreach (SftpListingParser::parse(self::OUTPUT, RemotePath::of('/upload'), self::NOW)->entries as $entry) {
            $entries[$entry->name] = $entry;
        }

        return $entries;
    }
}
