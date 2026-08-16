<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

/**
 * Jeden zasób widziany z listy (krok 52).
 *
 * Dana warstwy aplikacji, nie obiekt domeny: nie ma reguł, nie ma zachowania
 * i nie waliduje niczego — jest tym, co zostaje z JSON-a klastra po
 * rozczytaniu go przez `ResourceJsonParser`. Obiektem domeny jest `ResourceRef`,
 * czyli **wskazanie** zasobu, i to on odsiewa nazwy.
 *
 * `values` trzyma wyłącznie kolumny **własne rodzaju** (gotowość, stan, liczba
 * restartów), bo nazwa, przestrzeń i wiek mają tu swoje pola i mają je
 * wszystkie zasoby naraz.
 */
final readonly class ResourceRow
{
    /**
     * @param ?int                     $createdAt chwila powstania jako znacznik
     *                                            uniksowy; `null`, gdy klaster
     *                                            jej nie podał
     * @param array<string, string>    $values    wartości kolumn własnych rodzaju,
     *                                            pod kluczami `ResourceColumn`
     */
    public function __construct(
        public string $name,
        public ?string $namespace,
        public ?int $createdAt,
        public array $values = [],
        public RowTone $tone = RowTone::Normal,
        /**
         * Obrazy kontenerów — **nazwa kontenera → obraz** (krok 54).
         *
         * Pole powstało dla kwerendy `k8s.deployments`, bez której czynność
         * `k8s.deploy-image` nie miałaby czego podmienić: `kubectl set image`
         * wymaga **nazwy kontenera**, a nie samej nazwy wdrożenia. Wypełnia się
         * z tego samego JSON-a, z którego biorą się kolumny, więc nie kosztuje
         * ani jednego wywołania; rodzaje bez kontenerów mają je puste.
         *
         * @var array<string, string>
         */
        public array $images = [],
    ) {
    }

    public function valueOf(ResourceColumn $column): string
    {
        return $this->values[$column->value] ?? '';
    }
}
