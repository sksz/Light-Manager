<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\Scrollbar;
use LightManager\Application\Ui\Primitive\TextMark;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\ScrollPosition;
use LightManager\Presentation\Ui\ComponentInterface;
use LightManager\Presentation\Ui\Container\Distribution;
use LightManager\Presentation\Ui\Container\Span;

/**
 * Lista o wielu kolumnach, wyrównanych w pionie.
 *
 * Powstała w kroku 27, bo `ListRow` ma dokładnie dwa pola i to jest sufit, a nie
 * wybór stylistyczny: data i prawa nie miały w liście plików gdzie stanąć,
 * a dopychanie ich spacjami znaczyłoby liczyć układ w komponencie modułu.
 *
 * **Stoi obok `ListView`, a nie zamiast niego**, i jest to rozstrzygnięcie
 * z początku kroku. Opis pliku to naprawdę etykieta i wartość, a nie tabela
 * o dwóch kolumnach; sekcje z kroku 22 zostały więc nietknięte. Cena jest
 * wymierna i warto ją znać: pętla po wierszach, pasek pod kursorem i suwak
 * istnieją odtąd w rdzeniu dwa razy. Że cena jest **niska**, wynika z kroku 18 —
 * `Highlight::under()` był już wtedy wydzielony właśnie po to, żeby zaznaczenie
 * wyglądało identycznie u każdego, kto je rysuje, a `Scrollbar` jest prymitywem,
 * nie kodem. Powtórzeniem jest sama pętla, nie mechanizm.
 *
 * **Szerokości liczy tabela, raz na klatkę, dla wszystkich wierszy naraz.** To
 * jedno zdanie odróżnia tabelę od listy napisów: wiersz liczący swoje kolumny
 * sam byłby wierszem, którego kolumny nie zgadzają się z sąsiadem. Sam rachunek
 * jest wspólny z podziałem wierszy (`Distribution`, krok 27) — łącznie z regułą,
 * że uczestnik poniżej swojego minimum **znika w całości**, zamiast pokazać
 * resztkę.
 */
final class Table implements ComponentInterface
{
    /**
     * Odstęp między sąsiednimi kolumnami, w kolumnach siatki.
     *
     * Odbiera się go **treści**, a nie szerokości: kolumna dostaje swoje miejsce
     * w rozdziale, a potem oddaje jeden znak na oddech od sąsiada z prawej.
     * Ostatnia widoczna kolumna oddechu nie oddaje, bo z prawej nie ma już
     * sąsiada — dzięki temu prawa dostępu (`rwxr-xr-x`, dziewięć znaków) mieszczą
     * się w kolumnie o szerokości dziewięciu, a nie dziesięciu.
     */
    private const GAP = 1;

    /**
     * Role podświetlenia dopasowania: pismo w kolorze tła, tło w akcencie.
     *
     * Akcent, a nie `Selection`, i to nie jest wybór estetyczny: pasek pod
     * kursorem **jest** rolą `Selection`, więc dopasowanie w zaznaczonym wierszu
     * zniknęłoby dokładnie w tym wierszu, na który użytkownik patrzy. Akcent
     * odróżnia się od obu — od tła listy i od paska zaznaczenia — bo w motywach
     * projektu jest jedynym kolorem nasyconym (D25).
     */
    private const MARK_TEXT = Role::Background;

    private const MARK_GROUND = Role::Accent;

    /**
     * @param list<Column>   $columns  kolumny wraz z regułą ustępowania
     * @param list<TableRow> $rows     widoczny wycinek listy
     * @param ?int           $selected położenie zaznaczenia w tym wycinku
     * @param ?ScrollPosition $position okno przewijania; `null` — nie ma czego przewijać
     * @param bool           $withHeader czy pierwszy wiersz to nazwy kolumn
     */
    public function __construct(
        private readonly array $columns,
        private readonly array $rows,
        private readonly ?int $selected = null,
        private readonly ?ScrollPosition $position = null,
        private readonly bool $withHeader = false,
    ) {
    }

    /**
     * Ile wierszy treści zmieści się w prostokącie — nagłówek zabiera jeden.
     *
     * Wystawione, bo pyta o to `ScrollWindow` **zanim** powstanie komponent:
     * okno przewijania trzeba wyznaczyć, żeby wiedzieć, które wiersze podać, a
     * tabela powstaje dopiero z nimi.
     */
    public static function capacityOf(Rect $bounds, bool $withHeader): int
    {
        return max(0, $bounds->rows - ($withHeader ? 1 : 0));
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty() || $this->columns === []) {
            return [];
        }

        $position = $this->position;
        $scrolls = $position !== null && $position->isNeeded();

        // Suwakowi oddaje się kolumnę **z rozdziału**, a nie kładzie go na treści.
        // `ListView` kładzie, bo jego prawa kolumna niesie krótką wartość, która
        // i tak kończy się przed krawędzią; w tabeli prawa kolumna jest daną
        // dosuniętą do brzegu i suwak przykryłby jej ostatni znak.
        $content = $scrolls ? $bounds->columnsFrom(0, $bounds->columns - 1) : $bounds;

        if ($content->isEmpty()) {
            return [];
        }

        $widths = Distribution::of(array_map(static fn (Column $column): Span => $column->span, $this->columns), $content->columns);
        $last = self::lastVisible($widths);

        if ($last === null) {
            return [];
        }

        $primitives = $this->header($content, $widths, $last);
        $offset = $this->withHeader ? 1 : 0;
        $capacity = self::capacityOf($content, $this->withHeader);

        foreach (array_slice($this->rows, 0, $capacity) as $index => $row) {
            foreach ($this->line($bounds, $content, $widths, $last, $row, $index, $offset) as $primitive) {
                $primitives[] = $primitive;
            }
        }

        if ($scrolls) {
            $primitives[] = new Scrollbar(
                new Rect($bounds->row + $offset, $bounds->right(), $capacity, 1),
                $position,
            );
        }

        return $primitives;
    }

    /**
     * Nazwy kolumn rolą `Muted` — nagłówek jest opisem tabeli, nie jej treścią,
     * więc nie ma prawa konkurować wzrokowo z wierszami.
     *
     * @param list<int> $widths
     *
     * @return list<Primitive>
     */
    private function header(Rect $content, array $widths, int $last): array
    {
        if (!$this->withHeader) {
            return [];
        }

        $labels = [];

        foreach ($this->columns as $column) {
            $labels[] = $column->label;
        }

        return $this->cells($content->line(0), $widths, $last, new TableRow($labels, Role::Muted), Role::Muted);
    }

    /**
     * Jeden wiersz treści wraz z paskiem pod kursorem, jeśli to on.
     *
     * Pasek idzie na **cały** prostokąt, także pod kolumnę suwaka — tak samo, jak
     * w `ListView` od kroku 18. Zaznaczenie kończące się na wysokości suwaka
     * wyglądałoby jak niedokończone.
     *
     * @param list<int> $widths
     *
     * @return list<Primitive>
     */
    private function line(Rect $bounds, Rect $content, array $widths, int $last, TableRow $row, int $index, int $offset): array
    {
        $primitives = [];
        $role = $row->role;

        if ($index === $this->selected) {
            foreach (Highlight::under($bounds->line($index + $offset)) as $primitive) {
                $primitives[] = $primitive;
            }

            // Rola zaznaczenia wygrywa z rolą kolumny: wyszarzony rozmiar na
            // pasku zaznaczenia zniknąłby z widoku.
            $role = Role::SelectionText;
        }

        foreach ($this->cells($content->line($index + $offset), $widths, $last, $row, $role) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    /**
     * Komórki jednego wiersza, każda w swoim wycinku.
     *
     * @param list<int> $widths
     *
     * @return list<Primitive>
     */
    private function cells(Rect $line, array $widths, int $last, TableRow $row, Role $role): array
    {
        $primitives = [];
        $column = 0;

        foreach ($widths as $index => $width) {
            $text = $row->cells[$index] ?? '';

            if ($width > 0 && $text !== '') {
                $cell = $line->columnsFrom($column, $width);
                $room = $index === $last ? $width : $width - self::GAP;

                foreach ($this->cell($cell, $this->columns[$index], $text, $room, $role, $row->marks[$index] ?? []) as $primitive) {
                    $primitives[] = $primitive;
                }
            }

            $column += $width;
        }

        return $primitives;
    }

    /**
     * Napis jednej komórki, przycięty i dosunięty do swojej krawędzi.
     *
     * Przycięcie idzie przez `Label::fit()`, a nie przez własny rachunek — jeden
     * znak wielokropka w całej aplikacji, ustalony w kroku 18.
     *
     * **Dosunięcie do prawej liczy się względem miejsca na treść, a nie względem
     * całej kolumny**, i to jest jedyna nieoczywistość w tej klasie. Odstęp od
     * sąsiada leży po **prawej** stronie komórki, bo to z prawej stoi następna
     * kolumna; dosunięcie do jej brzegu skleiłoby rozmiar z datą dokładnie
     * wtedy, gdy rozmiar jest najdłuższy — czyli w przypadku, w którym najbardziej
     * potrzeba go odróżnić.
     *
     * @param list<TextSpan> $marks zakresy do podświetlenia, liczone w znakach
     *                              **pełnej** treści komórki
     *
     * @return list<Primitive>
     */
    private function cell(Rect $cell, Column $column, string $text, int $room, Role $role, array $marks): array
    {
        $fitted = Label::fit($text, $room);

        if ($fitted === '') {
            return [];
        }

        $shift = $column->align === Align::Right ? $room - mb_strlen($fitted) : 0;
        $at = $cell->column + max(0, $shift);

        return [
            new TextRun(
                $cell->row,
                $at,
                $fitted,
                $role === Role::SelectionText ? $role : ($column->role ?? $role),
            ),
            ...$this->marks($cell->row, $at, $text, $fitted, $room, $marks),
        ];
    }

    /**
     * Podświetlenia jednej komórki — po jednym prymitywie na zakres, który
     * przetrwał przycięcie.
     *
     * Zakresy przycina się do treści **zachowanej**, a nie do napisu, który
     * wyszedł z `Label::fit()`: ten drugi kończy się wielokropkiem, a wielokropek
     * nie jest dopasowaniem. Bez tego rozróżnienia nazwa ucięta w środku
     * dopasowania malowałaby tło pod znakiem, którego w treści nie ma.
     *
     * @param list<TextSpan> $marks
     *
     * @return list<Primitive>
     */
    private function marks(int $row, int $at, string $text, string $fitted, int $room, array $marks): array
    {
        if ($marks === []) {
            return [];
        }

        $kept = mb_strlen($text) > $room ? max(0, $room - 1) : mb_strlen($fitted);
        $primitives = [];

        foreach ($marks as $mark) {
            $clipped = $mark->clippedTo($kept);

            if ($clipped === null) {
                continue;
            }

            $primitives[] = new TextMark(
                $row,
                $at + $clipped->offset,
                mb_substr($fitted, $clipped->offset, $clipped->length),
                self::MARK_TEXT,
                self::MARK_GROUND,
            );
        }

        return $primitives;
    }

    /**
     * Ostatnia kolumna, która przetrwała rozdział — to jej nie odbiera się
     * odstępu. `null` znaczy, że nie przetrwała żadna.
     *
     * @param list<int> $widths
     */
    private static function lastVisible(array $widths): ?int
    {
        $last = null;

        foreach ($widths as $index => $width) {
            if ($width > 0) {
                $last = $index;
            }
        }

        return $last;
    }
}
