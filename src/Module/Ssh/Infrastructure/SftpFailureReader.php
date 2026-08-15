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

    /** Klucz katalogu z powodem — nigdy `null`, bo aplikacja nie ma prawa milczeć. */
    public static function read(string $output): string
    {
        foreach (self::PATTERNS as $needle => $key) {
            if (preg_match('/' . $needle . '/i', $output) === 1) {
                return $key;
            }
        }

        return 'module.ssh.listing.failed';
    }
}
