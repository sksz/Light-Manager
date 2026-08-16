<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

/**
 * Czym skończyła się czynność zmieniająca klaster (krok 52).
 *
 * Dana, nie zdanie: niesie **klucz katalogu i parametry**, a nie gotowy napis,
 * bo składanie zdań należy do warstwy rysującej (reguła 7 — `Application` zna
 * klucze). Zdarzenie też nie stąd — publikuje je `Presentation`, bo rejestr
 * zdarzeń mieszka w stanie pętli, którego ta warstwa nie zna (granica z kroku 46).
 */
final readonly class ActionOutcome
{
    /** @param array<string, string|int|float> $problemParameters */
    private function __construct(
        public ClusterAction $action,
        public bool $successful,
        public string $subject,
        public ?string $problemKey = null,
        public array $problemParameters = [],
    ) {
    }

    public static function success(ClusterAction $action, string $subject): self
    {
        return new self($action, true, $subject);
    }

    /** @param array<string, string|int|float> $parameters */
    public static function failure(
        ClusterAction $action,
        string $subject,
        string $problemKey,
        array $parameters = [],
    ): self {
        return new self($action, false, $subject, $problemKey, $parameters);
    }
}
