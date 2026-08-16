<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Module\Docker\Domain\ValueObject\ImageRef;

/**
 * Stan budowy obrazu w tej chwili — migawka (krok 54).
 *
 * Trzeci brat `ImageView` i `ContainerView`, ale to on jest w tym kroku
 * najważniejszy: **na nim stoi trzeci etap czynności `k8s.deploy-image`.** Moduł
 * Kubernetesa dowiaduje się zdarzeniem, że budowa się skończyła, i pyta o wynik
 * kwerendą `docker.build` — czyli dostaje ten obiekt jako wiersze, bo ładunku
 * (jak każdy obcy) nie zobaczy.
 *
 * `imageId` jest tu polem, dla którego cała klasa istnieje: po udanej budowie
 * wołający musi wiedzieć **co** zbudował, a nie tylko **że** zbudował.
 */
final readonly class BuildProgress
{
    public function __construct(
        public BuildStage $stage,
        public ?ImageRef $tag,
        public string $directory,
        /** Skrót zbudowanego obrazu; `null`, dopóki budowa go nie poda. */
        public ?string $imageId,
        /** Ostatnie zdanie budowy — to, co widać w oknie pracy. */
        public string $note,
        /** Ułamek pakowania; `null` przy budowie, bo tam nie ma czego dzielić. */
        public ?float $fraction,
        public ?string $problemKey = null,
        /** @var array<string, string|int|float> */
        public array $problemParameters = [],
    ) {
    }

    /** Odpowiedź zastępcza fasady, gdy kwerendy nie ma kto wykonać (reguła 8). */
    public static function empty(): self
    {
        return new self(BuildStage::Idle, null, '', null, '', null);
    }

    public function isWorking(): bool
    {
        return $this->stage === BuildStage::Packing || $this->stage === BuildStage::Building;
    }

    public function isDone(): bool
    {
        return $this->stage === BuildStage::Done;
    }
}
