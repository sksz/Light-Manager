<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

final class KeyPress
{
    /**
     * @param bool $ctrl czy znak przyszedł z wciśniętym klawiszem `Ctrl`
     * @param bool $alt  czy znak przyszedł z wciśniętym klawiszem `Alt`
     */
    public function __construct(
        public readonly Key $key,
        public readonly string $raw,
        public readonly bool $ctrl = false,
        public readonly bool $alt = false,
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

    /**
     * Znak wciśnięty wraz z `Alt` — druga para modyfikatora, dopisana w kroku 29.
     *
     * Powód jej powstania jest jeden i konkretny: przełączanie zawijania wierszy
     * w podglądzie tekstu ma wisieć na `Alt`+`z`, jak w edytorach. Słownik zna
     * odtąd dwa modyfikatory i **nie zna ich kombinacji** — `Ctrl`+`Alt`+litera
     * nie ma w aplikacji ani jednego użytkownika, a para znaczników, z których
     * oba bywają prawdziwe naraz, wymagałaby rozstrzygnięcia w każdym miejscu
     * porównującym naciśnięcia. Gdy taki użytkownik się pojawi, wraca to jako
     * osobna decyzja.
     */
    public static function alt(string $letter): self
    {
        return new self(Key::Character, $letter, false, true);
    }

    public static function special(Key $key, string $raw): self
    {
        return new self($key, $raw);
    }

    public function equals(self $other): bool
    {
        return $this->key === $other->key
            && $this->raw === $other->raw
            && $this->ctrl === $other->ctrl
            && $this->alt === $other->alt;
    }
}
