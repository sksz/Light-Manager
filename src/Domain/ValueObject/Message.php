<?php

declare(strict_types=1);

namespace LightManager\Domain\ValueObject;

use LightManager\Domain\Exception\InvalidMessageException;

/**
 * Treść paska stanu wraz z jej wagą.
 *
 * Do kroku 13 komunikat był gołym stringiem rysowanym zawsze na czerwono, więc
 * „skopiowano” wyglądało jak awaria. Ton rozdziela te przypadki, a renderer
 * tłumaczy go na kolor i znak wiodący.
 */
final class Message
{
    private function __construct(
        public readonly string $text,
        public readonly MessageTone $tone,
    ) {
        if (trim($text) === '') {
            throw InvalidMessageException::forEmptyText();
        }
    }

    public static function info(string $text): self
    {
        return new self($text, MessageTone::Info);
    }

    public static function warning(string $text): self
    {
        return new self($text, MessageTone::Warning);
    }

    public static function error(string $text): self
    {
        return new self($text, MessageTone::Error);
    }

    /** Treść poprzedzona znakiem tonu — postać gotowa do narysowania. */
    public function marked(): string
    {
        return $this->tone->marker() . ' ' . $this->text;
    }

    public function equals(self $other): bool
    {
        return $this->text === $other->text && $this->tone === $other->tone;
    }
}
