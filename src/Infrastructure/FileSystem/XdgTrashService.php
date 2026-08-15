<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\FileSystem;

use LightManager\Application\Port\TrashPort;
use LightManager\Domain\Exception\FileOperationException;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Kosz wedle specyfikacji freedesktop.org (krok 44, D81).
 *
 * Usługa jest bezstanowa wobec konfiguracji: katalog kosza przychodzi w każdym
 * wywołaniu, bo jego wybór jest pozycją ustawień **modułu**, a rdzeń ustawień
 * modułu nie czyta. Układ jest za to wszędzie ten sam — `files/` na wpisy,
 * `info/` na pliki informacyjne — także w katalogu wskazanym ręcznie: ścieżka
 * powrotna zapisana obok wpisu jest jedyną rzeczą, która przeżywa zamknięcie
 * aplikacji (D81, nr 3).
 *
 * **Plik informacyjny powstaje przed przeniesieniem i jest rezerwacją nazwy.**
 * Tryb `x` przy jego tworzeniu daje jedyność bez zamków: dwa procesy proszące
 * o tę samą nazwę dostaną ją najwyżej raz, a przegrany weźmie sufiks — tak
 * właśnie rezerwują nazwy implementacje środowisk graficznych. Sufiks jest
 * liczbowy i staje **przed rozszerzeniem** (`raport.pdf`, `raport.1.pdf`),
 * jak w koszu środowiska (D81, nr 11).
 *
 * `DeletionDate` idzie w czasie lokalnym bez strefy, `Path` — zakodowany jak
 * adres URL, ukośniki bez zmian: obie postaci wprost ze specyfikacji, żeby
 * wpis z aplikacji dawał się przywrócić z pulpitu i odwrotnie.
 */
final class XdgTrashService extends AbstractSingleton implements TrashPort
{
    public function defaultDirectory(): string
    {
        $data = getenv('XDG_DATA_HOME');

        if (is_string($data) && $data !== '') {
            return rtrim($data, '/') . '/Trash';
        }

        $home = getenv('HOME');

        if (!is_string($home) || $home === '') {
            // Stan patologiczny, ten sam co w `SettingsService`: bez katalogu
            // domowego kosz staje w katalogu roboczym, żeby usunięcie działało
            // zamiast wywracać się na starcie.
            $working = getcwd();
            $home = $working === false ? '.' : $working;
        }

        return rtrim($home, '/') . '/.local/share/Trash';
    }

    public function accepts(string $path, string $trashDirectory): bool
    {
        $entry = @lstat($path);

        if ($entry === false) {
            return false;
        }

        return $entry['dev'] === self::deviceOf($trashDirectory);
    }

    public function moveToTrash(string $path, string $trashDirectory): string
    {
        if (!file_exists($path) && !is_link($path)) {
            throw FileOperationException::missing($path);
        }

        if (!is_writable(dirname($path))) {
            throw FileOperationException::denied($path);
        }

        $name = $this->reserve($path, $trashDirectory);
        $target = $trashDirectory . '/files/' . $name;

        error_clear_last();

        if (!@rename($path, $target)) {
            // Nieudane przeniesienie zdejmuje rezerwację: plik informacyjny bez
            // wpisu w `files/` obiecywałby przywrócenie czegoś, czego nie ma.
            @unlink($trashDirectory . '/info/' . $name . '.trashinfo');

            throw FileOperationException::failed($path, self::lastError());
        }

        return $name;
    }

    public function reserve(string $path, string $trashDirectory): string
    {
        $this->ensureLayout($trashDirectory);

        $base = basename($path);
        $content = "[Trash Info]\nPath=" . self::encodePath($path)
            . "\nDeletionDate=" . date('Y-m-d\TH:i:s') . "\n";

        for ($attempt = 0; ; ++$attempt) {
            $name = self::candidate($base, $attempt);

            if (file_exists($trashDirectory . '/files/' . $name)) {
                continue;
            }

            $handle = @fopen($trashDirectory . '/info/' . $name . '.trashinfo', 'x');

            if ($handle === false) {
                // Nazwa zajęta w `info/` — cudza rezerwacja. Następny sufiks.
                continue;
            }

            fwrite($handle, $content);
            fclose($handle);

            return $name;
        }
    }

    public function releaseUnused(array $names, string $trashDirectory): array
    {
        $kept = [];

        foreach ($names as $name) {
            if (file_exists($trashDirectory . '/files/' . $name) || is_link($trashDirectory . '/files/' . $name)) {
                $kept[] = $name;

                continue;
            }

            @unlink($trashDirectory . '/info/' . $name . '.trashinfo');
        }

        return $kept;
    }

    public function restore(string $trashName, string $trashDirectory): string
    {
        $entry = $trashDirectory . '/files/' . $trashName;
        $info = $trashDirectory . '/info/' . $trashName . '.trashinfo';

        if (!file_exists($entry) && !is_link($entry)) {
            throw FileOperationException::missing($entry);
        }

        $path = self::pathFrom($info);

        if ($path === null) {
            throw FileOperationException::failed($entry, 'trash info file is missing or malformed');
        }

        if (file_exists($path) || is_link($path)) {
            throw FileOperationException::nameTaken($path);
        }

        if (!is_dir(dirname($path))) {
            // Katalog, z którego wpis zniknął, sam zdążył zniknąć — przywrócenie
            // nie ma dokąd wrócić, a zakładanie drzewa katalogów nie jest jego
            // czynnością.
            throw FileOperationException::missing(dirname($path));
        }

        error_clear_last();

        if (!@rename($entry, $path)) {
            throw FileOperationException::failed($entry, self::lastError());
        }

        @unlink($info);

        return $path;
    }

    /**
     * `files/` i `info/` wraz z katalogiem kosza — `0700`, jak każe specyfikacja:
     * kosz jest prywatny, a prawa szersze niż właściciela byłyby decyzją,
     * której użytkownik nie zamawiał.
     */
    private function ensureLayout(string $trashDirectory): void
    {
        foreach ([$trashDirectory, $trashDirectory . '/files', $trashDirectory . '/info'] as $directory) {
            if (is_dir($directory)) {
                continue;
            }

            error_clear_last();

            if (!@mkdir($directory, 0o700, true) && !is_dir($directory)) {
                throw FileOperationException::failed($directory, self::lastError());
            }
        }
    }

    /** Nazwa dla kolejnej próby: `raport.pdf`, `raport.1.pdf`, `raport.2.pdf`. */
    private static function candidate(string $base, int $attempt): string
    {
        if ($attempt === 0) {
            return $base;
        }

        $dot = strrpos($base, '.');

        // Kropka na początku to wpis ukryty, nie rozszerzenie.
        if ($dot === false || $dot === 0) {
            return $base . '.' . $attempt;
        }

        return substr($base, 0, $dot) . '.' . $attempt . substr($base, $dot);
    }

    /**
     * `Path=` koduje się jak adres URL, ale ukośniki zostają: specyfikacja każe
     * kodować oktety spoza znaków bezpiecznych, a separator ścieżki jest jej
     * szkieletem.
     */
    private static function encodePath(string $path): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }

    /** Ścieżka powrotna z pliku informacyjnego; `null`, gdy pliku nie ma albo nie mówi. */
    private static function pathFrom(string $info): ?string
    {
        $content = @file_get_contents($info);

        if ($content === false) {
            return null;
        }

        if (preg_match('/^Path=(.+)$/m', $content, $found) !== 1) {
            return null;
        }

        $path = rawurldecode(trim($found[1]));

        return $path === '' ? null : $path;
    }

    /**
     * Numer urządzenia katalogu kosza — albo najbliższego istniejącego przodka,
     * bo kosz mógł jeszcze nie powstać, a pytanie „czy przyjmiesz” nie ma prawa
     * niczego zakładać.
     */
    private static function deviceOf(string $directory): ?int
    {
        $current = $directory;

        while (true) {
            $stat = @stat($current);

            if ($stat !== false) {
                return $stat['dev'];
            }

            $parent = dirname($current);

            if ($parent === $current) {
                return null;
            }

            $current = $parent;
        }
    }

    private static function lastError(): string
    {
        $error = error_get_last();

        if ($error === null) {
            return 'unknown error';
        }

        $position = strrpos($error['message'], ': ');

        return $position === false ? $error['message'] : substr($error['message'], $position + 2);
    }
}
