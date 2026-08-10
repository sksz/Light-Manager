<?php

declare(strict_types=1);

namespace LightManager\Application\Command;

use LightManager\Domain\ValueObject\Message;

/**
 * Skutek komendy: co z oknem, co powiedzieć i czy otworzyć ekran.
 *
 * Ekran wskazywany jest **identyfikatorem**, a nie obiektem, bo `ScreenInterface`
 * mieszka w `Presentation`, a kontrakt komendy w `Application` — sięgnięcie po
 * niego byłoby strzałką na zewnątrz. Napis tłumaczy na obiekt jedno miejsce
 * w warstwie dostarczania, a identyfikator bez pokrycia jest błędem wykrywalnym
 * testem (D39, P24).
 */
final class CommandOutcome
{
    private function __construct(
        public readonly CommandTransition $transition,
        public readonly ?Message $message = null,
        public readonly ?string $screenId = null,
    ) {
    }

    /** Komenda zrobiła swoje — okno się zamyka. */
    public static function done(?Message $message = null): self
    {
        return new self(CommandTransition::Close, $message);
    }

    /** Okno zostaje otwarte wraz z wpisanym wierszem — jest co poprawić. */
    public static function stay(?Message $message = null): self
    {
        return new self(CommandTransition::Stay, $message);
    }

    /** Okno się zamyka, a na wierzchu staje wskazany ekran. */
    public static function opens(string $screenId, ?Message $message = null): self
    {
        return new self(CommandTransition::Close, $message, $screenId);
    }

    public static function quit(): self
    {
        return new self(CommandTransition::Quit);
    }
}
