<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Component;

use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Ścieżka bieżącego katalogu wraz z dopiskiem, skrócona **od lewej**.
 *
 * Do kroku 20 ten rachunek stał w `FrameComposer`, bo pasek ścieżki rysował rdzeń.
 * Zszedł tu razem z pasem i razem z jedyną rzeczą, która czyni go osobnym
 * komponentem: skracaniem od strony korzenia. `Label::fit()` ucina koniec i stawia
 * wielokropek na końcu — dla zdania właściwie, dla ścieżki najgorzej, bo znika
 * z niej dokładnie to, gdzie użytkownik stoi. `DirectoryPath::shortenedTo()` ucina
 * początek i zostawia ogon.
 *
 * Komponent leży w warstwie `Presentation` **modułu**, a nie w katalogu
 * komponentów rdzenia: zna `DirectoryPath`, więc postawiony w rdzeniu przywróciłby
 * mu wiedzę o tym, czym jest katalog — czyli dokładnie to, co krok 21 z niego
 * wyjmuje. Słownik prymitywów zostaje przy tym nietknięty: komponent składa się
 * z `Label`, tak samo jak każdy inny.
 */
final class PathLine implements ComponentInterface
{
    public function __construct(
        private readonly DirectoryPath $path,
        /** Numer zaznaczenia i znacznik wpisów ukrytych — dopisek po ścieżce. */
        private readonly string $suffix = '',
    ) {
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $path = $this->path->shortenedTo(max(1, $bounds->columns - mb_strlen($this->suffix)));

        return (new Label($path . $this->suffix))->draw($bounds);
    }
}
