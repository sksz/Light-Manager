<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use LightManager\Domain\ValueObject\Message;

/**
 * Skutek klawisza obsłużonego przez ekran: co ma się stać z samym ekranem, co
 * powiedzieć w pasku stanu i czy położyć coś na wierzchu.
 *
 * Trzy przejścia wystarczają, bo tyle ich w aplikacji istnieje: ekran zostaje,
 * ekran się zamyka (powrót do przeglądarki) albo aplikacja kończy pracę.
 * Otwieranie innego ekranu należy do rdzenia i wisi na klawiszach globalnych —
 * ekran, który mógłby otworzyć dowolny inny, musiałby je wszystkie znać.
 */
final class ScreenOutcome
{
    private function __construct(
        public readonly Transition $transition,
        public readonly ?Message $message = null,
        public readonly ?OverlayInterface $overlay = null,
    ) {
    }

    public static function stay(?Message $message = null): self
    {
        return new self(Transition::Stay, $message);
    }

    public static function close(?Message $message = null): self
    {
        return new self(Transition::Close, $message);
    }

    public static function quit(): self
    {
        return new self(Transition::Quit);
    }

    /** Ekran zostaje, a nad nim staje okno nakładane. */
    public static function opens(OverlayInterface $overlay): self
    {
        return new self(Transition::Stay, null, $overlay);
    }
}
