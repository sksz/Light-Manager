<?php

declare(strict_types=1);

namespace LightManager\Domain\ValueObject;

/**
 * Waga komunikatu w pasku stanu.
 *
 * Ton niesie znak wiodący, bo sam kolor nie wystarcza: po kwantyzacji Sixela
 * odcienie bywają zbliżone, a terminal bez 256 kolorów zaokrągla je jeszcze
 * mocniej. Znak działa zawsze.
 */
enum MessageTone
{
    case Info;
    case Warning;
    case Error;

    public function marker(): string
    {
        return match ($this) {
            self::Info => '·',
            self::Warning => '!',
            self::Error => '×',
        };
    }
}
