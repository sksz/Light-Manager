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
 *
 * Od kroku 41 skutek niesie ponadto **następne okno** (`replace()`) i nie jest to
 * wygoda: usuwanie katalogu prowadzi przez trzy okna po kolei (liczenie, pytanie
 * z liczbą, usuwanie), a stos okien ma **jedno piętro** (`OverlayStack`). Wskazanie
 * jest obiektem, a nie identyfikatorem — inaczej niż przy ekranach, bo tam
 * kontrakt komendy leży w `Application` i obiektu widzieć nie może (D39); tutaj
 * wskazuje okno oknu, więc obie strony leżą w tej samej warstwie.
 */
final class OverlayOutcome
{
    private function __construct(
        public readonly bool $handled,
        public readonly bool $closes,
        public readonly ?Message $message = null,
        public readonly ?string $screenId = null,
        public readonly bool $quits = false,
        public readonly ?OverlayInterface $next = null,
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

    /**
     * Okno ustępuje miejsca innemu oknu — jedna droga, dwa kroki, bo stos ma
     * jedno piętro i „zamknij, a potem otwórz” musi się stać naraz.
     */
    public static function replace(OverlayInterface $next, ?Message $message = null): self
    {
        return new self(true, true, $message, null, false, $next);
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
