<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

/**
 * Strefa klatki zamówiona przez ekran: etykieta jej obwódki i treść, która w niej
 * stanie.
 *
 * Powstała w kroku 21, gdy rdzeń przestał mieć z czego rysować pasek ścieżki i pas
 * podglądu. Do kroku 20 obie strefy brały treść z katalogu leżącego w `LoopState`;
 * po przenosinach katalog należy do modułu przeglądarki, więc strefy poszły tam,
 * gdzie dane.
 *
 * **Oprawa zostaje rdzeniowi.** Obwódkę, nawiasy narożne i samą etykietę rysuje
 * dalej `FrameComposer::chrome()` — stąd `labelKey` jest kluczem katalogu napisów,
 * a nie napisem: ekran nazywa strefę, ale jej nie maluje i motywu od tej strony nie
 * zna.
 */
final class ScreenZone
{
    public function __construct(
        /** Klucz katalogu napisów z etykietą obwódki strefy. */
        public readonly string $labelKey,
        public readonly ComponentInterface $content,
    ) {
    }
}
