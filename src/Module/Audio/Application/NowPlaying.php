<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Application;

/**
 * Co gra w tej chwili — odpowiedź kwerendy `audio.now-playing` (krok 53).
 *
 * Osobna dana, a nie pięć pytań do odtwarzacza, i to jest różnica, którą wnosi
 * jedyna droga odczytu: do tego kroku okno modułu pytało `PlaylistPlayer`
 * o pozycję, o granie, o tryb i o dostępność silnika **osobno**, więc każde
 * z tych pytań mogło odpowiedzieć z innej chwili. Tutaj odpowiedź jest jedna
 * i spójna z sobą.
 */
final readonly class NowPlaying
{
    public function __construct(
        /** Pozycja grana albo `null`, gdy playlista nie prowadzi żadnej. */
        public ?PlaylistEntry $entry,
        /** Numer tej pozycji na liście; `null` razem z pozycją. */
        public ?int $index,
        public bool $playing,
        public PlaybackMode $mode,
        /** Czy jest czym zagrać — silnik bywa nieobecny (`ext-glfw`). */
        public bool $available,
        /** Zdanie o kłopocie z plikiem playlisty albo `null`. */
        public ?string $problem,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->index === $other->index
            && $this->playing === $other->playing
            && $this->mode === $other->mode
            && $this->available === $other->available
            && $this->problem === $other->problem;
    }
}
