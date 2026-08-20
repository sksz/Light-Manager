<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Registry;

/**
 * Stan jednej rozmowy z rejestrem — **stan pracy, nie wynik** (krok 61, etap 2).
 *
 * Reguła 11w mówi o kwerendzie: „odpowiada w klatce albo oddaje **stan pracy**,
 * nigdy nie czeka na jej koniec”. Rozmowa z rejestrem jest siecią, więc drugiej
 * możliwości tu nie ma — a ponieważ ma **trzy obiegi**, stan mówi także, na
 * którym z nich stoi (`RegistryStage`).
 */
final readonly class RegistryResult
{
    /** @param array<string, string|int|float> $problemParameters */
    private function __construct(
        public RegistryStage $stage,
        public string $body,
        public ?int $status,
        public ?string $problemKey,
        public array $problemParameters,
    ) {
    }

    public static function idle(): self
    {
        return new self(RegistryStage::Idle, '', null, null, []);
    }

    public static function working(RegistryStage $stage): self
    {
        return new self($stage, '', null, null, []);
    }

    public static function done(string $body, int $status): self
    {
        return new self(RegistryStage::Done, $body, $status, null, []);
    }

    /** @param array<string, string|int|float> $parameters */
    public static function failed(string $problemKey, array $parameters = []): self
    {
        return new self(RegistryStage::Failed, '', null, $problemKey, $parameters);
    }

    public function isWorking(): bool
    {
        return $this->stage->isWorking();
    }

    /** Czy rejestr uznał pytanie za załatwione (2xx). */
    public function isSuccessful(): bool
    {
        return $this->stage === RegistryStage::Done && $this->status !== null
            && $this->status >= 200 && $this->status < 300;
    }

    /**
     * Czy rejestr powiedział „nie mam takiego zasobu”.
     *
     * Wyodrębnione, bo `404` na `/v2/_catalog` **nie jest awarią**, tylko
     * odpowiedzią „ten rejestr katalogu nie wystawia” — i ma przełączyć widok
     * w tryb „podaj nazwę obrazu”, zamiast mówić o błędzie (trzecia trudność
     * kroku).
     */
    public function isMissing(): bool
    {
        return $this->stage === RegistryStage::Done && $this->status === 404;
    }
}
