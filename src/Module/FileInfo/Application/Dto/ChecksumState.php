<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\Dto;

/**
 * Stan liczenia sumy kontrolnej w tej chwili — dana, nie proces.
 *
 * Ekran ogląda ją co klatkę i z niej właśnie bierze się wypełnienie paska
 * postępu (krok 23). `fraction` jest tu **prawdziwym ułamkiem**, a nie ozdobą:
 * liczymy własnym odczytem po kawałku, więc liczba przeczytanych bajtów wobec
 * rozmiaru pliku jest znana dokładnie. To był powód, dla którego odrzucono
 * `sha256sum` jako proces potomny — polecenie nie mówi o sobie nic, aż skończy.
 */
final class ChecksumState
{
    private function __construct(
        public readonly ChecksumStage $stage,
        /** 0.0–1.0; ma sens wyłącznie przy `Running`. */
        public readonly float $fraction,
        public readonly ?string $digest,
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
        return new self(ChecksumStage::Idle, 0.0, null, null, []);
    }

    public static function running(float $fraction): self
    {
        return new self(ChecksumStage::Running, max(0.0, min(1.0, $fraction)), null, null, []);
    }

    public static function done(string $digest): self
    {
        return new self(ChecksumStage::Done, 1.0, $digest, null, []);
    }

    /** @param array<string, string|int|float> $parameters */
    public static function failed(string $problemKey, array $parameters = []): self
    {
        return new self(ChecksumStage::Failed, 0.0, null, $problemKey, $parameters);
    }

    public function isRunning(): bool
    {
        return $this->stage === ChecksumStage::Running;
    }
}
