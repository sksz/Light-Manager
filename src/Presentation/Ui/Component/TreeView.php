<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Domain\ValueObject\ScrollPosition;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Drzewo: wcięcie, prowadnice gałęzi, znacznik rozwinięcia — a rysowanie oddane
 * liście.
 *
 * Wzorzec jest wzięty z kroku 22 co do joty i to nie jest oszczędność, tylko
 * warunek postawiony w planie: `SectionList` spłaszcza, wycina okno i oddaje
 * rysowanie `ListView`owi, bo podkład zaznaczenia i suwak są **jego** robotą.
 * Drzewo jest tym samym problemem o wymiar głębiej, więc kończy swoją pracę
 * dokładnie tam, gdzie tamta klasa: na złożeniu wierszy.
 *
 * **Drzewo przychodzi już spłaszczone** (reguła planu kroku 31). Komponent nie
 * schodzi po gałęziach, bo zejście oznaczałoby wiedzę o tym, skąd biorą się
 * dzieci — czyli wejście-wyjście, którego rdzeń nie zna (D42). Dostaje listę
 * `TreeNode`ów i traktuje ją jak listę wierszy, którą po spłaszczeniu **jest**.
 *
 * Prowadnice (`├─`, `└─`, `│`) są rozstrzygnięciem użytkownika ze startu kroku
 * i mają cenę, którą warto znać: każdy poziom dokłada znak spoza podstawowej
 * strony kodowej, czyli osobną bitmapę w pamięci podręcznej wierszy (D34).
 * Wariantem odrzuconym było samo wcięcie ze znacznikiem — tańsze o te bitmapy,
 * ale przy głębokim zagnieżdżeniu nie widać w nim, do którego rodzica wraca
 * gałąź. Koszt rozlicza scenariusz `tree`.
 *
 * Cały wiersz idzie **jedną rolą koloru** — prowadnice nie są wyszarzane osobno.
 * To ta sama decyzja, co w `Section::lines()`: drugi `TextRun` na wiersz podwoiłby
 * liczbę napisów w klatce po to, żeby odcieniować kreski, na które nikt nie
 * patrzy.
 */
final class TreeView implements ComponentInterface
{
    /** Gałąź rozwinięta — trójkąt w dół, ten sam co w `Section`. */
    public const OPEN = '▼';

    /** Gałąź zwinięta — trójkąt w prawo, czyli „jest tu coś dalej”. */
    public const CLOSED = '▶';

    /** Odgałęzienie do węzła, po którym idą jeszcze następne. */
    public const BRANCH = '├─';

    /** Odgałęzienie do ostatniego dziecka — dalej na tym poziomie nic nie ma. */
    public const LAST = '└─';

    /** Prowadnica poziomu, na którym rodzeństwo jeszcze się nie skończyło. */
    public const TRUNK = '│ ';

    /** Poziom domknięty — prowadnicy nie ma, zostaje samo wcięcie. */
    public const CLEAR = '  ';

    /** Węzeł bez dzieci: miejsce po znaczniku zostaje puste, żeby nazwy stały w pionie. */
    public const LEAF = ' ';

    /**
     * Ile kolumn zostawić nazwie, zanim wcięcie zacznie się skracać.
     *
     * Drzewo bez limitu głębokości potrafi zepchnąć nazwę poza prawą krawędź
     * panelu — a wiersz złożony z samych kresek nie mówi nic. Poniżej tego progu
     * wcięcie ustępuje **od lewej** (`Label::fitEnd()`), więc znika początek
     * ścieżki prowadnic, a znacznik gałęzi i nazwa zostają. Odwrotne cięcie
     * zabrałoby dokładnie te dwa znaki, dla których prowadnice powstały.
     */
    private const MINIMUM_LABEL = 6;

    /**
     * @param list<TreeNode>  $nodes    **wszystkie** widoczne węzły po spłaszczeniu
     * @param int             $offset   pierwszy widoczny wiersz
     * @param ?int            $cursor   numer węzła pod kursorem w pełnej liście;
     *                                  `null` — kursora nie ma
     * @param ?ScrollPosition $position okno przewijania; `null` — nie ma czego przewijać
     */
    public function __construct(
        private readonly array $nodes,
        private readonly int $offset = 0,
        private readonly ?int $cursor = null,
        private readonly ?ScrollPosition $position = null,
    ) {
    }

    /** @return list<Primitive> */
    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $rows = [];
        $room = max(0, $bounds->columns - self::MINIMUM_LABEL);

        foreach (array_slice($this->nodes, $this->offset, $bounds->rows) as $node) {
            $rows[] = new ListRow(
                Label::fitEnd(self::prefixOf($node), $room) . $node->label,
                $node->value,
                $node->role,
            );
        }

        return (new ListView($rows, $this->selected(count($rows)), $this->position))->draw($bounds);
    }

    /**
     * Wcięcie wraz z prowadnicami i znacznikiem gałęzi.
     *
     * Statyczna i publiczna, bo pyta o nią **test** i pytać powinien: to jest cała
     * treść tego komponentu, a sprawdzanie jej przez porównywanie prymitywów
     * mierzyłoby przy okazji `ListView` i `Label`.
     */
    public static function prefixOf(TreeNode $node): string
    {
        $prefix = '';

        foreach ($node->guides as $continues) {
            $prefix .= $continues ? self::TRUNK : self::CLEAR;
        }

        $prefix .= $node->last ? self::LAST : self::BRANCH;

        if (!$node->hasChildren) {
            return $prefix . self::LEAF . ' ';
        }

        return $prefix . ($node->expanded ? self::OPEN : self::CLOSED) . ' ';
    }

    /**
     * Położenie kursora **w wycinku**, albo `null`, gdy wyszedł poza okno.
     *
     * Ta sama poprawka, co w `SectionList::selected()`: lista dostaje wycinek, więc
     * numer bezwzględny podkreśliłby w nim przypadkowy wiersz.
     */
    private function selected(int $visible): ?int
    {
        if ($this->cursor === null) {
            return null;
        }

        $inWindow = $this->cursor - $this->offset;

        return $inWindow >= 0 && $inWindow < $visible ? $inWindow : null;
    }
}
