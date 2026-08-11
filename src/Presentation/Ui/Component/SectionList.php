<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Domain\ValueObject\ScrollPosition;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Lista zwijanych sekcji: spłaszcza je do wierszy, wycina okno i oddaje
 * rysowanie liście.
 *
 * Sekcje przewijają się **jak jedna lista**, a nie jak stos niezależnych paneli:
 * użytkownik ciągnący `↓` przez opis nie ma czuć granic. Dlatego wycinanie okna
 * dzieje się tutaj, na wierszach wszystkich sekcji naraz, a nie w każdej sekcji
 * z osobna.
 *
 * **`ListView` zostaje nietknięty i to jest rozstrzygnięcie, nie oszczędność.**
 * Używa go dziś przeglądarka, ekran ustawień i pomoc; dorzucenie mu wiedzy
 * o sekcjach kosztowałoby trzech użytkowników, z których dwóch sekcji nie chce.
 * Podkład zaznaczenia i suwak i tak są jego robotą, więc lista sekcji kończy
 * swoją pracę tam, gdzie zaczyna się jego.
 *
 * Okno przewijania komponent dostaje **z zewnątrz**, tak samo jak `ListView`:
 * powstaje na nowo trzydzieści razy na sekundę i nie zapamiętałby, gdzie stał
 * przed chwilą. Pamięć należy do ekranu (`ScrollWindow` i `SectionState`).
 */
final class SectionList implements ComponentInterface
{
    /**
     * @param list<Section>   $sections wszystkie sekcje, także zwinięte
     * @param int             $offset   pierwszy widoczny wiersz po spłaszczeniu
     * @param ?int            $cursor   numer sekcji pod kursorem; `null` — kursora nie ma
     * @param ?ScrollPosition $position okno przewijania; `null` — nie ma czego przewijać
     */
    public function __construct(
        private readonly array $sections,
        private readonly int $offset = 0,
        private readonly ?int $cursor = null,
        private readonly ?ScrollPosition $position = null,
    ) {
    }

    /**
     * Ile wierszy zajmą sekcje po spłaszczeniu.
     *
     * Statyczna, bo potrzebuje jej **ekran**, i to zanim złoży komponent: bez tej
     * liczby nie da się ani przyciąć okna przewijania, ani policzyć suwaka.
     *
     * @param list<Section> $sections
     */
    public static function rowCount(array $sections): int
    {
        $total = 0;

        foreach ($sections as $section) {
            $total += $section->height();
        }

        return $total;
    }

    /**
     * Wiersz, w którym stoi nagłówek sekcji o zadanym numerze.
     *
     * Tym punktem ekran karmi `ScrollWindow::keepVisible()` — kursor chodzi po
     * nagłówkach, więc to nagłówek ma zostać w oknie.
     *
     * @param list<Section> $sections
     */
    public static function rowOf(array $sections, int $section): int
    {
        $row = 0;

        foreach ($sections as $index => $current) {
            if ($index >= $section) {
                break;
            }

            $row += $current->height();
        }

        return $row;
    }

    /** @return list<Primitive> */
    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $lines = [];
        $header = null;

        foreach ($this->sections as $index => $section) {
            if ($index === $this->cursor) {
                $header = count($lines);
            }

            foreach ($section->lines() as $line) {
                $lines[] = $line;
            }
        }

        $visible = array_slice($lines, $this->offset, $bounds->rows);

        return (new ListView($visible, $this->selected($header, count($visible)), $this->position))->draw($bounds);
    }

    /**
     * Położenie nagłówka pod kursorem **w wycinku**, albo `null`, gdy kursor
     * wyszedł poza okno. Lista dostaje wycinek, więc numer bezwzględny
     * podkreśliłby w nim przypadkowy wiersz.
     */
    private function selected(?int $header, int $visible): ?int
    {
        if ($header === null) {
            return null;
        }

        $inWindow = $header - $this->offset;

        return $inWindow >= 0 && $inWindow < $visible ? $inWindow : null;
    }
}
