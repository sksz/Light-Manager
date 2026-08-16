<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

/**
 * Wdrożenia wraz z obrazami kontenerów — migawka (krok 54).
 *
 * Bliźniak `ResourceView` zawężony do jednego rodzaju i istnieje osobno z jednego
 * powodu: **czynność `k8s.deploy-image` pyta o wdrożenia po nazwie kwerendy**,
 * a nie po adresie rodzaju podanym argumentem. Gdyby pytała `k8s.resources
 * deployments.apps`, wiedziałaby, jak Kubernetes nazywa swoje rodzaje — czyli
 * znałaby cudzą dziedzinę tam, gdzie wystarczy nazwa kwerendy.
 */
final readonly class DeploymentView
{
    /** @param list<ResourceRow> $rows */
    public function __construct(
        public array $rows,
        public bool $loaded,
        public bool $working,
        public ?string $problemKey = null,
    ) {
    }

    /** Odpowiedź zastępcza fasady, gdy kwerendy nie ma kto wykonać (reguła 8). */
    public static function empty(): self
    {
        return new self([], false, false);
    }

    public function stage(): string
    {
        return match (true) {
            $this->working => 'reading',
            $this->loaded => 'ready',
            default => 'absent',
        };
    }

    /**
     * Kontenery jednego wdrożenia — nazwa kontenera → obraz.
     *
     * @return array<string, string>
     */
    public function containersOf(string $deployment): array
    {
        foreach ($this->rows as $row) {
            if ($row->name === $deployment) {
                return $row->images;
            }
        }

        return [];
    }
}
