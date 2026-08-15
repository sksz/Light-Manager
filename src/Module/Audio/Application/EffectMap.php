<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Application;

use Closure;

/**
 * Przypisania „zdarzenie → plik" (krok 46).
 *
 * Mapa trzyma **wyłącznie to, co ktoś przypisał**, a nie wiersz na każde
 * zdarzenie słownika, i ta asymetria jest celowa: słownik należy do rdzenia
 * i modułów, które go wnoszą, więc mapa pamiętająca wszystkie jego pozycje
 * musiałaby wiedzieć, kiedy któraś zniknęła. Spis w oknie składa się odwrotnie —
 * ze słownika, z zajrzeniem tutaj po każdą pozycję — więc zdarzenie usunięte
 * z któregoś modułu po prostu przestaje się pokazywać, a jego dawne przypisanie
 * leży w pliku nietknięte i milczy.
 *
 * Klasa jest mutowalna w miejscu, jak `Playlist`: jest stanem modułu między
 * klatkami, a nie obiektem wartości.
 */
final class EffectMap
{
    /** @param array<string, EffectAssignment> $assignments klucz = nazwa zdarzenia */
    public function __construct(
        private array $assignments = [],
    ) {
    }

    public function at(string $event): ?EffectAssignment
    {
        return $this->assignments[$event] ?? null;
    }

    /**
     * Przypisuje plik zdarzeniu.
     *
     * Przełącznik **przeżywa podmianę ścieżki**: użytkownik, który wyciszył
     * zdarzenie i podmienił mu plik, nie prosił o włączenie go z powrotem.
     */
    public function assign(string $event, string $path, bool $missing = false): void
    {
        $previous = $this->assignments[$event] ?? null;

        $this->assignments[$event] = new EffectAssignment(
            $path,
            $previous === null || $previous->enabled,
            $missing,
        );
    }

    public function clear(string $event): bool
    {
        if (!isset($this->assignments[$event])) {
            return false;
        }

        unset($this->assignments[$event]);

        return true;
    }

    /** Włącza albo wycisza przypisanie; `false`, gdy nie ma czego przełączyć. */
    public function toggle(string $event): bool
    {
        $assignment = $this->assignments[$event] ?? null;

        if ($assignment === null) {
            return false;
        }

        $this->assignments[$event] = $assignment->toggled();

        return true;
    }

    /**
     * Ponowne sprawdzenie, których plików nie ma — przy wczytaniu i przy otwarciu
     * okna modułu, nigdy w takcie.
     *
     * @param Closure(string): bool $exists
     */
    public function refresh(Closure $exists): void
    {
        foreach ($this->assignments as $event => $assignment) {
            $this->assignments[$event] = $assignment->withMissing(!$exists($assignment->path));
        }
    }

    public function isEmpty(): bool
    {
        return $this->assignments === [];
    }

    /** @return array<string, EffectAssignment> */
    public function all(): array
    {
        return $this->assignments;
    }
}
