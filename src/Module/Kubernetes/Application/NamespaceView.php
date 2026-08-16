<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

/**
 * Przestrzenie nazw, o których sesja wie — migawka (krok 54).
 *
 * Powód, dla którego „wie” nie znaczy „istnieją w klastrze”, stoi przy
 * `NamespacesQuery`: spis pełny kosztuje obieg do klastra, a kwerenda odpowiada
 * w klatce albo nie odpowiada wcale.
 */
final readonly class NamespaceView
{
    /** @param list<string> $names bieżąca zawsze pierwsza, reszta alfabetycznie */
    public function __construct(
        public array $names,
        public ?string $current,
    ) {
    }

    /** Odpowiedź zastępcza fasady, gdy kwerendy nie ma kto wykonać (reguła 8). */
    public static function empty(): self
    {
        return new self([], null);
    }
}
