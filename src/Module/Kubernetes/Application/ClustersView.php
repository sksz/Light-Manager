<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

/**
 * Migawka spisu klastrów dla ekranu i kwerendy `k8s.clusters` (krok 59).
 *
 * Migawka, a nie obiekt roboczy — jak w pozostałych fasadach modułowych (D92
 * nr 3): ma postać pustą, więc pytający pisze jedną linię zamiast obsługi
 * `null`a w każdym miejscu odczytu.
 */
final readonly class ClustersView
{
    /** @param list<ClusterRow> $rows */
    public function __construct(
        public array $rows,
        /** Nazwa wiersza bieżącego; pusty napis — jeszcze nie wybrano. */
        public string $current,
        /** Gdzie leży dokument stanu z książką — ekran pokazuje to w górnym pasie. */
        public string $location,
        public bool $reading,
        public ?string $problemKey,
    ) {
    }

    public static function empty(): self
    {
        return new self([], '', '', false, null);
    }

    public function at(int $index): ?ClusterRow
    {
        return $this->rows[$index] ?? null;
    }

    public function count(): int
    {
        return count($this->rows);
    }
}
