<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Component;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\ListView;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\ComponentInterface;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Zawartość jednego panelu przeglądarki: lista wpisów katalogu wraz z suwakiem,
 * a przy podziale — także własna obwódka.
 *
 * Do kroku 24 ten rysunek stał wprost w `BrowserScreen::draw()` i nie było
 * powodu, żeby stał gdzie indziej: panel był jeden. Podział daje mu **drugiego
 * użytkownika w tej samej klatce**, a dwa panele różnią się dokładnie trzema
 * rzeczami — katalogiem, oknem przewijania i tym, który z nich jest czynny. Tyle
 * właśnie przyjmuje ten komponent.
 *
 * Komponent leży w katalogu modułu, a nie rdzenia, bo zna `Directory` — typ
 * domeny przeglądarki (reguła 11, precedens `PathLine` i `PreviewBox` z kroku 21).
 *
 * **Obwódkę rysuje tylko wtedy, gdy jest w podziale.** Przy jednym panelu oprawa
 * należy do rdzenia, tak jak przed tym krokiem, i klatka wygląda co do znaku jak
 * wcześniej — to jest kryterium zgodności wstecznej i sprawdza je test.
 */
final class EntryList implements ComponentInterface
{
    private const EMPTY_DIRECTORY_KEY = 'module.browser.empty';

    /** Skróty jednostek są międzynarodowe — nie przechodzą przez katalog napisów. */
    private const SIZE_UNITS = ['B', 'kB', 'MB', 'GB', 'TB'];

    public function __construct(
        private readonly Directory $directory,
        private readonly ScrollWindow $window,
        private readonly TranslatorPort $translator,
        /**
         * Czy lista siedzi w podziale, czyli wewnątrz własnej obwódki.
         *
         * Samej obwódki komponent **nie rysuje** — oddaje ją ekran, a rdzeń kładzie
         * na płaszczyźnie pamiętanej między klatkami. Tutaj zostaje z tego jedno:
         * wcięcie, żeby wiersze nie weszły na kreskę.
         */
        private readonly bool $framed = false,
    ) {
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        return $this->entries($this->framed ? Panel::inner($bounds) : $bounds);
    }

    /** @return list<Primitive> */
    private function entries(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        if ($this->directory->isEmpty()) {
            return (new Label($this->translator->translate(self::EMPTY_DIRECTORY_KEY)))->draw($bounds);
        }

        $this->window->useContext($this->directory->path()->value);

        $entries = $this->directory->entries();
        $selected = $this->directory->selection()?->index;
        $offset = $this->window->keepVisible($selected, count($entries), $bounds->rows);
        $rows = [];

        foreach (array_slice($entries, $offset, $bounds->rows) as $entry) {
            $rows[] = new ListRow(
                $entry->name . ($entry->isDirectory() ? '/' : ''),
                $entry->isDirectory() ? '' : $this->formatSize($entry->sizeInBytes),
                $entry->isDirectory() ? Role::Accent : Role::Text,
            );
        }

        return (new ListView(
            $rows,
            $selected === null ? null : $selected - $offset,
            $this->window->position(count($entries), min($bounds->rows, count($rows))),
        ))->draw($bounds);
    }

    private function formatSize(int $bytes): string
    {
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024.0 && $unit < count(self::SIZE_UNITS) - 1) {
            $value /= 1024.0;
            ++$unit;
        }

        if ($unit === 0) {
            return $bytes . ' B';
        }

        return $this->translator->number($value, 1) . ' ' . self::SIZE_UNITS[$unit];
    }
}
