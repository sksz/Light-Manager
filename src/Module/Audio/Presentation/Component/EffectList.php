<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Presentation\Component;

use LightManager\Application\Event\EventDeclaration;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\EffectMap;
use LightManager\Presentation\Ui\Component\Column;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\Table;
use LightManager\Presentation\Ui\Component\TableRow;
use LightManager\Presentation\Ui\ComponentInterface;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Spis zdarzeń wraz z przypisanymi plikami — lewy panel okna dźwięku (krok 46).
 *
 * **Komponentu rdzenia nie dokłada**, i to jest ten sam sprawdzian, który przeszła
 * playlista w kroku 45: wiersz o wielu kolumnach stoi w rdzeniu od kroku 27, więc
 * zostaje złożenie kolumn i wierszy. Komponent leży w katalogu modułu, bo zna
 * `EffectMap` — typ jego domeny (reguła 11).
 *
 * **Spis składa się ze słownika, a nie z mapy**, i ta kolejność jest tu całą
 * treścią: pokazujemy wszystkie zdarzenia, jakie aplikacja zna, a mapa odpowiada
 * wyłącznie na pytanie „co jest przypisane". Odwrotnie — spis z mapy — pokazywałby
 * wyłącznie to, co już zrobione, a użytkownik nie miałby skąd wiedzieć, co jeszcze
 * da się przypisać. Stąd wiersz nieprzypisany zostaje w spisie, wyszarzony
 * i z kreską zamiast nazwy pliku (rozstrzygnięcie użytkownika).
 *
 * Kolumny są trzy i **znacznik stoi pierwszy**, jak w liście plików: jest jedynym
 * miejscem, w którym widać różnicę między „nic nie przypisano" a „przypisano
 * i wyciszono".
 */
final class EffectList implements ComponentInterface
{
    /** Znak stanu plus odstęp od nazwy — dokładnie jak kolumna zaznaczenia w liście plików. */
    private const MARK_WIDTH = 2;

    /**
     * Nazwa zdarzenia poniżej dwudziestu znaków przestaje być nazwą: `Usunięcie
     * trwałe: zakoń…` mówi mniej niż nic.
     */
    private const EVENT_MINIMUM = 20;

    /**
     * Nazwa pliku: kolumna **stała, ustępująca pierwsza** — nie elastyczna.
     *
     * Wybór wyszedł z rachunku, a nie z upodobania. Dwie kolumny elastyczne dzielą
     * resztę po połowie, więc w panelu podziału (50 kolumn na panel) nazwa
     * zdarzenia dostawałaby 23 znaki — a najdłuższa z nich, „Usunięcie trwałe:
     * zakończone”, ma 28. Wiersz mówiłby wtedy „Usunięcie trwałe: zak…”, czyli nie
     * odróżniałby udanego od nieudanego. Kolumna stała oddaje miejsce nazwie, a gdy
     * zabraknie go jej samej, **znika w całości** (reguła 11e) i zostaje znacznik,
     * który i tak mówi, czy cokolwiek jest przypisane.
     *
     * Szesnaście znaków mieści `success_bell.wav` co do znaku — i tyle właśnie
     * zostaje, bo kolumna stoi ostatnia i odstępu sąsiadowi nie oddaje (11e).
     * Nazwie zdarzenia zostaje wtedy w panelu podziału 29 znaków treści, czyli
     * o jeden więcej, niż ma najdłuższa z nich.
     */
    private const FILE_WIDTH = 16;

    /**
     * @param list<EventDeclaration> $events słownik — **cały**, nie tylko przypisany
     */
    public function __construct(
        private readonly array $events,
        private readonly EffectMap $map,
        private readonly ScrollWindow $window,
        private readonly TranslatorPort $translator,
        private readonly int $selected,
        /** Czy panel siedzi w podziale, czyli wewnątrz własnej obwódki. */
        private readonly bool $framed = false,
    ) {
    }

    public function draw(Rect $bounds): array
    {
        $inner = $this->framed ? Panel::inner($bounds) : $bounds;

        if ($inner->isEmpty()) {
            return [];
        }

        if ($this->events === []) {
            // Nieosiągalne, dopóki rdzeń wnosi swoje pięć zdarzeń — ale spis
            // składa się ze słownika, a ten jest budowany przy starcie, więc
            // pusty jest stanem, a nie niemożliwością.
            return (new Label($this->text('effects.empty'), '', Role::Muted))->draw($inner->line(0));
        }

        $total = count($this->events);
        $offset = $this->window->keepVisible($this->selected, $total, $inner->rows);

        return (new Table(
            $this->columns(),
            array_slice($this->rows(), $offset, $inner->rows),
            $this->selected - $offset,
            $this->window->position($total, min($inner->rows, $total)),
        ))->draw($inner);
    }

    /** @return list<Column> */
    private function columns(): array
    {
        return [
            Column::fixed(self::MARK_WIDTH, yieldOrder: 2),
            Column::flexible(self::EVENT_MINIMUM),
            Column::fixed(self::FILE_WIDTH, yieldOrder: 1, role: Role::Muted),
        ];
    }

    /** @return list<TableRow> */
    private function rows(): array
    {
        $rows = [];

        foreach ($this->events as $declaration) {
            $assignment = $this->map->at($declaration->name);
            $mark = match (true) {
                $assignment === null => '',
                !$assignment->enabled => $this->text('effect.muted'),
                $assignment->missing => $this->text('effect.missing'),
                default => $this->text('effect.on'),
            };

            $rows[] = new TableRow(
                [
                    $mark,
                    $this->translator->translate($declaration->labelKey),
                    $assignment === null
                        ? $this->text('effect.none')
                        : basename($assignment->path),
                ],
                // Wyszarzone jest wszystko, co **nie zagra**: brak przypisania,
                // wyciszenie i brakujący plik. Trzy różne powody, ta sama
                // odpowiedź na pytanie „czy przy tym zdarzeniu coś usłyszę”.
                $assignment !== null && $assignment->playable() ? Role::Text : Role::Muted,
            );
        }

        return $rows;
    }

    private function text(string $key): string
    {
        return $this->translator->translate('module.' . AudioSettings::ID . '.' . $key);
    }
}
