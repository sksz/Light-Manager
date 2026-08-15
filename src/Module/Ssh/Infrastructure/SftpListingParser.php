<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Infrastructure;

use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntry;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntryType;
use LightManager\Module\Ssh\Domain\ValueObject\RemotePath;

/**
 * Zamienia wypis `sftp ls -l` na wpisy katalogu (krok 49).
 *
 * Klasa **czysta w całości**, jak `KnownHostsReader` i `FingerprintParser`
 * z kroku 48 — i z tego samego powodu: sprawdza się ją testem na wejściach
 * podanych wprost, bez ani jednego bajtu w sieci. Wejścia w testach pochodzą
 * z prawdziwego przebiegu przeciwko serwerowi SFTP, a nie z ręcznego rachunku.
 *
 * **Wypis składa klient, nie serwer**, i to jest fakt, na którym stoi cały ten
 * parser (sprawdzony na żywym serwerze, OpenSSH 9.6): pole liczby dowiązań
 * pokazuje `?`, bo protokół SFTP jej nie niesie, właściciel i grupa są **zawsze
 * liczbami**, a nazwa miesiąca **nie zależy od ustawień językowych** — `ls_file()`
 * formatuje czas w locale „C”. Postać wiersza nie zależy więc od tego, co stoi
 * po drugiej stronie, co odróżnia tę drogę od odrzuconego `ssh ls -l`.
 *
 * **Czego wypis nie niesie i czego przez to nie ma we wpisie**: sekund (czas ma
 * dokładność minuty), roku dla plików świeższych niż pół roku (stąd rachunek
 * w `timestamp()`), nazw właściciela i grupy (protokół oddaje liczby, a nazw po
 * drugiej stronie nikt nie rozwiąże).
 *
 * **Nazwa ze znakiem nowej linii jest granicą tej drogi i jest znana.** Wypis
 * jest podzielony na wiersze, więc taka nazwa rozpada się na dwa — a ponieważ
 * strumień błędów jest z wypisem sklejony (inaczej powód niepowodzenia
 * przepadłby w całości), wiersza nie do rozczytania **nie wolno** doklejać do
 * poprzedniej nazwy: doklejałoby się wtedy także komunikaty klienta. Wiersz taki
 * ląduje więc wśród komunikatów, a wpis pokazuje pierwszą linię swojej nazwy.
 * Cena jest zapisana w dzienniku kroku.
 */
final class SftpListingParser
{
    /**
     * Wiersz wpisu: rodzaj, prawa, dowiązania, właściciel, grupa, rozmiar, czas,
     * nazwa.
     *
     * Nazwa idzie **po dokładnie jednym odstępie** za czasem, a nie po `\s+`,
     * i to nie jest drobiazg: `ls_file()` skleja czas z nazwą jedną spacją, więc
     * `\s+` zjadłoby pierwszy znak nazwy zaczynającej się od odstępu.
     */
    private const ENTRY_PATTERN = '/^([-dlbcpsD?])([rwxsStT-]{9})[.+]?\s+(?:\S+)\s+(?:\S+)\s+(?:\S+)\s+(\d+)\s+'
        . '([A-Za-z]{3})\s+(\d{1,2})\s+(\d{2}:\d{2}|\d{4})\s(.*)$/';

    /**
     * Podział na wiersze **wypisany wprost, a nie przez `\R`**.
     *
     * Wygląda na drobiazg, a jest usterką znalezioną w tym kroku na prawdziwej
     * nazwie pliku: `\R` poza trybem UTF-8 traktuje bajt `0x85` jako znak nowej
     * linii, a `0x85` jest **drugim bajtem litery `ą`**. Nazwa „zażółć gęślą
     * jaźń.txt" rozpadała się przez to w połowie znaku.
     *
     * Trybu UTF-8 (`u`) nie wolno tu za to włączyć **ani tutaj, ani we wzorcu
     * wpisu**: nazwa pliku na cudzej maszynie nie musi być poprawnym UTF-8,
     * a `preg_*` na niepoprawnym wejściu oddaje `false` — czyli jeden zepsuty
     * bajt w jednej nazwie kasowałby cały katalog.
     */
    private const LINE_PATTERN = '/\r\n|\n|\r/';

    private const MONTHS = [
        'Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4, 'May' => 5, 'Jun' => 6,
        'Jul' => 7, 'Aug' => 8, 'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12,
    ];

    /** Wiersz, którym `sftp` odbija zadane mu polecenie. */
    private const ECHO_PREFIX = 'sftp> ';

    private const PERMISSION_MASK = 0o7777;

    /**
     * @param string          $output wypis polecenia wraz ze strumieniem błędów
     * @param RemotePath|null $path   katalog, o który pytano; `null` — pytano
     *                                o startowy i ścieżka przyjdzie z `pwd`
     */
    public static function parse(string $output, ?RemotePath $path, int $now): SftpListing
    {
        $entries = [];
        $messages = [];
        $workingDirectory = null;

        foreach (preg_split(self::LINE_PATTERN, $output) ?: [] as $line) {
            if ($line === '' || str_starts_with($line, self::ECHO_PREFIX)) {
                continue;
            }

            if (str_starts_with($line, SftpCommand::WORKING_DIRECTORY_PREFIX)) {
                $workingDirectory = trim(substr($line, strlen(SftpCommand::WORKING_DIRECTORY_PREFIX)));

                continue;
            }

            if (preg_match(self::ENTRY_PATTERN, $line, $matches) !== 1) {
                // Wiersz, który nie jest wpisem, jest **komunikatem** — narzekaniem
                // klienta albo drugą linią nazwy zawierającej znak nowej linii.
                // Przy powodzeniu nikt tu nie zagląda.
                $messages[] = $line;

                continue;
            }

            $entry = self::entryFrom($matches, $path ?? self::pathOrNull($workingDirectory), $now);

            // `null` znaczy tu „wiersz rozczytany, ale nie ma czego pokazać" —
            // kropka, dwukropka albo wpis z cudzego katalogu. Do komunikatów
            // **nie trafia**, bo nie jest niczyim narzekaniem: gdyby trafiał,
            // każdy odczyt kończyłby się dwoma zdaniami do zignorowania.
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return new SftpListing($entries, $messages, $workingDirectory);
    }

    /**
     * Wpis z rozczytanego wiersza albo `null`, gdy nie ma czego pokazać.
     *
     * Nazwa przychodzi z przedrostkiem ścieżki wtedy i tylko wtedy, gdy o katalog
     * pytano wprost (`ls -l /kat`); listowanie katalogu bieżącego (`ls -l` po
     * `pwd`) oddaje nazwy gołe. Obsłużone są oba przypadki, bo oba naprawdę
     * zachodzą — pierwszy przy chodzeniu po katalogach, drugi przy pierwszym
     * odczycie po połączeniu.
     *
     * @param array<int, string> $matches
     */
    private static function entryFrom(array $matches, ?RemotePath $path, int $now): ?RemoteEntry
    {
        $name = self::nameFrom($matches[7], $path);

        if ($name === null) {
            return null;
        }

        $type = RemoteEntryType::fromMode($matches[1]);

        return new RemoteEntry(
            $name,
            $type,
            $type->isDirectory() ? null : (int) $matches[3],
            self::timestamp($matches[4], (int) $matches[5], $matches[6], $now),
            self::permissions($matches[2]),
        );
    }

    /** `null` znaczy „to nie jest wpis do pokazania”: kropka, dwukropka albo pusta nazwa. */
    private static function nameFrom(string $raw, ?RemotePath $path): ?string
    {
        $name = $raw;
        $prefix = $path?->prefix();

        if ($prefix !== null && str_starts_with($name, $prefix)) {
            $name = substr($name, strlen($prefix));
        }

        if ($name === '' || $name === '.' || $name === '..' || str_contains($name, RemotePath::SEPARATOR)) {
            // Ukośnik w nazwie znaczy, że przedrostek nie zgadza się z tym, o co
            // pytaliśmy — wpis z cudzego katalogu albo wiersz nie do rozczytania.
            // Wpuszczony dalej, zrobiłby ze ścieżki bzdurę przy pierwszym wejściu.
            return null;
        }

        return $name;
    }

    /**
     * Czas z wypisu — **z rokiem dorobionym, gdy wypis go nie podał**.
     *
     * `ls -l` pokazuje godzinę zamiast roku dla wpisów świeższych niż pół roku,
     * więc rok trzeba wyliczyć. Reguła jest ta sama, którą stosuje `ls`: rok
     * bieżący, a gdy data wypadłaby **w przyszłości**, to poprzedni. Dzień zapasu
     * jest po to, żeby zegar hosta spieszący się o kilka godzin nie cofał dat
     * o rok.
     *
     * Wypis formatuje **potomek**, a formatuje go w UTC — polecenie narzuca mu
     * `TZ=UTC` (patrz `SftpDirectoryService`), więc rachunek poniżej idzie w tej
     * samej strefie i nie zależy ani od strefy systemu, ani od ustawienia PHP.
     * Bez tego narzucenia data zdalnego pliku różniłaby się od daty lokalnego
     * o tyle, ile wynosi rozjazd stref — i nikt by tego nie zauważył.
     */
    private static function timestamp(string $month, int $day, string $rest, int $now): ?int
    {
        $number = self::MONTHS[ucfirst(strtolower($month))] ?? null;

        if ($number === null) {
            return null;
        }

        if (!str_contains($rest, ':')) {
            $stamp = gmmktime(0, 0, 0, $number, $day, (int) $rest);

            return $stamp === false ? null : $stamp;
        }

        [$hour, $minute] = array_map(intval(...), explode(':', $rest, 2));
        $year = (int) gmdate('Y', $now);
        $stamp = gmmktime($hour, $minute, 0, $number, $day, $year);

        if ($stamp === false) {
            return null;
        }

        return $stamp > $now + 86_400 ? (int) gmmktime($hour, $minute, 0, $number, $day, $year - 1) : $stamp;
    }

    /** Bity uprawnień z zapisu `rwxr-xr-x`, wraz z bitami specjalnymi. */
    private static function permissions(string $text): ?int
    {
        if (strlen($text) !== 9) {
            return null;
        }

        $bits = 0;

        foreach ([0, 3, 6] as $offset) {
            $bits <<= 3;
            $bits |= ($text[$offset] === 'r' ? 4 : 0)
                | ($text[$offset + 1] === 'w' ? 2 : 0)
                | (in_array($text[$offset + 2], ['x', 's', 't'], true) ? 1 : 0);
        }

        $bits |= in_array($text[2], ['s', 'S'], true) ? 0o4000 : 0;
        $bits |= in_array($text[5], ['s', 'S'], true) ? 0o2000 : 0;
        $bits |= in_array($text[8], ['t', 'T'], true) ? 0o1000 : 0;

        return $bits & self::PERMISSION_MASK;
    }

    private static function pathOrNull(?string $value): ?RemotePath
    {
        if ($value === null || !str_starts_with($value, RemotePath::SEPARATOR)) {
            return null;
        }

        return RemotePath::of($value);
    }
}
