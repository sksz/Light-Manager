<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application;

use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;

/**
 * Spis hostów, które użytkownik prowadzi z ekranu modułu (krok 48).
 *
 * Kolekcja **mutowalna w miejscu**, wzorem `Playlist` z kroku 45 i z tego samego
 * powodu: jest własnością modułu, a nie wartością przekazywaną między warstwami,
 * więc kopiowanie jej przy każdym dopisaniu byłoby ceremonią bez odbiorcy.
 *
 * **Tożsamością wpisu jest nazwa własna** (`HostProfile::equals()`), więc spis
 * zachowuje się jak mapa, a nie jak lista: dopisanie pod nazwą już zajętą
 * **zastępuje** wpis, zamiast dokładać drugi. Inaczej `ssh.connect biuro`
 * musiałoby rozstrzygać, o który z dwóch „biur" chodzi — a nie ma jak.
 *
 * Kolejność jest kolejnością dopisywania i przeżywa zapis, bo to ona rządzi
 * spisem na ekranie; sortowania nie ma i nie powinno być, dopóki nikt o nie nie
 * poprosi (reguła 13).
 */
final class HostBook
{
    /** @var list<HostProfile> */
    private array $profiles;

    /** @param list<HostProfile> $profiles */
    public function __construct(array $profiles = [])
    {
        $this->profiles = [];

        foreach ($profiles as $profile) {
            $this->add($profile);
        }
    }

    /** @return list<HostProfile> */
    public function all(): array
    {
        return $this->profiles;
    }

    public function count(): int
    {
        return count($this->profiles);
    }

    public function isEmpty(): bool
    {
        return $this->profiles === [];
    }

    public function find(string $name): ?HostProfile
    {
        foreach ($this->profiles as $profile) {
            if ($profile->name === $name) {
                return $profile;
            }
        }

        return null;
    }

    public function at(int $index): ?HostProfile
    {
        return $this->profiles[$index] ?? null;
    }

    /**
     * Dopisuje albo **zastępuje** wpis o tej samej nazwie, zachowując jego miejsce.
     *
     * Spis powstaje od nowa, zamiast podmiany pod indeksem, i nie jest to
     * ozdoba: podstawienie `$this->profiles[$index]` gubi gwarancję, że tablica
     * jest listą o kolejnych kluczach, a od niej zależy `at()` — czyli wskazanie
     * wiersza kursorem.
     */
    public function add(HostProfile $profile): void
    {
        $profiles = [];
        $replaced = false;

        foreach ($this->profiles as $existing) {
            if ($existing->equals($profile)) {
                $profiles[] = $profile;
                $replaced = true;

                continue;
            }

            $profiles[] = $existing;
        }

        if (!$replaced) {
            $profiles[] = $profile;
        }

        $this->profiles = $profiles;
    }

    /** Usuwa wpis; spis powstaje od nowa z tego samego powodu, co w `add()`. */
    public function remove(string $name): bool
    {
        $profiles = [];
        $removed = false;

        foreach ($this->profiles as $profile) {
            if ($profile->name === $name) {
                $removed = true;

                continue;
            }

            $profiles[] = $profile;
        }

        $this->profiles = $profiles;

        return $removed;
    }

    /** @return list<string> nazwy własne — materiał na podpowiedzi argumentów komendy */
    public function names(): array
    {
        return array_map(static fn (HostProfile $profile): string => $profile->name, $this->profiles);
    }
}
