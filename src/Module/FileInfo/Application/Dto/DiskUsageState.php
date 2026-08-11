<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\Dto;

/**
 * Stan pomiaru zajętości katalogu — dana oglądana co klatkę.
 *
 * Bliźniak `ChecksumState` z jedną różnicą, która jest tu sednem: **nie ma
 * ułamka**. `du` nie mówi, ile drzewa już przeszło, więc pasek postępu chodzi
 * w trybie „nie wiadomo ile jeszcze” (krok 23) — i to jest jego pierwszy
 * prawdziwy użytkownik w aplikacji. Ułamek udawany czasem byłby tym gorszym
 * kłamstwem, im większe drzewo.
 */
final class DiskUsageState
{
    private function __construct(
        public readonly DiskUsageStage $stage,
        /** Zajętość w bajtach; ma sens wyłącznie przy `Done`. */
        public readonly ?int $bytes,
        /** Klucz katalogu z powodem — wyłącznie przy `Failed`. */
        public readonly ?string $problemKey,
        /**
         * Parametry do podstawienia w powodzie.
         *
         * @var array<string, string|int|float>
         */
        public readonly array $problemParameters,
    ) {
    }

    public static function idle(): self
    {
        return new self(DiskUsageStage::Idle, null, null, []);
    }

    public static function running(): self
    {
        return new self(DiskUsageStage::Running, null, null, []);
    }

    public static function measured(int $bytes): self
    {
        return new self(DiskUsageStage::Done, max(0, $bytes), null, []);
    }

    /** @param array<string, string|int|float> $parameters */
    public static function failed(string $problemKey, array $parameters = []): self
    {
        return new self(DiskUsageStage::Failed, null, $problemKey, $parameters);
    }

    public function isRunning(): bool
    {
        return $this->stage === DiskUsageStage::Running;
    }
}
