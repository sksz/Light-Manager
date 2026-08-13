<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\Port;

use LightManager\Module\FileInfo\Application\Dto\TextAnchor;
use LightManager\Module\FileInfo\Application\Dto\TextWindow;

/**
 * Podgląd pliku tekstowego czytany **przesuwnym oknem**, a nie w całości.
 *
 * Kształt portu podyktowało rozstrzygnięcie ze startu kroku 29: podgląd ma
 * działać jak edytor — w pamięci siedzi tylko to, co widać, przewinięcie
 * porzuca poprzednie wiersze i doczytuje następne. Stąd nie ma tu metody
 * `lines(path)`, bo obiecywałaby wczytanie pliku; są **trzy pytania zadawane
 * przy rysowaniu**: czy to w ogóle tekst, co widać od tej kotwicy w dół i gdzie
 * jest kotwica o panel wyżej.
 *
 * Port jest **własnością modułu**, choć czytanie pliku brzmi rdzeniowo. To ta
 * sama granica, co przy `PreviewEntryUseCase`: rdzeń nie wie, czym jest wpis
 * w systemie plików (reguła 15), a komponent `TextView`, który dostaje stąd
 * wiersze, nie czyta, nie dekoduje i nie zna pliku.
 *
 * Odczyt **nie jest pracą kawałkową w rozumieniu D46** i nie musi nią być:
 * jedno wywołanie czyta tyle, ile pokazuje — kilkadziesiąt kilobajtów przy
 * pełnym panelu — więc mieści się w klatce z zapasem. Wzorzec z kroku 25
 * obowiązuje prace, których w klatce **nie da się** wykonać; ta się da, i to
 * jest właśnie powód, dla którego okno czyta się przy rysowaniu.
 */
interface TextPreviewPort
{
    /**
     * Czy plik nadaje się na podgląd tekstowy — i co powiedzieć, jeśli nie.
     *
     * Rozstrzyga **kaskada trzech metod** (rozstrzygnięcie ze startu kroku):
     * rozszerzenie, potem opis od polecenia `file`, potem podejrzenie pierwszych
     * bajtów. Każda kolejna wchodzi dopiero wtedy, gdy poprzednia nie umiała
     * odpowiedzieć twierdząco, a ostatnia rozstrzyga zawsze.
     *
     * @param ?string $description wyjście polecenia `file`, jeśli moduł już je ma
     *
     * @return string|null klucz katalogu z powodem odmowy; `null` — plik jest tekstem
     */
    public function refuse(string $path, ?string $description): ?string;

    /** Okno zaczynające się od kotwicy: tyle wierszy, ile mieści panel. */
    public function forward(string $path, TextAnchor $anchor, int $lines, int $characters): TextWindow;

    /**
     * Kotwica o `$lines` wierszy **w górę** od podanej.
     *
     * Osobna metoda, bo wstecz nie ma jak pójść inaczej niż przez plik: początek
     * poprzedniego wiersza jest tam, gdzie stoi poprzedni znak nowej linii,
     * a tego nie widać z kotwicy. Szukanie jest ograniczone tym samym budżetem
     * bajtów, co odczyt w przód — plik bez ani jednego znaku nowej linii nie ma
     * prawa zatrzymać klatki tylko dlatego, że ktoś nacisnął `PgUp`.
     */
    public function backward(string $path, TextAnchor $anchor, int $lines, int $characters): TextAnchor;
}
