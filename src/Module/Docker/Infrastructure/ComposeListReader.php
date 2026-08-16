<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Infrastructure;

use LightManager\Module\Docker\Domain\ValueObject\ComposeProject;

/**
 * Wypis wtyczki compose zamieniony na obiekty domeny (krok 51).
 *
 * `docker compose ls -a --format json` oddaje **tablicę**, a nie strumień
 * obiektów — inaczej niż `docker ps --format json`, który oddaje po jednym
 * obiekcie na wiersz. Różnica jest w tym samym kliencie, w dwóch sąsiednich
 * poleceniach, i sprawdzono ją na żywo przed napisaniem tej klasy.
 *
 * **Ścieżka pliku bywa względna** i to jest osobliwość, która przesądza
 * o kształcie funkcji `down`: `ConfigFiles` niesie to, co podano przy
 * podnoszeniu projektu, więc projekt podniesiony poleceniem
 * `docker compose -f docker/dev/compose.yaml up` ma tam **ścieżkę względną**
 * — bezużyteczną dla kogoś, kto stoi w innym katalogu. Czytnik oddaje ją taką,
 * jaka jest, a rozstrzyga o niej ten, kto jej użyje: ścieżka bezwzględna
 * pozwala położyć projekt bez pytania, względna każe zapytać o plik.
 */
final class ComposeListReader
{
    /**
     * Spis projektów; pusta lista, gdy wypisu nie da się rozczytać.
     *
     * @return list<ComposeProject>
     */
    public static function projects(string $output): array
    {
        $decoded = json_decode(trim($output), true);

        if (!is_array($decoded)) {
            return [];
        }

        $projects = [];

        foreach ($decoded as $entry) {
            if (!is_array($entry) || !is_string($entry['Name'] ?? null) || $entry['Name'] === '') {
                continue;
            }

            $configFiles = $entry['ConfigFiles'] ?? null;

            $projects[] = new ComposeProject(
                $entry['Name'],
                is_string($entry['Status'] ?? null) ? $entry['Status'] : '',
                is_string($configFiles) && $configFiles !== '' ? self::firstFile($configFiles) : null,
            );
        }

        return $projects;
    }

    /**
     * Powód odmowy wyczytany ze strumienia błędów — **ostatni niepusty wiersz**.
     *
     * Ostatni, bo wtyczka wypisuje najpierw ostrzeżenia (wersja pliku,
     * nieużywane zmienne), a dopiero na końcu to, przez co się poddała. Pierwszy
     * wiersz podałby użytkownikowi ostrzeżenie w miejscu powodu.
     */
    public static function reason(string $errorOutput): string
    {
        $line = self::lastLine($errorOutput);

        return $line === '' ? 'docker compose' : $line;
    }

    /** Ostatni niepusty wiersz wypisu; pusty napis, gdy wypis milczy. */
    public static function lastLine(string $output): string
    {
        $lines = array_values(array_filter(
            array_map(trim(...), explode("\n", str_replace("\r", "\n", $output))),
            static fn (string $line): bool => $line !== '',
        ));

        return $lines === [] ? '' : $lines[count($lines) - 1];
    }

    /**
     * Pierwszy plik z pola `ConfigFiles`.
     *
     * Pole niesie **listę rozdzieloną przecinkami**, gdy projekt złożono z kilku
     * plików (`compose.yaml,compose.override.yaml`). Pierwszy wystarczy: `down`
     * potrzebuje projektu, a nie kompletu warstw, z których go zbudowano.
     */
    private static function firstFile(string $configFiles): string
    {
        $first = explode(',', $configFiles)[0];

        return trim($first);
    }
}
