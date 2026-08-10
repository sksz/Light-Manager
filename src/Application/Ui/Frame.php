<?php

declare(strict_types=1);

namespace LightManager\Application\Ui;

/**
 * Niemutowalny opis tego, co ma się znaleźć na ekranie w danej iteracji pętli:
 * stos płaszczyzn w porządku nakładania.
 *
 * Do kroku 18 klatka była płaską strukturą — tytuł, lista wierszy, komunikat,
 * okienko, podgląd, położenie okna przewijania, podpowiedzi — a renderer znał
 * znaczenie **każdego** z tych pól. Każdy nowy element interfejsu kosztował
 * wtedy dwie implementacje: jedną w enkoderze Sixela, drugą w rendererze
 * tekstowym. Dziś klatka nie wie, co przedstawia; niesie kształty i ich
 * kolejność.
 *
 * Klasa mieszka w `Application`, nie w `Domain` (krok 18, D36): przechodzi przez
 * `FrameRendererPort`, więc musi ją widzieć `Infrastructure`, a domena menadżera
 * plików przestała mieć cokolwiek wspólnego z rysowaniem.
 */
final class Frame
{
    /** @param list<Plane> $planes od spodu do wierzchu */
    public function __construct(
        public readonly array $planes,
    ) {
    }

    public function signature(): string
    {
        $parts = [];

        foreach ($this->planes as $plane) {
            $parts[] = $plane->signature();
        }

        return implode("\x1e", $parts);
    }

    public function equals(self $other): bool
    {
        return $this->signature() === $other->signature();
    }
}
