<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\Dto;

/**
 * Miejsce w pliku, od którego zaczyna się okno podglądu — bajt i numer wiersza.
 *
 * Kotwica istnieje, bo podgląd czyta **przesuwnym buforem, jak edytor**
 * (rozstrzygnięcie ze startu kroku 29): w pamięci są wyłącznie te wiersze, które
 * właśnie widać, a przewinięcie porzuca poprzednie i doczytuje następne. Przy
 * takim odczycie nie ma listy, po której dałoby się liczyć wycinek numerem
 * wiersza — jest **położenie w bajtach**, bo tylko ono pozwala usiąść w środku
 * pliku bez przeczytania wszystkiego przed nim.
 *
 * Numer wiersza jedzie obok bajtu i **liczy się przyrostowo**, a nie przez
 * przeliczanie pliku od początku: przewinięcie o pięć wierszy w dół dodaje pięć,
 * w górę — odejmuje. Inaczej numer w pliku półgigabajtowym kosztowałby przy
 * każdym przewinięciu przejście przez cały plik, czyli dokładnie to, czego ten
 * krok ma nie robić.
 */
final class TextAnchor
{
    public function __construct(
        public readonly int $byte = 0,
        public readonly int $line = 1,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->byte === $other->byte && $this->line === $other->line;
    }

    public function signature(): string
    {
        return $this->byte . ':' . $this->line;
    }
}
