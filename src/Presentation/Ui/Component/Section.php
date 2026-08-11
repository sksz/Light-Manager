<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Role;

/**
 * Jedna sekcja listy: etykieta, wiersze pod nią i to, czy jest zwinięta.
 *
 * Sekcja jest **daną, nie komponentem** — dokładnie tak samo, jak `ListRow` jest
 * daną, a rysuje ją `ListView`. Powód jest ten sam i nie jest wygodą: sekcje
 * przewija się **jak jedną listę**, więc wycinanie okna musi widzieć wiersze
 * wszystkich sekcji naraz. Sekcja rysująca się samodzielnie znałaby tylko swój
 * prostokąt i nie umiałaby powiedzieć, że zaczyna się trzy wiersze nad górną
 * krawędzią okna. Rysuje więc `SectionList`, a sekcja mówi tylko, co ma pokazać
 * i ile miejsca to zajmie.
 *
 * `key` jest **napisem, a nie numerem**, i to jest zabezpieczenie, a nie
 * ozdoba: stan zwinięcia trzyma `SectionState` pod tym kluczem, a sekcja, która
 * zniknęła z listy i wróciła, ma wrócić w tym samym stanie. Numer po zmianie
 * listy wskazywałby na inną.
 */
final class Section
{
    /** Sekcja rozwinięta — trójkąt w dół, jak w każdym interfejsie z drzewem. */
    public const OPEN = '▼';

    /** Sekcja zwinięta — trójkąt w prawo, czyli „jest tu coś dalej”. */
    public const CLOSED = '▶';

    /** @param list<ListRow> $rows */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $rows,
        public readonly bool $collapsed = false,
    ) {
    }

    /**
     * Naturalna wysokość sekcji w wierszach.
     *
     * Metoda własna, a nie `measure()` w `ComponentInterface`: kontrakt
     * komponentu ma jedną metodę i ma ją zachować (krok 18), a wysokość znaną
     * z góry wystawia ten, kto ją zna — tak samo robi przycisk i okno dialogowe.
     */
    public function height(): int
    {
        return $this->collapsed ? 1 : count($this->rows) + 2;
    }

    /**
     * Wiersze sekcji: nagłówek ze znacznikiem, treść i odstęp.
     *
     * Odstęp stoi **za** treścią, a nie przed nagłówkiem, i to ma znaczenie przy
     * zwijaniu: sekcje zwinięte układają się wtedy jedna pod drugą bez przerw,
     * czyli wyglądają jak spis — a o to w zwijaniu chodzi.
     *
     * @return list<ListRow>
     */
    public function lines(): array
    {
        $lines = [new ListRow(($this->collapsed ? self::CLOSED : self::OPEN) . ' ' . $this->label, '', Role::Accent)];

        if ($this->collapsed) {
            return $lines;
        }

        foreach ($this->rows as $row) {
            $lines[] = $row;
        }

        $lines[] = new ListRow('', '', Role::Muted);

        return $lines;
    }
}
