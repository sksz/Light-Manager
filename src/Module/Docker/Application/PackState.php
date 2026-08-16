<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/**
 * Stan pakowania kontekstu budowy — dana oglądana co klatkę (krok 51).
 *
 * Miarą są **pliki, nie bajty**, i to jest różnica wobec kopiowania z kroku 42:
 * tam pasek liczył bajty, bo przepisywał je PHP i znał każdy z nich. Tutaj bajty
 * przepisuje `PharData` w środku jednego wywołania, więc jedyną liczbą, którą
 * aplikacja naprawdę zna, jest liczba wpisów włożonych do archiwum. Licznik
 * mówiący prawdę o plikach jest uczciwszy niż pasek zgadujący bajty.
 */
final readonly class PackState
{
    /** @param array<string, string|int|float> $problemParameters */
    private function __construct(
        public PackStage $stage,
        public int $done,
        public int $total,
        /** Ścieżka gotowego archiwum; `null` wszędzie poza `Packed`. */
        public ?string $archivePath,
        public ?string $problemKey,
        public array $problemParameters,
    ) {
    }

    public static function idle(): self
    {
        return new self(PackStage::Idle, 0, 0, null, null, []);
    }

    public static function packing(int $done, int $total): self
    {
        return new self(PackStage::Packing, $done, $total, null, null, []);
    }

    public static function packed(string $archivePath, int $files): self
    {
        return new self(PackStage::Packed, $files, $files, $archivePath, null, []);
    }

    /** @param array<string, string|int|float> $parameters */
    public static function failed(string $problemKey, array $parameters = []): self
    {
        return new self(PackStage::Failed, 0, 0, null, $problemKey, $parameters);
    }

    public function isPacking(): bool
    {
        return $this->stage === PackStage::Packing;
    }

    public function isPacked(): bool
    {
        return $this->stage === PackStage::Packed;
    }

    /** Ułamek wykonania; `null`, gdy nie ma czego dzielić. */
    public function fraction(): ?float
    {
        return $this->total > 0 ? min(1.0, $this->done / $this->total) : null;
    }
}
