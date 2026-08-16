<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Module\Docker\Domain\ValueObject\ImageRef;

/**
 * Stan wypychania obrazu — migawka (krok 54).
 *
 * Czwarta migawka modułu Dockera, po `ImageView`, `ContainerView`
 * i `BuildProgress`, i ta sama rola co ostatniej z nich: **czynność prowadzona
 * przez inny moduł ogląda przez nią cudzą pracę.** `k8s.deploy-image` zamawia
 * wypchnięcie komendą, a potem pyta kwerendą `docker.push`, czy już — bo
 * odpowiedzi „już" nie da się dostać inaczej niż pytając, dopóki praca nie ma
 * własnego zdarzenia.
 */
final readonly class PushProgress
{
    public function __construct(
        public PushStage $stage,
        public ?ImageRef $target,
        /** Ostatnie zdanie rozmowy z rejestrem. */
        public string $note,
        public ?string $problemKey = null,
        /** @var array<string, string|int|float> */
        public array $problemParameters = [],
    ) {
    }

    /** Odpowiedź zastępcza fasady, gdy kwerendy nie ma kto wykonać (reguła 8). */
    public static function empty(): self
    {
        return new self(PushStage::Idle, null, '');
    }

    /**
     * Czy praca trwa — **oba etapy robocze, nie tylko wypychanie**.
     *
     * Pominięcie `Tagging` kosztowało w kroku 54 jeden defekt widoczny wyłącznie
     * na żywym demonie: kwerenda mówiła „nie pracuję" w chwili, w której praca
     * dopiero się zaczynała, więc okno czekania czynności `k8s.deploy-image`
     * zamykało się **po ułamku sekundy** i meldowało niepowodzenie. Testy tego nie
     * złapały, bo powstały, gdy etap był jeden.
     *
     * Reguła ogólna z tej pomyłki: **dokładając etap pracy, przejrzyj każdy
     * rachunek „czy trwa"** — jest ich tyle, ile jest migawek, a każdy przeoczony
     * kończy pracę przedwcześnie w oczach tego, kto ją ogląda.
     */
    public function isWorking(): bool
    {
        return $this->stage === PushStage::Tagging || $this->stage === PushStage::Pushing;
    }

    public function isDone(): bool
    {
        return $this->stage === PushStage::Done;
    }
}
