<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Component;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Browser\Domain\ValueObject\NameFilter;
use LightManager\Presentation\Ui\Component\Align;
use LightManager\Presentation\Ui\Component\Column;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\Table;
use LightManager\Presentation\Ui\Component\TableRow;
use LightManager\Presentation\Ui\Component\TextSpan;
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
 *
 * **Od kroku 27 lista ma cztery kolumny**, a nie dwa pola. Zmiana nie polega na
 * dołożeniu dwóch napisów: do tego kroku układ liczył się w komponencie modułu
 * (nazwa po lewej, rozmiar po prawej, `ListView` sklejał je z prostokąta),
 * a teraz liczy go `Table` z reguły wspólnej dla obu osi. Kolumny szczegółów
 * **ustępują w wąskim oknie** — prawa pierwsze, potem data, potem rozmiar —
 * a nazwa nie ustępuje nigdy.
 */
final class EntryList implements ComponentInterface
{
    private const EMPTY_DIRECTORY_KEY = 'module.browser.empty';

    /** Pusto **po zawężeniu** to co innego niż pusty katalog — i tak ma się czytać. */
    private const NO_MATCH_KEY = 'module.browser.filter.none';

    /** `2026-08-11 18:45` — szesnaście znaków plus odstęp od sąsiada. */
    private const DATE_FORMAT = 'Y-m-d H:i';

    private const DATE_WIDTH = 17;

    /** `999,9 GB` mieści się w ośmiu znakach; dziewiąty to odstęp. */
    private const SIZE_WIDTH = 9;

    /** `rwxr-xr-x` — dokładnie dziewięć znaków, bez odstępu, bo stoi ostatnia. */
    private const PERMISSIONS_WIDTH = 9;

    /**
     * Poniżej tylu kolumn nazwa przestaje być nazwą — i to ta liczba, a nie
     * szerokości kolumn stałych, rozstrzyga, kiedy szczegóły zaczynają ustępować.
     *
     * Dwadzieścia znaków mieści `light-manager.php` z zapasem, a `Infrastructure`
     * co do znaku. Gdyby minimum było niskie, w panelu podziału zostałaby nazwa
     * przycięta do „In…” obok pełnej daty i praw — czyli układ, w którym
     * najważniejsza kolumna ustępuje najmniej ważnym.
     */
    private const NAME_MINIMUM = 20;

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
        /** Czy widać kolumny daty i praw — ustawienie modułu. */
        private readonly bool $details = true,
        /** Czy nad listą stoi wiersz z nazwami kolumn — ustawienie modułu. */
        private readonly bool $header = false,
        /**
         * Fragment, którym lista jest zawężona — **tylko do podświetlenia**.
         *
         * Samo zawężenie zrobił już panel (`BrowserState`), więc komponent
         * dostaje listę gotową; filtr jest mu potrzebny wyłącznie po to, by
         * wiedzieć, którą część nazwy pokazać jako dopasowaną.
         */
        private readonly NameFilter $filter = new NameFilter(''),
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
            return $this->nothing($bounds);
        }

        $this->window->useContext($this->directory->path()->value);

        // Nagłówek zabiera wiersz **listy**, więc okno przewijania musi o nim
        // wiedzieć — inaczej ostatni wpis chowałby się pod dolną krawędzią.
        $capacity = Table::capacityOf($bounds, $this->header);
        $entries = $this->directory->entries();
        $selected = $this->directory->selection()?->index;
        $offset = $this->window->keepVisible($selected, count($entries), $capacity);
        $rows = [];

        foreach (array_slice($entries, $offset, $capacity) as $entry) {
            $rows[] = $this->row($entry);
        }

        return (new Table(
            $this->columns(),
            $rows,
            $selected === null ? null : $selected - $offset,
            $this->window->position(count($entries), min($capacity, count($rows))),
            $this->header,
        ))->draw($bounds);
    }

    /**
     * Panel bez wierszy: zdanie o tym, dlaczego jest pusty — **pod nagłówkiem
     * kolumn, a nie zamiast niego**.
     *
     * Do poprawki z 2026-08-12 zdanie zastępowało całą tabelę razem z nagłówkiem,
     * więc panel bez wyników tracił wiersz „Nazwa · Rozmiar · Zmieniony · Prawa“,
     * a przy podziale ekranu obie listy przestawały się zgadzać w pionie — jedna
     * miała nagłówek, druga nie. Pusto znaczy „nie ma wierszy“, a nie „nie ma
     * kolumn“; tabela z pustym ciałem to nadal tabela.
     *
     * @return list<Primitive>
     */
    private function nothing(Rect $bounds): array
    {
        $sentence = new Label($this->translator->translate(
            $this->filter->isEmpty() ? self::EMPTY_DIRECTORY_KEY : self::NO_MATCH_KEY,
        ));

        if (!$this->header) {
            return $sentence->draw($bounds);
        }

        $primitives = (new Table($this->columns(), [], null, null, true))->draw($bounds);

        if ($bounds->rows < 2) {
            return $primitives;
        }

        foreach ($sentence->draw($bounds->line(1)) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    /**
     * Kolumny wraz z kolejnością ustępowania.
     *
     * Kolejność jest wpisana tutaj, a nie w ustawieniach, i to jest
     * rozstrzygnięcie ze startu kroku: ustępowanie i tak musi być
     * zaprogramowane, więc przełącznik na każdą kolumnę dawałby użytkownikowi
     * władzę nad tym, co w wąskim oknie zniknie samo. Zostaje jeden przełącznik
     * — „kolumny szczegółów” — a resztą rządzi drabinka: **prawa ustępują
     * pierwsze, potem data, potem rozmiar, a nazwa nie ustępuje nigdy**.
     *
     * @return list<Column>
     */
    private function columns(): array
    {
        $columns = [
            Column::flexible(
                self::NAME_MINIMUM,
                label: $this->translator->translate('module.browser.column.name'),
            ),
            Column::fixed(
                self::SIZE_WIDTH,
                yieldOrder: 3,
                align: Align::Right,
                label: $this->translator->translate('module.browser.column.size'),
                role: Role::Muted,
            ),
        ];

        if (!$this->details) {
            return $columns;
        }

        $columns[] = Column::fixed(
            self::DATE_WIDTH,
            yieldOrder: 2,
            label: $this->translator->translate('module.browser.column.modified'),
            role: Role::Muted,
        );
        $columns[] = Column::fixed(
            self::PERMISSIONS_WIDTH,
            yieldOrder: 1,
            label: $this->translator->translate('module.browser.column.permissions'),
            role: Role::Muted,
        );

        return $columns;
    }

    /**
     * Wiersz jednego wpisu.
     *
     * Katalog nie ma rozmiaru i to jest ta sama decyzja, co przed krokiem 27:
     * liczba w tej kolumnie znaczyłaby „rozmiar i-węzła”, czyli nic użytecznego.
     * Zajętość katalogu wraz z zawartością pokazuje moduł opisu pliku, bo do jej
     * policzenia trzeba procesu tłowego (krok 26).
     */
    private function row(Entry $entry): TableRow
    {
        return new TableRow(
            [
                $entry->name . ($entry->isDirectory() ? '/' : ''),
                $entry->isDirectory() ? '' : EntrySize::of($this->translator, $entry->sizeInBytes),
                $this->formatDate($entry->modifiedAt),
                $entry->permissionsAsText(),
            ],
            $entry->isDirectory() ? Role::Accent : Role::Text,
            $this->marksIn($entry),
        );
    }

    /**
     * Zakresy dopasowania w nazwie — w kolumnie zerowej i tylko w niej.
     *
     * Ukośnik dopisany katalogowi stoi **za** nazwą, więc przesunięcia liczone
     * w nazwie zgadzają się z treścią komórki co do znaku i nie wymagają
     * przeliczenia.
     *
     * Przy pustym filtrze oddaje pustą tablicę i to jest cała cena, jaką klatka
     * bez filtra płaci za istnienie tego kroku: jedno porównanie napisu na wiersz.
     *
     * @return array<int, list<TextSpan>>
     */
    private function marksIn(Entry $entry): array
    {
        if ($this->filter->isEmpty()) {
            return [];
        }

        $spans = TextSpan::occurrencesOf($this->filter->value, $entry->name);

        return $spans === [] ? [] : [0 => $spans];
    }

    /**
     * Data zapisem `2026-08-11 18:45` — najwęższym, który jest jednoznaczny.
     *
     * Zapis nie przechodzi przez katalog napisów z rozmysłu: kolejność
     * rok-miesiąc-dzień jest sortowalna wzrokiem, ta sama w każdym języku
     * i **ma stałą szerokość**, od której zależy rozdział kolumn. Zapis zależny
     * od języka zmieniałby szerokość kolumny wraz z ustawieniem.
     */
    private function formatDate(?int $timestamp): string
    {
        return $timestamp === null ? '' : date(self::DATE_FORMAT, $timestamp);
    }
}
