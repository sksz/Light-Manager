<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application;

use LightManager\Module\Ssh\Domain\ValueObject\HostFingerprint;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;

/**
 * Stan sesji w tej chwili — **dana oglądana co klatkę, nie proces** (krok 48).
 *
 * Druga część wzorca pracy kawałkowej (D46), ta sama, którą niosą
 * `ChecksumState` i `BackgroundState`: ekran, pasek stanu i okno postępu pytają
 * o to co klatkę i rysują, co zastaną. Niczego nie uruchamia i nie zawiera ani
 * jednego uchwytu do procesu — od tego jest port.
 *
 * **Powód niepowodzenia jest kluczem katalogu, nie zdaniem.** Port nie rzuca
 * przez granicę (reguła 8), bo połączenie nie udaje się **rutynowo**, a nie
 * wyjątkowo — host bywa wyłączony i nie jest to awaria aplikacji.
 */
final readonly class SessionState
{
    /**
     * @param list<HostFingerprint>        $fingerprints odciski do pokazania — wyłącznie przy `AwaitingApproval`
     * @param array<string, string|int|float> $problemParameters
     */
    private function __construct(
        public SessionStage $stage,
        public ?HostProfile $host = null,
        public array $fingerprints = [],
        public ?string $problemKey = null,
        public array $problemParameters = [],
    ) {
    }

    public static function idle(): self
    {
        return new self(SessionStage::Idle);
    }

    public static function probing(HostProfile $host): self
    {
        return new self(SessionStage::Probing, $host);
    }

    /** @param list<HostFingerprint> $fingerprints */
    public static function awaitingApproval(HostProfile $host, array $fingerprints): self
    {
        return new self(SessionStage::AwaitingApproval, $host, $fingerprints);
    }

    public static function connecting(HostProfile $host): self
    {
        return new self(SessionStage::Connecting, $host);
    }

    public static function checking(HostProfile $host): self
    {
        return new self(SessionStage::Checking, $host);
    }

    public static function connected(HostProfile $host): self
    {
        return new self(SessionStage::Connected, $host);
    }

    /** @param array<string, string|int|float> $parameters */
    public static function failed(?HostProfile $host, string $problemKey, array $parameters = []): self
    {
        return new self(SessionStage::Failed, $host, [], $problemKey, $parameters);
    }

    public function isConnected(): bool
    {
        return $this->stage === SessionStage::Connected;
    }

    public function isWorking(): bool
    {
        return $this->stage->isWorking();
    }

    /** Czy sesja dotyczy tego wpisu — po nazwie własnej, bo ona jest tożsamością. */
    public function concerns(HostProfile $profile): bool
    {
        return $this->host !== null && $this->host->equals($profile);
    }
}
