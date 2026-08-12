<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Terminal;

use LightManager\Application\Port\ViewportPort;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Ustala rozmiar okna terminala: raz przy starcie i ponownie po każdej
 * zmianie rozmiaru okna (krok 33).
 *
 * O zmianie mówi znacznik `SIGWINCH` z `TerminalService`, zdejmowany przy
 * najbliższym odczycie rozmiaru — czyli raz na klatkę, bo o rozmiar pytają
 * wyłącznie składanie klatki i renderer. Seria sygnałów z przeciągania rogu
 * okna składa się przez to sama do jednego pomiaru na takt pętli.
 *
 * Komórki znakowe bierzemy z `stty size` (zawsze dostępne), a piksele —
 * potrzebne rendererowi Sixel — z zapytania `ESC [ 14 t`. Terminal nie musi
 * na nie odpowiedzieć, więc jest fallback: komórki przemnożone przez typowy
 * rozmiar komórki.
 *
 * Milczenie jest tu regułą, nie wyjątkiem: XTerm blokuje operacje okienkowe
 * domyślnie (zasób `allowWindowOps`), a wraz z nimi raport rozmiaru. Dopuszcza
 * go dopiero usunięcie `14` z zasobu `disallowedWindowOps` — szczegóły w
 * README.
 */
final class TerminalSizeService extends AbstractSingleton implements ViewportPort
{
    private const WINDOW_SIZE_QUERY = "\e[14t";

    private const RESPONSE_TIMEOUT_MICROSECONDS = 300000;

    private const POLL_INTERVAL_MICROSECONDS = 5000;

    /**
     * Gdy terminal nie odpowie na pytanie o piksele, zgadujemy — i zgadujemy w
     * dół. Płótno mniejsze od okna zostawia niewykorzystany margines u dołu,
     * płótno większe wypycha ekran i zabiera wiersze z góry klatki, więc obie
     * pomyłki kosztują bardzo różnie. 6×13 to komórka domyślnego fontu XTerma,
     * czyli najczęstszy przypadek, w którym zgadywanie w ogóle się zdarza.
     */
    private const FALLBACK_CELL_WIDTH_PIXELS = 6;

    private const FALLBACK_CELL_HEIGHT_PIXELS = 13;

    private const FALLBACK_COLUMNS = 80;

    private const FALLBACK_ROWS = 24;

    private TerminalSize $size;

    /**
     * Czy terminal odpowiedział na pytanie o piksele przy pierwszym pomiarze.
     *
     * Milczenie jest własnością konfiguracji terminala (zasób
     * `disallowedWindowOps`), nie chwilową niedyspozycją — więc pytanie
     * milczącego terminala przy każdej zmianie rozmiaru kosztowałoby 300 ms
     * zamrożonej pętli za każdym razem, a odpowiedź i tak by nie przyszła.
     * Ponownie pytamy wyłącznie tego, kto już raz odpowiedział.
     */
    private bool $answersPixelQuery = false;

    protected function __construct()
    {
        parent::__construct();

        $this->size = $this->measure(previous: null, attemptPixelQuery: true);
    }

    /**
     * Rozmiar odświeża się sam: znacznik `SIGWINCH` sprawdzany jest przy
     * odczycie, więc `FrameComposer` i renderer — którzy pytają co klatkę —
     * dostają świeżą odpowiedź bez wiedzy, że coś zaszło. Kontrakt
     * `ViewportPort` zostaje nietknięty (krok 33, rozstrzygnięcie nr 2).
     */
    public function size(): TerminalSize
    {
        if (TerminalService::getInstance()->consumeWindowResize()) {
            $this->size = $this->measure($this->size, $this->answersPixelQuery);
        }

        return $this->size;
    }

    public function rows(): int
    {
        return $this->size()->rows;
    }

    public function columns(): int
    {
        return $this->size()->columns;
    }

    private function measure(?TerminalSize $previous, bool $attemptPixelQuery): TerminalSize
    {
        $cells = TerminalService::getInstance()->sizeInCells()
            ?? ['columns' => self::FALLBACK_COLUMNS, 'rows' => self::FALLBACK_ROWS];

        $pixels = $attemptPixelQuery ? $this->queryPixelSize() : null;

        if ($pixels !== null) {
            $this->answersPixelQuery = true;
        }

        $pixels ??= [
            'width' => $cells['columns'] * self::cellWidthFallback($previous),
            'height' => $cells['rows'] * self::cellHeightFallback($previous),
        ];

        return new TerminalSize(
            $pixels['width'],
            $pixels['height'],
            $cells['columns'],
            $cells['rows'],
        );
    }

    /**
     * Rozmiar komórki do zgadywania, gdy pytanie o piksele nie przyniosło
     * odpowiedzi. Przy pierwszym pomiarze zostają stałe 6×13; przy kolejnych
     * (zmiana rozmiaru okna) lepszym źródłem jest poprzedni pomiar — komórka
     * bierze się z fontu, a font się nie zmienił, więc iloraz poprzednich
     * pikseli i komórek mówi prawdę tam, gdzie stała mówi „najczęściej”.
     */
    private static function cellWidthFallback(?TerminalSize $previous): int
    {
        if ($previous === null) {
            return self::FALLBACK_CELL_WIDTH_PIXELS;
        }

        return max(1, intdiv($previous->widthPixels, max(1, $previous->columns)));
    }

    private static function cellHeightFallback(?TerminalSize $previous): int
    {
        if ($previous === null) {
            return self::FALLBACK_CELL_HEIGHT_PIXELS;
        }

        return max(1, intdiv($previous->heightPixels, max(1, $previous->rows)));
    }

    /** @return array{width: int, height: int}|null */
    private function queryPixelSize(): ?array
    {
        $terminal = TerminalService::getInstance();
        $parser = new WindowSizeParser();

        $terminal->write(self::WINDOW_SIZE_QUERY);

        $deadline = microtime(true) + self::RESPONSE_TIMEOUT_MICROSECONDS / 1000000;
        $response = '';

        while (microtime(true) < $deadline) {
            $response .= $terminal->readRawBytes(self::POLL_INTERVAL_MICROSECONDS);

            if ($parser->isComplete($response)) {
                $terminal->pushBackBytes($parser->strip($response));

                return $parser->parse($response);
            }
        }

        // Bajty zebrane do chwili poddania się nie były odpowiedzią — przy
        // pomiarze w trakcie działania (zmiana rozmiaru okna) to najpewniej
        // klawisze użytkownika i połknięcie ich byłoby zgubionym wejściem.
        $terminal->pushBackBytes($response);

        return null;
    }
}
