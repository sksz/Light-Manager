<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Infrastructure;

use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\Port\TrackFilesPort;

/**
 * Pliki utworów na dysku: czy są i co można dopisać do ścieżki (krok 45).
 *
 * Usługa jest **jedynym miejscem, w którym mieszka reguła ścieżki względnej**:
 * liczy się ona od korzenia projektu, bo tam leży katalog `assets/`
 * z utworem domyślnym. Do kroku 45 ta sama reguła stała prywatnie
 * w `GlAudioService`; dwaj użytkownicy znaczą jedno miejsce, bo rozjechana
 * dawałaby playlistę, która pokazuje pozycję jako obecną, a zagrać jej nie umie.
 *
 * Podpowiedzi przeglądają katalog **sam moduł**, kilkoma linijkami, zamiast
 * sięgać do repozytorium wpisów przeglądarki: moduły się nie znają (reguła 15),
 * a powtórzenie jest tu kilkunastu linii bez skutków ubocznych — tą samą miarą,
 * którą krok 41 dopuścił powtórzony `permissionsAsText()`.
 */
final class TrackFileService extends AbstractSingleton implements TrackFilesPort
{
    public function exists(string $path): bool
    {
        $resolved = self::resolved($path);

        return is_file($resolved) && is_readable($resolved);
    }

    public function suggestions(string $prefix): array
    {
        $separator = strrpos($prefix, '/');
        $head = $separator === false ? '' : substr($prefix, 0, $separator + 1);
        $needle = $separator === false ? $prefix : substr($prefix, $separator + 1);

        $directory = self::resolved($head === '' ? '.' : $head);
        $names = @scandir($directory);

        if ($names === false) {
            return [];
        }

        $values = [];

        foreach ($names as $name) {
            if ($name === '.' || $name === '..' || !self::matches($name, $needle)) {
                continue;
            }

            $full = rtrim($directory, '/') . '/' . $name;

            if (is_dir($full)) {
                // Ukośnik na końcu pozwala uzupełniać dalej, w głąb — bez niego
                // `Tab` zatrzymywałby się na każdym poziomie (jak w `browser.jump`).
                $values[] = $head . $name . '/';

                continue;
            }

            if (self::isTrack($name)) {
                $values[] = $head . $name;
            }
        }

        return $values;
    }

    /**
     * Ścieżka względna liczy się od korzenia projektu, bezwzględna zostaje
     * nietknięta — to druga jest drogą do własnego pliku użytkownika.
     */
    public static function resolved(string $path): string
    {
        return str_starts_with($path, '/') ? $path : dirname(__DIR__, 4) . '/' . $path;
    }

    /** Wpis ukryty pokazuje się dopiero temu, kto zaczął go pisać kropką. */
    private static function matches(string $name, string $needle): bool
    {
        if (str_starts_with($name, '.') && !str_starts_with($needle, '.')) {
            return false;
        }

        return $needle === '' || str_starts_with($name, $needle);
    }

    private static function isTrack(string $name): bool
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return in_array($extension, AudioSettings::TRACK_EXTENSIONS, true);
    }
}
