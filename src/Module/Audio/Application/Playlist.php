<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Application;

use Closure;

/**
 * Lista utworów wraz z kursorem „co gra teraz” (krok 45).
 *
 * Klasa jest **danymi i regułami kolejności**, i niczym więcej: nie wie, czym
 * jest plik, nie czyta dysku i nie zna silnika audio. Odtwarzaniem zajmuje się
 * `PlaylistPlayer`, trwałością — `PlaylistPort`. Dzięki temu cała arytmetyka
 * „co jest następne” daje się sprawdzić testem bez ani jednego dźwięku.
 *
 * Mutowalna w miejscu, wzorem `Application\Command\CommandHistory`: playlista
 * jest jedna na uruchomienie, a jej zmiany są zdarzeniami użytkownika, nie
 * przeliczeniem widoku.
 *
 * **Kursor grania to nie kursor listy.** W oknie modułu chodzi się po pozycjach
 * strzałkami, a gra ta, którą wskazano `Enter`em — i jedno nie ma prawa gonić
 * drugiego (D82, rozstrzygnięcie 4). Kursor listy należy do ekranu, ten kursor
 * mieszka tutaj, bo przeżywa zamknięcie okna.
 *
 * Pozycja wskazująca plik, którego nie ma, **zostaje** (D82, rozstrzygnięcie 6):
 * wypada wyłącznie z wyboru „co grać dalej”, a odpięty nośnik nie kasuje
 * playlisty.
 */
final class Playlist
{
    /** @var list<PlaylistEntry> */
    private array $entries;

    /** Numer pozycji granej albo `null`, gdy nie gra żadna. */
    private ?int $playing = null;

    /** @param list<PlaylistEntry> $entries */
    public function __construct(array $entries = [])
    {
        $this->entries = $entries;
    }

    /** @return list<PlaylistEntry> */
    public function entries(): array
    {
        return $this->entries;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    public function at(int $index): ?PlaylistEntry
    {
        return $this->entries[$index] ?? null;
    }

    public function playing(): ?int
    {
        return $this->playing;
    }

    /** Numer spoza listy znaczy „nie gra nic” — kursor nie ma prawa wskazywać pustki. */
    public function usePlaying(?int $index): void
    {
        $this->playing = $index !== null && isset($this->entries[$index]) ? $index : null;
    }

    /**
     * Dopisuje na koniec i oddaje numer nowej pozycji.
     *
     * Powtórzenia są dozwolone: ten sam utwór dwa razy na playliście jest
     * wyborem, a nie pomyłką, i żaden odtwarzacz go nie zabrania.
     */
    public function add(PlaylistEntry $entry): int
    {
        $this->entries[] = $entry;

        return count($this->entries) - 1;
    }

    public function removeAt(int $index): bool
    {
        if (!isset($this->entries[$index])) {
            return false;
        }

        array_splice($this->entries, $index, 1);

        if ($this->playing === $index) {
            // Utwór gra dalej — silnika to nie dotyczy — ale playlista przestaje
            // go wskazywać, więc następny weźmie się od początku listy.
            $this->playing = null;
        } elseif ($this->playing !== null && $this->playing > $index) {
            --$this->playing;
        }

        return true;
    }

    /**
     * Zamienia pozycję z sąsiadem i oddaje jej nowy numer (`Shift`+strzałki).
     *
     * Zamiana, a nie wyjęcie i wstawienie: krok jest zawsze o jedno miejsce, więc
     * jedno i drugie daje ten sam wynik, a zamiana nie każe się zastanawiać, co
     * się dzieje z pozycjami pomiędzy. Kursor grania wędruje **razem z pozycją**,
     * bo wskazuje utwór, a nie miejsce w liście.
     */
    public function swap(int $index, int $direction): int
    {
        $target = $index + ($direction < 0 ? -1 : 1);

        if (!isset($this->entries[$index], $this->entries[$target])) {
            return $index;
        }

        $entries = $this->entries;
        $carried = $entries[$index];
        $entries[$index] = $entries[$target];
        $entries[$target] = $carried;
        $this->entries = array_values($entries);

        if ($this->playing === $index) {
            $this->playing = $target;
        } elseif ($this->playing === $target) {
            $this->playing = $index;
        }

        return $target;
    }

    /**
     * Przelicza dostępność plików podanym sprawdzeniem.
     *
     * Sprawdzenie przychodzi z zewnątrz, bo dysku ta warstwa nie zna. Wołane jest
     * przy wczytaniu, przy dopisaniu pozycji i przy otwarciu okna modułu —
     * **nigdy w takcie**, bo takt nie ma prawa dotknąć wejścia-wyjścia.
     *
     * @param Closure(string): bool $exists
     */
    public function refresh(Closure $exists): void
    {
        $refreshed = [];

        foreach ($this->entries as $entry) {
            $refreshed[] = $entry->withMissing(!$exists($entry->path));
        }

        $this->entries = $refreshed;
    }

    /** Czy pozycję da się dziś zagrać — istnieje i plik jest osiągalny. */
    public function isPlayable(int $index): bool
    {
        $entry = $this->entries[$index] ?? null;

        return $entry !== null && !$entry->missing;
    }

    /** Pierwsza pozycja, którą da się zagrać — punkt startu autostartu. */
    public function firstPlayable(): ?int
    {
        foreach ($this->entries as $index => $entry) {
            if (!$entry->missing) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Co zagrać po pozycji `$index` — albo `null`, gdy ma zapaść cisza.
     *
     * Tryb rozstrzyga wszystko: powtarzanie utworu oddaje ten sam numer (choć
     * w praktyce nie zostanie użyty, bo zapętla silnik), zatrzymanie po utworze
     * oddaje `null`, a pętla listy szuka **najbliższej grywalnej** pozycji
     * w przód, z zawinięciem — pomijając te, których pliku nie ma.
     *
     * `null` jako `$index` znaczy „zacznij od początku listy”.
     */
    public function nextAfter(?int $index, PlaybackMode $mode): ?int
    {
        if ($this->entries === []) {
            return null;
        }

        if ($mode->repeatsInEngine()) {
            return $index !== null && $this->isPlayable($index) ? $index : null;
        }

        if (!$mode->continuesToNext()) {
            return null;
        }

        $count = count($this->entries);
        $from = $index ?? -1;

        for ($step = 1; $step <= $count; ++$step) {
            $candidate = ($from + $step + $count) % $count;

            if ($this->isPlayable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
