<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Application;

use LightManager\Module\Audio\Domain\Exception\InvalidTrackException;

/**
 * Co gra przy jednym zdarzeniu: plik, przełącznik i to, czy plik dziś jest
 * (krok 46).
 *
 * Przełącznik siedzi **przy przypisaniu, a nie w ustawieniach**, i to jest
 * rozstrzygnięcie użytkownika ze startu kroku. Powód, dla którego w ogóle da się
 * to zrobić tanio: mapa i tak trzyma po jednym wierszu na zdarzenie, więc
 * wartość logiczna obok ścieżki nie kosztuje ani jednej struktury więcej —
 * a pozycja w zakładce ustawień musiałaby powstać dla **każdego** zdarzenia
 * z osobna i rosłaby razem ze słownikiem.
 *
 * Wyciszenie jest przez to czym innym niż wyczyszczenie: `F8` zabiera plik,
 * spacja go zostawia i milknie. Różnica ma znaczenie dokładnie wtedy, gdy
 * użytkownik dobierał ścieżkę dłużej niż chwilę.
 *
 * `missing` jest **zapamiętaną odpowiedzią**, tą samą co w `PlaylistEntry`:
 * pytanie dysku o istnienie pliku jest wejściem-wyjściem, a zdarzenia padają
 * w środku klatki.
 */
final class EffectAssignment
{
    public function __construct(
        /** Ścieżka bezwzględna albo względna wobec korzenia projektu. */
        public readonly string $path,
        public readonly bool $enabled = true,
        /** Czy pliku nie było przy ostatnim sprawdzeniu. */
        public readonly bool $missing = false,
    ) {
        if (trim($path) === '') {
            throw InvalidTrackException::emptyPath();
        }
    }

    public function withMissing(bool $missing): self
    {
        return $missing === $this->missing ? $this : new self($this->path, $this->enabled, $missing);
    }

    public function toggled(): self
    {
        return new self($this->path, !$this->enabled, $this->missing);
    }

    /** Czy to przypisanie ma prawo teraz zagrać. */
    public function playable(): bool
    {
        return $this->enabled && !$this->missing;
    }

    public function equals(self $other): bool
    {
        return $this->path === $other->path
            && $this->enabled === $other->enabled
            && $this->missing === $other->missing;
    }
}
