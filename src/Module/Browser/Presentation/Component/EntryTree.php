<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Component;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Domain\ValueObject\NameFilter;
use LightManager\Module\Browser\Presentation\BrowserTree;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\TreeView;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Zawartość panelu pokazującego **drzewo** zamiast listy — bliźniak `EntryList`
 * z kroku 24, ułożony wedle tych samych trzech zasad.
 *
 * Pierwsza: komponent leży w katalogu modułu, bo zna `BrowserTree`, czyli typ
 * przeglądarki (reguła 11, precedens `PathLine` i `PreviewBox`). Druga: obwódki
 * **nie rysuje** — przy podziale oddaje ją ekran, a rdzeń kładzie na płaszczyźnie
 * pamiętanej między klatkami; tutaj zostaje z tego samo wcięcie, żeby wiersze nie
 * weszły na kreskę. Trzecia: okno przewijania przychodzi z zewnątrz, bo komponent
 * powstaje na nowo trzydzieści razy na sekundę — z tą różnicą, że pamięta je
 * **drzewo panelu**, a nie panel: lista i drzewo przewijają się po czym innym.
 */
final class EntryTree implements ComponentInterface
{
    private const EMPTY_DIRECTORY_KEY = 'module.browser.empty';

    /** Pusto **po zawężeniu** to co innego niż pusty katalog — tak samo, jak w liście. */
    private const NO_MATCH_KEY = 'module.browser.filter.none';

    public function __construct(
        private readonly BrowserTree $tree,
        private readonly TranslatorPort $translator,
        /** Czy drzewo siedzi w podziale, czyli wewnątrz własnej obwódki. */
        private readonly bool $framed = false,
        /**
         * Zawężenie korzenia — **tylko po to, żeby wiedzieć, co znaczy pustka**.
         *
         * Filtr obowiązuje w drzewie na pierwszym poziomie, bo korzeniem jest
         * katalog panelu taki, jaki widzi lista. Podświetlenia dopasowania drzewo
         * nie pokazuje: zakresy niesie `TableRow` (krok 30), a wiersz drzewa jest
         * `ListRow`em.
         */
        private readonly NameFilter $filter = new NameFilter(''),
    ) {
    }

    /** @return list<Primitive> */
    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $inner = $this->framed ? Panel::inner($bounds) : $bounds;

        if ($inner->isEmpty()) {
            return [];
        }

        $nodes = $this->tree->nodes();

        if ($nodes === []) {
            return (new Label($this->translator->translate(
                $this->filter->isEmpty() ? self::EMPTY_DIRECTORY_KEY : self::NO_MATCH_KEY,
            )))->draw($inner);
        }

        $window = $this->tree->window();
        $cursor = $this->tree->cursorIndex();
        $offset = $window->keepVisible($cursor, count($nodes), $inner->rows);

        return (new TreeView(
            $nodes,
            $offset,
            $cursor,
            $window->position(count($nodes), $inner->rows),
        ))->draw($inner);
    }
}
