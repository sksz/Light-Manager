<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Infrastructure;

/**
 * Zamienia narzekanie klienta `ssh` na klucz katalogu napisów (krok 48).
 *
 * Klasa **czysta w całości**, jak `FingerprintParser`, i z tego samego powodu.
 *
 * **Skąd w ogóle mamy to narzekanie.** `ssh` pisze diagnostykę na **strumieniu
 * błędów**, a `BackgroundState` go świadomie nie niesie (krok 26: `du`
 * zasypałby go wierszami „brak dostępu"). Polecenia tego modułu kończą się
 * dlatego na `2>&1` — i wolno im, bo mistrz połączenia uruchamiany z `-N`
 * **na standardowym wyjściu nie pisze nic**, więc sklejenie strumieni niczego
 * tu nie miesza. To jest różnica wobec `du`, nie odstępstwo od tamtej reguły.
 *
 * Rozpoznawanie idzie po **napisach klienta**, i trzeba wiedzieć, czym się za to
 * płaci: napisy zależą od wersji OpenSSH i od języka, w którym klient mówi.
 * Stąd dwie ostrożności. Po pierwsze, wzorce dobrano tak, by trafiały
 * w **rzeczowniki protokołu** (`Host key verification failed`, `Permission
 * denied`), a nie w zdania ozdobne. Po drugie — i to jest ważniejsze — brak
 * dopasowania **nie jest błędem**: kończy się ogólnym „nie udało się połączyć",
 * a nie brakiem komunikatu. Aplikacja nigdy przez to nie milczy.
 */
final class SshFailureReader
{
    /**
     * Wzorzec → klucz katalogu, **w kolejności rozstrzygania**.
     *
     * Kolejność jest istotna w jednym miejscu: zmieniony klucz hosta wypisuje
     * ostrzeżenie **i** „Host key verification failed", więc gdyby ogólniejszy
     * wzorzec stał pierwszy, najgroźniejszy przypadek zniknąłby pod
     * łagodniejszym zdaniem.
     *
     * @var array<string, string>
     */
    private const PATTERNS = [
        'REMOTE HOST IDENTIFICATION HAS CHANGED' => 'module.ssh.problem.key-changed',
        'Host key verification failed' => 'module.ssh.problem.key-rejected',
        'Permission denied' => 'module.ssh.problem.denied',
        'Too many authentication failures' => 'module.ssh.problem.denied',
        'Could not resolve hostname' => 'module.ssh.problem.unresolved',
        'Name or service not known' => 'module.ssh.problem.unresolved',
        'Connection refused' => 'module.ssh.problem.refused',
        'Connection timed out' => 'module.ssh.problem.timeout',
        'Operation timed out' => 'module.ssh.problem.timeout',
        'No route to host' => 'module.ssh.problem.unreachable',
        'Network is unreachable' => 'module.ssh.problem.unreachable',
        'Connection closed by' => 'module.ssh.problem.closed',
        'Permissions .* are too open' => 'module.ssh.problem.key-permissions',
        'No such file or directory' => 'module.ssh.problem.key-missing',
    ];

    /** Klucz katalogu z powodem — nigdy `null`, bo aplikacja nie ma prawa milczeć. */
    public static function read(string $output): string
    {
        foreach (self::PATTERNS as $needle => $key) {
            if (preg_match('/' . $needle . '/i', $output) === 1) {
                return $key;
            }
        }

        return 'module.ssh.problem.failed';
    }
}
