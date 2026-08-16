<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation\Overlay;

/**
 * Jedna pozycja okna wyboru — **dana, nie klucz katalogu** (krok 54).
 *
 * Trzy pola i każde z innego powodu. `id` jest tym, co wraca do wołającego, i nie
 * musi być tym, co widać — przy wdrożeniach jest to para „wdrożenie/kontener",
 * której użytkownik nie chce czytać w takiej postaci. `label` jest tym, co widać.
 * `detail` stoi po prawej stronie wiersza i mieści to, co odróżnia pozycje o tej
 * samej nazwie: rozmiar obrazu, przestrzeń nazw wdrożenia, obraz kontenera.
 *
 * Klasa jest daną, jak `ListRow` i `Section` — nie komponentem (11a).
 */
final readonly class PickItem
{
    public function __construct(
        /** Co wraca do wołającego po wybraniu. */
        public string $id,
        /** Co widać po lewej stronie wiersza. */
        public string $label,
        /** Co widać po prawej — pusty napis znaczy „nic". */
        public string $detail = '',
    ) {
    }
}
