<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/**
 * Stan tunelu SSH — dana oglądana co klatkę (krok 58, wzorzec D46).
 *
 * Posuwa go takt modułu, a ogląda górny pas ekranu, spis środowisk i kwerenda
 * `docker.environments`. Powód niepowodzenia jest **cytatem z klienta**
 * (ostatni wiersz jego wypisu w `{reason}`), bo to klient wie, czy nie
 * odpowiedział host, czy odmówiło uwierzytelnienie — ta sama granica, co przy
 * strumieniu błędów `sftp` w kroku 49.
 */
final readonly class TunnelState
{
    /** @param array<string, string> $problemParameters */
    private function __construct(
        public TunnelStage $stage,
        public ?string $socketPath,
        public ?string $problemKey,
        public array $problemParameters,
    ) {
    }

    public static function none(): self
    {
        return new self(TunnelStage::None, null, null, []);
    }

    public static function starting(): self
    {
        return new self(TunnelStage::Starting, null, null, []);
    }

    public static function up(string $socketPath): self
    {
        return new self(TunnelStage::Up, $socketPath, null, []);
    }

    /** @param array<string, string> $parameters */
    public static function failed(string $problemKey, array $parameters = []): self
    {
        return new self(TunnelStage::Failed, null, $problemKey, $parameters);
    }

    public function isUp(): bool
    {
        return $this->stage === TunnelStage::Up;
    }
}
