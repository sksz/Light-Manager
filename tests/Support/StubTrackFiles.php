<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\Audio\Application\Port\TrackFilesPort;

/**
 * Pliki utworów bez dysku: istnieje to, co powiedziano, że istnieje (krok 45).
 *
 * Odpowiedź domyślna brzmi „jest”, bo w większości testów playlista ma po prostu
 * działać; testy pozycji brakującej podają jawną listę nieobecnych. Dzięki temu
 * reguła „pozycja bez pliku zostaje, ale wypada z wyboru” daje się sprawdzić bez
 * zakładania i kasowania plików tymczasowych.
 */
final class StubTrackFiles implements TrackFilesPort
{
    /**
     * @param list<string> $missing ścieżki, których „nie ma”
     * @param list<string> $hints   podpowiedzi oddawane niezależnie od przedrostka
     */
    public function __construct(
        private readonly array $missing = [],
        private readonly array $hints = [],
    ) {
    }

    public function exists(string $path): bool
    {
        return !in_array($path, $this->missing, true);
    }

    public function suggestions(string $prefix): array
    {
        $matching = [];

        foreach ($this->hints as $hint) {
            if ($prefix === '' || str_starts_with($hint, $prefix)) {
                $matching[] = $hint;
            }
        }

        return $matching;
    }
}
