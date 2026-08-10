<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Imagick;

use Imagick;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Odpowiada na pytania o możliwości lokalnej instalacji ImageMagick.
 *
 * Koder Sixel bywa pomijany przy kompilacji ImageMagick, więc obecność
 * rozszerzenia `imagick` nie wystarcza — trzeba zapytać o konkretny format.
 */
final class ImagickCapabilityService extends AbstractSingleton
{
    private const SIXEL_FORMAT = 'SIXEL';

    /** Kolejność preferencji — pierwszy obecny w systemie wygrywa. */
    private const PREFERRED_MONOSPACE_FONTS = [
        'DejaVu-Sans-Mono',
        'Liberation-Mono',
        'Nimbus-Mono-PS',
        'FreeMono',
        'Courier',
    ];

    private readonly bool $sixelCoderAvailable;

    private readonly ?string $monospaceFont;

    protected function __construct()
    {
        parent::__construct();

        // Brak rozszerzenia to nie wyjątek, tylko odpowiedź „nie” — pytanie
        // dotyczy możliwości środowiska. Twardy wymóg `ext-imagick` pilnuje
        // punkt wejścia w `bin/`.
        $available = extension_loaded('imagick');

        $this->sixelCoderAvailable = $available
            && in_array(self::SIXEL_FORMAT, Imagick::queryFormats(self::SIXEL_FORMAT), true);

        $this->monospaceFont = $available ? $this->resolveMonospaceFont() : null;
    }

    public function supportsSixelCoder(): bool
    {
        return $this->sixelCoderAvailable;
    }

    /**
     * `null` oznacza, że nie znaleziono żadnego fontu o stałej szerokości —
     * renderer użyje wtedy domyślnego fontu ImageMagick, co jest brzydsze, ale
     * nadal działa.
     */
    public function monospaceFont(): ?string
    {
        return $this->monospaceFont;
    }

    private function resolveMonospaceFont(): ?string
    {
        $installed = Imagick::queryFonts();

        foreach (self::PREFERRED_MONOSPACE_FONTS as $font) {
            if (in_array($font, $installed, true)) {
                return $font;
            }
        }

        // Żadna z preferencji nie jest zainstalowana — bierzemy pierwszy font,
        // który sam deklaruje się jako Mono, byle nie odmianę pochyłą/grubą.
        foreach ($installed as $font) {
            if (preg_match('/Mono$/i', $font) === 1) {
                return $font;
            }
        }

        return null;
    }
}
