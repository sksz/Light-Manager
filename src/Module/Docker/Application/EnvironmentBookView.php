<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/**
 * Migawka spisu środowisk dla ekranu i kwerendy (krok 58).
 *
 * Migawka, a nie obiekt roboczy — jak w pozostałych fasadach modułowych (D92
 * nr 3): ma postać pustą, więc pytający pisze jedną linię zamiast obsługi
 * `null`a w każdym miejscu odczytu.
 */
final readonly class EnvironmentBookView
{
    /** @param list<EnvironmentRow> $rows */
    public function __construct(
        public array $rows,
        public string $current,
        public string $location,
        public TunnelState $tunnel,
        public bool $reading,
        public ?string $problemKey,
    ) {
    }

    public static function empty(): self
    {
        return new self([], EnvironmentBook::DEFAULT_NAME, '', TunnelState::none(), false, null);
    }

    public function at(int $index): ?EnvironmentRow
    {
        return $this->rows[$index] ?? null;
    }

    public function count(): int
    {
        return count($this->rows);
    }
}
