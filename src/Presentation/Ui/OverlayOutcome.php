<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use LightManager\Domain\ValueObject\Message;

/**
 * Skutek klawisza obsłużonego przez okno nakładane — bliźniak `ScreenOutcome`.
 *
 * Różnica jest jedna i wynika z tego, czym okno jest: ekran zawsze klawisz
 * przyjmuje, a okno nakładane może go **przepuścić niżej** (`ignored`). Bez tego
 * `F10` nie kończyłby aplikacji przy otwartym oknie komend, a `F1` nie otwierał
 * pomocy — okno modalne przejmuje klawisze, ale klawisze globalne nie należą do
 * żadnego okna.
 */
final class OverlayOutcome
{
    private function __construct(
        public readonly bool $handled,
        public readonly bool $closes,
        public readonly ?Message $message = null,
        public readonly ?string $screenId = null,
        public readonly bool $quits = false,
    ) {
    }

    /** Okno zużyło klawisz i zostaje otwarte. */
    public static function stay(?Message $message = null): self
    {
        return new self(true, false, $message);
    }

    /** Okno zużyło klawisz i się zamyka. */
    public static function close(?Message $message = null): self
    {
        return new self(true, true, $message);
    }

    /** Okno się zamyka, a rdzeń ma otworzyć wskazany ekran. */
    public static function opens(string $screenId, ?Message $message = null): self
    {
        return new self(true, true, $message, $screenId);
    }

    public static function quit(): self
    {
        return new self(true, true, null, null, true);
    }

    /** Klawisz do okna nie należy — niech idzie wyżej. */
    public static function ignored(): self
    {
        return new self(false, false);
    }
}
