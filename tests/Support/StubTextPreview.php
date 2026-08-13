<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\FileInfo\Application\Dto\TextAnchor;
use LightManager\Module\FileInfo\Application\Dto\TextWindow;
use LightManager\Module\FileInfo\Application\Port\TextPreviewPort;

/**
 * Plik tekstowy trzymany w pamięci — atrapa portu podglądu (krok 29).
 *
 * Atrapa liczy wiersze zamiast bajtów i **to jest jej cała różnica** wobec
 * prawdziwej usługi: kotwica niesie tu numer wiersza także w polu bajtu, bo
 * testy ekranu sprawdzają, *co widać po przewinięciu*, a nie arytmetykę
 * offsetów. Tamtą sprawdza `TextPreviewTest` na prawdziwych plikach — jedno
 * i drugie na atrapie sprawdzałoby atrapę.
 */
final class StubTextPreview implements TextPreviewPort
{
    /** @var list<string> */
    public array $askedPaths = [];

    /** @param list<string> $lines */
    public function __construct(
        private readonly array $lines = [],
        private readonly ?string $refusal = null,
    ) {
    }

    public function refuse(string $path, ?string $description): ?string
    {
        $this->askedPaths[] = $path;

        return $this->refusal;
    }

    public function forward(string $path, TextAnchor $anchor, int $lines, int $characters): TextWindow
    {
        $first = max(0, $anchor->byte);
        $shown = array_slice($this->lines, $first, max(1, $lines));
        $next = min(count($this->lines), $first + max(1, $lines));

        // Atrapa liczy wiersze zamiast bajtów, więc „początkiem wiersza” jest tu
        // po prostu jego numer — zgodnie z regułą całej tej klasy.
        $starts = [];

        foreach (array_keys($shown) as $index) {
            $starts[] = $first + $index;
        }

        return TextWindow::of(
            $shown,
            $starts,
            $anchor,
            new TextAnchor($next, $next + 1),
            count($this->lines),
        );
    }

    public function backward(string $path, TextAnchor $anchor, int $lines, int $characters): TextAnchor
    {
        $first = max(0, $anchor->byte - max(1, $lines));

        return new TextAnchor($first, $first + 1);
    }
}
