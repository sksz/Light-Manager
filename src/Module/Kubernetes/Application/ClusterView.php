<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Module\Kubernetes\Domain\ValueObject\ClusterVersion;
use LightManager\Module\Kubernetes\Domain\ValueObject\ContextName;

/**
 * Co wiadomo o klastrze w tej chwili — migawka (krok 54).
 *
 * Wspólne źródło **dwóch** kwerend: `k8s.contexts` pyta ją o spis, `k8s.cluster`
 * o wersję, adres i etap. Jeden obiekt na dwie kwerendy, bo obie opisują **tę
 * samą chwilę** i pochodzą z jednego odczytu `kubeconfig`; dwie migawki
 * znaczyłyby dwa razy tę samą pracę w tej samej klatce.
 *
 * Migawka, a nie żywy `ClusterState`, i to z tego samego powodu, co w module
 * Dockera: fasada oddająca obiekt roboczy musiałaby oddawać go jako `null`owalny,
 * bo przy module wyłączonym nie ma czego oddać.
 */
final readonly class ClusterView
{
    /**
     * @param list<ContextName>              $contexts
     * @param array<string, string|int|float> $problemParameters
     */
    public function __construct(
        public array $contexts,
        public ?ContextName $current,
        public ?ClusterVersion $versions,
        public ClusterStage $stage,
        public ?string $problemKey = null,
        public array $problemParameters = [],
    ) {
    }

    public static function of(ClusterState $cluster): self
    {
        return new self(
            $cluster->contexts(),
            $cluster->current(),
            $cluster->versions(),
            $cluster->stage(),
            $cluster->problemKey(),
            $cluster->problemParameters(),
        );
    }

    /** Odpowiedź zastępcza fasady, gdy kwerendy nie ma kto wykonać (reguła 8). */
    public static function empty(): self
    {
        return new self([], null, null, ClusterStage::Unknown);
    }

    public function isReady(): bool
    {
        return $this->stage === ClusterStage::Ready;
    }

    public function isWorking(): bool
    {
        return $this->stage === ClusterStage::Reading;
    }
}
