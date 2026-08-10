<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Terminal;

use LightManager\Application\Port\ViewportPort;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Ustala rozmiar okna terminala raz, przy starcie aplikacji.
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

    private readonly TerminalSize $size;

    protected function __construct()
    {
        parent::__construct();

        $this->size = $this->measure();
    }

    public function size(): TerminalSize
    {
        return $this->size;
    }

    public function rows(): int
    {
        return $this->size->rows;
    }

    public function columns(): int
    {
        return $this->size->columns;
    }

    private function measure(): TerminalSize
    {
        $cells = TerminalService::getInstance()->sizeInCells()
            ?? ['columns' => self::FALLBACK_COLUMNS, 'rows' => self::FALLBACK_ROWS];

        $pixels = $this->queryPixelSize() ?? [
            'width' => $cells['columns'] * self::FALLBACK_CELL_WIDTH_PIXELS,
            'height' => $cells['rows'] * self::FALLBACK_CELL_HEIGHT_PIXELS,
        ];

        return new TerminalSize(
            $pixels['width'],
            $pixels['height'],
            $cells['columns'],
            $cells['rows'],
        );
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

        return null;
    }
}
