<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;

/**
 * Element interfejsu, który potrafi się narysować w zadanym prostokącie.
 *
 * Kontrakt ma jedną metodę i to jest rozstrzygnięcie, nie niedopatrzenie.
 * Szkic planu przewidywał obok niej `measure()` — pytanie „ile miejsca chcesz
 * zająć”. Odpowiedź na nie byłaby jednak **drugim źródłem tej samej
 * informacji**: rozmiar minimalny, preferowany i kolejność ustępowania niesie
 * już `Container\Slot`, a komponent, który zna swoją naturalną wysokość
 * (przycisk, okno dialogowe), wystawia ją własną metodą i używa jej ten, kto
 * buduje szczelinę. Dwa źródła musiałyby się pilnować nawzajem — a to dokładnie
 * ten rodzaj powtórzenia, który krok 18 usuwa.
 *
 * Komponent, który dostał pusty prostokąt, nie rysuje nic. Tak kontener mówi
 * dziecku, że w tym oknie się nie zmieściło, i żadne dziecko nie musi tego
 * traktować jak błędu.
 */
interface ComponentInterface
{
    /** @return list<Primitive> */
    public function draw(Rect $bounds): array;
}
