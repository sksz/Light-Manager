<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Infrastructure;

/**
 * Zamienia narzekanie klienta `sftp` na klucz katalogu napisów (krok 49).
 *
 * Klasa **czysta w całości**, jak `SshFailureReader` z kroku 48, i stoi obok
 * niego, a nie zamiast: tamten czyta niepowodzenia **połączenia**, ten —
 * niepowodzenia **odczytu katalogu**. Rozdział nie jest porządkowy, tylko
 * znaczeniowy: „nie ma takiego katalogu" i „nie udało się uwierzytelnić" to dwa
 * różne zdania dla użytkownika, choć oba wychodzą z tego samego programu.
 *
 * **Rozpoznanie zaczyna się od zdań `sftp`, a kończy na zdaniach `ssh`.**
 * Kolejność jest ta sama, co kolejność zdarzeń: klient najpierw próbuje wejść
 * przez gniazdo mistrza (i wtedy narzeka jak `ssh`), a dopiero potem czyta
 * katalog (i wtedy narzeka jak `sftp`). Brak dopasowania **nie jest błędem** —
 * kończy się ogólnym „nie udało się odczytać katalogu”, więc aplikacja nigdy
 * nie milczy.
 */
final class SftpFailureReader
{
    /**
     * Wzorzec → klucz katalogu, **w kolejności rozstrzygania**.
     *
     * Kolejność ma znaczenie w jednym miejscu i jest ono niedrobiazgowe:
     * `Permission denied` pada zarówno wtedy, gdy nie wolno wejść do katalogu,
     * jak i wtedy, gdy nie udało się uwierzytelnienie. Wzorce odczytu stoją
     * dlatego **przed** wzorcami połączenia — a rozstrzyga o pierwszeństwie
     * słowo `ls`, które pada wyłącznie w tym pierwszym przypadku.
     *
     * @var array<string, string>
     */
    private const PATTERNS = [
        // Napisy z **prawdziwego przebiegu** (OpenSSH 9.6): katalog nieistniejący
        // narzeka „Can't ls: … not found", a katalog bez prawa wejścia —
        // „remote readdir(…): Permission denied". Drugiego nie było w pierwszej
        // wersji tej tablicy i wpadał przez to pod ogólne „Permission denied",
        // czyli mówił użytkownikowi „sesja zerwana" o żywej sesji.
        'remote (readdir|opendir).*[Pp]ermission denied' => 'module.ssh.listing.denied',
        'remote (readdir|opendir).*[Nn]o such file' => 'module.ssh.listing.missing',
        'Can\'t ls:.*not found' => 'module.ssh.listing.missing',
        'Can\'t ls:.*[Pp]ermission denied' => 'module.ssh.listing.denied',
        'Can\'t ls' => 'module.ssh.listing.unreadable',
        'Couldn\'t (stat|get handle|canonicalize)' => 'module.ssh.listing.unreadable',
        'Connection closed' => 'module.ssh.listing.dropped',
        'Broken pipe' => 'module.ssh.listing.dropped',
        'Control socket connect.*: No such file' => 'module.ssh.listing.dropped',
        'Permission denied' => 'module.ssh.listing.dropped',
        'Connection (refused|timed out)' => 'module.ssh.listing.dropped',
        'subsystem request failed' => 'module.ssh.listing.unsupported',
    ];

    /**
     * Wzorce przesyłu — **druga tablica, nie drugie rozpoznanie** (krok 50).
     *
     * Osobna, bo te same słowa znaczą tu co innego dla użytkownika: „nie ma
     * takiego pliku" przy odczycie katalogu mówi „wpisz inną ścieżkę", a przy
     * przesyle — „plik zniknął, zanim po niego sięgnęliśmy". Wspólny został
     * rachunek, nie słownik.
     *
     * Napisy z prawdziwego przebiegu (OpenSSH 9.6): odmowa zapisu po stronie
     * zdalnej to `dest open "…": Permission denied`, zajęta nazwa przy
     * zatwierdzeniu — `remote rename "…" to "…": Failure` (bo `rename -l` idzie
     * bez rozszerzenia POSIX-owego), a **sesja zerwana w środku pracy nie mówi
     * nic**: `sftp` ginie od `SIGPIPE` z kodem 141 i pustym strumieniem błędów.
     * Ten ostatni przypadek rozstrzyga więc kod wyjścia u wołającego, a nie ta
     * tablica — i dlatego pusty wypis wraca stąd z ogólnym „nie udało się".
     *
     * @var array<string, string>
     */
    private const TRANSFER_PATTERNS = [
        'remote rename .*Failure' => 'module.ssh.transfer.nameTaken',
        'remote rename .*[Pp]ermission denied' => 'module.ssh.transfer.denied',
        'dest open .*[Pp]ermission denied' => 'module.ssh.transfer.denied',
        'dest open .*No such file' => 'module.ssh.transfer.missingTarget',
        'open local .*[Pp]ermission denied' => 'module.ssh.transfer.denied',
        'open local .*No such file' => 'module.ssh.transfer.missingTarget',
        'stat .*No such file' => 'module.ssh.transfer.missingSource',
        'File .* not found' => 'module.ssh.transfer.missingSource',
        '(Couldn\'t|Can\'t) (get|put|stat|fsetstat).*No such file' => 'module.ssh.transfer.missingSource',
        '(Couldn\'t|Can\'t) (get|put|stat).*[Pp]ermission denied' => 'module.ssh.transfer.denied',
        'No space left' => 'module.ssh.transfer.noSpace',
        'Disk quota exceeded' => 'module.ssh.transfer.noSpace',
        'Connection closed' => 'module.ssh.transfer.dropped',
        'Broken pipe' => 'module.ssh.transfer.dropped',
        'Control socket connect.*: No such file' => 'module.ssh.transfer.dropped',
        'Connection (refused|timed out)' => 'module.ssh.transfer.dropped',
        'Permission denied' => 'module.ssh.transfer.dropped',
    ];

    /** Klucz katalogu z powodem — nigdy `null`, bo aplikacja nie ma prawa milczeć. */
    public static function read(string $output): string
    {
        return self::match(self::PATTERNS, $output, 'module.ssh.listing.failed');
    }

    /** To samo dla przesyłu (krok 50) — inna tablica, ten sam rachunek. */
    public static function readTransfer(string $output): string
    {
        return self::match(self::TRANSFER_PATTERNS, $output, 'module.ssh.transfer.failed');
    }

    /** @param array<string, string> $patterns */
    private static function match(array $patterns, string $output, string $fallback): string
    {
        foreach ($patterns as $needle => $key) {
            if (preg_match('/' . $needle . '/i', $output) === 1) {
                return $key;
            }
        }

        return $fallback;
    }
}
