<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\Port;

use LightManager\Module\FileInfo\Application\Dto\FileStat;

/**
 * To, co system mówi o wpisie bez uruchamiania procesu potomnego.
 *
 * Port jest osobny od `FileInspectorPort`, choć oba opisują ten sam plik, i to
 * jest rozstrzygnięcie ze startu kroku 25: **każdy port prosi o dokładnie to,
 * czego potrzebuje**. `describe()` przyjmuje limit czasu i argumenty, bo za nim
 * stoi proces, który potrafi się zawiesić; `stat()` nie przyjmuje ani jednego
 * i nie ma po co ich znać — wywołanie systemowe albo wraca od razu, albo nie
 * wraca wcale, a wtedy zawiesza wszystko i tak.
 */
interface FileStatPort
{
    /**
     * Dane wpisu widziane przez `lstat` — czyli **bez** pójścia za dowiązaniem;
     * dokąd ono prowadzi, mówi osobne pole.
     *
     * `null` znaczy „nie da się odczytać”: wpis zniknął między narysowaniem
     * listy a naciśnięciem klawisza albo katalog nadrzędny odmawia przejścia.
     * Nigdy nie rzuca — opis pliku, którego nie ma, jest zwykłym stanem
     * interfejsu, a nie awarią.
     */
    public function stat(string $path): ?FileStat;
}
