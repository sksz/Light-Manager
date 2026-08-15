<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Infrastructure;

/**
 * Odpowiada na jedno pytanie: **czy `ssh` już zna ten host** (krok 48, D87 nr 6).
 *
 * Klasa jest **czysta poza jednym odczytem pliku** i to jest warunek jej
 * istnienia: cały rachunek dopasowania da się sprawdzić testem bez ani jednego
 * bajtu w sieci, na wpisach podanych wprost (`matches()`), a `knows()` dokłada do
 * tego wyłącznie `file()`. Sieci nie dotyka nigdy.
 *
 * **Dlaczego moduł czyta ten plik sam, skoro pisze go `ssh`.** Bo pytanie pada
 * **przed** połączeniem: od odpowiedzi zależy, czy w ogóle otworzyć okno
 * z pytaniem o odcisk, a `ssh` odpowiedziałby dopiero w trakcie i na strumieniu
 * błędów. To jest ta sama różnica, co między „zapytaj o zgodę" a „przeproś".
 *
 * **Nazwy w tym pliku są zahaszowane** — wszystkie 23 wpisy na maszynie projektu
 * i tak jest domyślnie od lat (`HashKnownHosts yes`). Wiersz wygląda wtedy tak:
 *
 * ```
 * |1|<sól w base64>|<HMAC-SHA1 nazwy w base64> ssh-ed25519 AAAA…
 * ```
 *
 * Dopasowanie to `hash_hmac('sha1', $nazwa, base64_decode($sól), true)`
 * porównane z odkodowaną drugą połową. Kluczem HMAC jest **sól, nie nazwa** —
 * odwrotnie niż podpowiada intuicja i odwrotnie niż wygląda kolejność
 * argumentów.
 *
 * Trzy postacie wpisu, które trzeba znieść, bo wszystkie są legalne:
 * zahaszowana (`|1|…`), jawna (`host` albo `[host]:port`) i lista rozdzielona
 * przecinkami. Wzorców z gwiazdką **nie rozwijamy** — wiersz `*.example.com`
 * odpowiada „nie wiem", a nie „nie zna": pomyłka w tę stronę kończy się
 * zbędnym pytaniem o odcisk, pomyłka w drugą — połączeniem bez pytania.
 */
final class KnownHostsReader
{
    private const HASH_MARKER = '|1|';

    /** Wiersze zaczynające się od tych znaczników nie są wpisami hosta. */
    private const MARKERS = ['@cert-authority', '@revoked'];

    /**
     * @param string|null $path ścieżka pliku; `null` znaczy `~/.ssh/known_hosts`.
     *                          Podstawialna, bo test nie ma prawa czytać pliku
     *                          użytkownika
     */
    public function __construct(private readonly ?string $path = null)
    {
    }

    /**
     * Ścieżka pliku, który ta klasa czyta — **i którą trzeba narzucić klientowi**.
     *
     * Wystawione publicznie po próbie z żywym serwerem (krok 48, dziennik):
     * `ssh` rozwija `~` w `UserKnownHostsFile` z **wpisu w `passwd`**, a nie ze
     * zmiennej `HOME`, więc czytający i piszący mogą trafić na dwa różne pliki.
     * Na zwykłej maszynie to ten sam plik, ale z przypadku, nie z gwarancji —
     * a w próbie te dwie drogi się rozeszły i wpis „zniknął". Odtąd usługa podaje
     * `ssh` tę ścieżkę wprost, więc obie strony zgadzają się **z konstrukcji**.
     *
     * Cena, którą trzeba znać: narzucenie jednego pliku pomija `known_hosts2`
     * (postać wycofana od lat). Pliku globalnego (`/etc/ssh/ssh_known_hosts`) ta
     * klasa nie czyta i nie czytała — host znany wyłącznie z niego zapyta
     * o odcisk raz, po czym trafi do pliku użytkownika.
     */
    public function location(): string
    {
        if ($this->path !== null) {
            return $this->path;
        }

        $home = getenv('HOME');

        if (!is_string($home) || $home === '') {
            return '.ssh/known_hosts';
        }

        return rtrim($home, DIRECTORY_SEPARATOR) . '/.ssh/known_hosts';
    }

    /** Czy plik zna ten host na tym porcie. Brak pliku znaczy „nie zna". */
    public function knows(string $host, int $port): bool
    {
        $path = $this->location();

        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $content = @file_get_contents($path);

        return $content !== false && self::matches($content, $host, $port);
    }

    /**
     * Ten sam rachunek na treści podanej wprost — **cały testowalny kawałek
     * klasy**.
     *
     * @param string $content zawartość pliku `known_hosts`
     */
    public static function matches(string $content, string $host, int $port): bool
    {
        $wanted = self::wantedNames($host, $port);

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            if (self::lineMatches($line, $wanted)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Postacie nazwy, pod którymi `ssh` mógł zapisać ten host.
     *
     * Port domyślny zapisuje się **samą nazwą**, każdy inny — w nawiasach
     * kwadratowych wraz z portem. Obie postacie trzeba sprawdzić, bo wpis mógł
     * powstać przy innym ustawieniu portu.
     *
     * @return list<string>
     */
    private static function wantedNames(string $host, int $port): array
    {
        $bracketed = '[' . $host . ']:' . $port;

        return $port === 22 ? [$host, $bracketed] : [$bracketed];
    }

    /** @param list<string> $wanted */
    private static function lineMatches(string $line, array $wanted): bool
    {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            return false;
        }

        foreach (self::MARKERS as $marker) {
            if (str_starts_with($line, $marker)) {
                return false;
            }
        }

        // `strtok()` odpada świadomie: trzyma stan globalny między wywołaniami,
        // a ta metoda woła się raz na wiersz pliku.
        $fields = preg_split('/[ \t]+/', $line, 2);
        $names = $fields[0] ?? '';

        if ($names === '') {
            return false;
        }

        if (str_starts_with($names, self::HASH_MARKER)) {
            return self::hashedMatches($names, $wanted);
        }

        foreach (explode(',', $names) as $candidate) {
            // Wzorzec z gwiazdką albo zaprzeczeniem zostawiamy nierozwinięty —
            // patrz opis klasy. Porównanie jest dokładne i bez rozróżniania
            // wielkości liter, bo nazwa hosta jest na nią nieczuła.
            if (str_contains($candidate, '*') || str_contains($candidate, '?')) {
                continue;
            }

            foreach ($wanted as $name) {
                if (strcasecmp($candidate, $name) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param list<string> $wanted */
    private static function hashedMatches(string $names, array $wanted): bool
    {
        $parts = explode('|', $names);

        // `|1|sól|skrót` rozpada się na pięć części, bo napis zaczyna się
        // od separatora: ['', '1', sól, skrót].
        if (count($parts) !== 4) {
            return false;
        }

        $salt = base64_decode($parts[2], true);
        $digest = base64_decode($parts[3], true);

        if ($salt === false || $digest === false || $salt === '') {
            return false;
        }

        foreach ($wanted as $name) {
            // Kluczem HMAC jest **sól**, a nazwa jest treścią — nie odwrotnie.
            if (hash_equals($digest, hash_hmac('sha1', $name, $salt, true))) {
                return true;
            }
        }

        return false;
    }

}
