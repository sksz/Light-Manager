<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Application;

use LightManager\Module\Audio\Domain\Exception\InvalidTrackException;

/**
 * Jedna pozycja playlisty: ścieżka, nazwa do pokazania i to, czy plik dziś jest
 * (krok 45).
 *
 * Nazwa jedzie **obok** ścieżki, a nie liczy się z niej przy każdym rysowaniu.
 * Powód jest ten sam, dla którego historia komend trzyma całe wiersze: pozycja
 * ma prawo nazywać się inaczej niż jej plik, a krok 46 albo późniejszy czytnik
 * znaczników utworu ma gdzie tę nazwę zapisać. Domyślnie bierze się z nazwy
 * pliku bez rozszerzenia, bo tak podpisuje utwory każdy odtwarzacz.
 *
 * `missing` jest **zapamiętaną odpowiedzią**, a nie pytaniem zadawanym przy
 * rysowaniu: sprawdzenie istnienia pliku to wejście-wyjście, a klatka idzie
 * trzydzieści razy na sekundę. Odświeża je `Playlist::refreshed()` — przy
 * wczytaniu, przy dopisaniu pozycji i przy otwarciu okna modułu.
 */
final class PlaylistEntry
{
    public function __construct(
        /** Ścieżka bezwzględna albo względna wobec korzenia projektu. */
        public readonly string $path,
        public readonly string $name,
        /** Czy plik był nieosiągalny przy ostatnim sprawdzeniu. */
        public readonly bool $missing = false,
    ) {
        if (trim($path) === '') {
            throw InvalidTrackException::emptyPath();
        }
    }

    /** Pozycja z samej ścieżki — nazwa z pliku, bez rozszerzenia. */
    public static function of(string $path, bool $missing = false): self
    {
        $path = trim($path);

        if ($path === '') {
            throw InvalidTrackException::emptyPath();
        }

        $name = pathinfo($path, PATHINFO_FILENAME);

        return new self($path, $name === '' ? basename($path) : $name, $missing);
    }

    public function withMissing(bool $missing): self
    {
        return $missing === $this->missing ? $this : new self($this->path, $this->name, $missing);
    }

    /** Tożsamością pozycji jest ścieżka — nazwa bywa dwa razy ta sama. */
    public function equals(self $other): bool
    {
        return $this->path === $other->path;
    }
}
