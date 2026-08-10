<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

final class KeyPress
{
    /**
     * @param bool $ctrl czy znak przyszedł z wciśniętym klawiszem `Ctrl`
     */
    public function __construct(
        public readonly Key $key,
        public readonly string $raw,
        public readonly bool $ctrl = false,
    ) {
    }

    public static function character(string $character): self
    {
        return new self(Key::Character, $character);
    }

    /**
     * Znak wciśnięty wraz z `Ctrl`.
     *
     * `raw` niesie **samą literę**, a nie bajt sterujący, którym przyszła:
     * `Ctrl+D` to `0x04`, ale ani spis w pomocy, ani deklaracja skrótu nie mają
     * powodu operować na bajcie. Odwzorowanie bajtu na literę należy do parsera
     * i tylko do niego (krok 19).
     */
    public static function ctrl(string $letter): self
    {
        return new self(Key::Character, $letter, true);
    }

    public static function special(Key $key, string $raw): self
    {
        return new self($key, $raw);
    }

    public function equals(self $other): bool
    {
        return $this->key === $other->key
            && $this->raw === $other->raw
            && $this->ctrl === $other->ctrl;
    }
}
