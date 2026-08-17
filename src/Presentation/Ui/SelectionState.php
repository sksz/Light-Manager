<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use LightManager\Application\Ui\Rect;

/**
 * Co jest zaznaczone na klatce — **piąta klasa pamiętająca coś między
 * klatkami** (krok 56), po `ScrollWindow` (18), `SectionState` (22),
 * `SplitState` (24) i `TreeState` (31).
 *
 * Reguła własności jest ta sama, co u czterech poprzedniczek: komponent
 * powstaje na nowo w każdej klatce (reguła 11a), więc co ma klatkę przeżyć,
 * mieszka **obok** niego. Różnica jest w tym, **kto jest właścicielem**:
 * tamte cztery należą do ekranu, a ta do rdzenia (`LoopState`) — bo zaznaczenie
 * przecina panele, ekrany i okna nakładane. Dotyczy **klatki**, a nie treści
 * któregokolwiek z nich.
 *
 * **Zaznaczenie jest prostokątne, nie przepływowe**, i nie jest to uproszczenie,
 * tylko dopasowanie do tego, czym jest ta klatka: ekran składa się z paneli
 * stojących obok siebie, więc zaznaczenie „od punktu do punktu, z całymi
 * wierszami pośrodku” przeciągnięte przez listę plików zabrałoby ze sobą obwódkę
 * panelu sąsiedniego, jego treść i pasek stanu. Prostokąt bierze dokładnie to,
 * co użytkownik obrysował. Ceną jest wiersz dłuższy od panelu, który zaznaczy
 * się do szerokości panelu, a nie do końca treści — cena jest widoczna
 * i przyjęta.
 *
 * **Kliknięcie od przeciągnięcia odróżnia ruch, a nie modyfikator.** Samo
 * naciśnięcie stawia kursor (krok 55) i kasuje poprzednie zaznaczenie;
 * prostokąt powstaje dopiero wtedy, gdy wskaźnik przesunie się o co najmniej
 * jedną komórkę. Modyfikator odpadł z dwóch powodów naraz: `Shift`+przeciągnięcie
 * jest w terminalach ucieczką do zaznaczania natywnego i ma nią zostać, a `Alt`
 * w torze terminalowym jest nieodróżnialny od `Esc` naciśniętego tuż wcześniej
 * (reguła 11j).
 */
final class SelectionState
{
    private ?int $anchorRow = null;

    private ?int $anchorColumn = null;

    private ?int $headRow = null;

    private ?int $headColumn = null;

    private bool $dragging = false;

    /** Klatka, w której zaznaczenie powstało — patrz `useFrame()`. */
    private ?string $frame = null;

    /**
     * Naciśnięcie: kotwica staje w komórce, poprzednie zaznaczenie znika.
     *
     * Zaznaczenia jeszcze **nie ma** — jest dopiero miejsce, od którego mogłoby
     * się zacząć. Dzięki temu kliknięcie w wiersz listy nie zostawia po sobie
     * prostokąta o zerowej powierzchni, którego użytkownik nie prosił.
     */
    public function begin(int $row, int $column): void
    {
        $this->anchorRow = $row;
        $this->anchorColumn = $column;
        $this->headRow = null;
        $this->headColumn = null;
        $this->dragging = true;
    }

    /**
     * Przeciągnięcie: prostokąt sięga do wskazanej komórki.
     *
     * Bez wcześniejszego naciśnięcia nie robi nic — przeciągnięcie, którego
     * początku nikt nie widział, zdarza się po zdjęciu ognisku okna albo po
     * kliknięciu w pasek stanu i nie ma od czego liczyć prostokąta.
     */
    public function extendTo(int $row, int $column): void
    {
        if (!$this->dragging || $this->anchorRow === null || $this->anchorColumn === null) {
            return;
        }

        if ($row === $this->anchorRow && $column === $this->anchorColumn) {
            // Powrót dokładnie na kotwicę znaczy „jednak nic” — inaczej
            // drgnięcie ręki tam i z powrotem zostawiałoby prostokąt o jednej
            // komórce, którego nie widać, a który liczy się jako zaznaczenie.
            $this->headRow = null;
            $this->headColumn = null;

            return;
        }

        $this->headRow = $row;
        $this->headColumn = $column;
    }

    /** Zwolnienie przycisku: prostokąt zostaje, przeciąganie się kończy. */
    public function release(): void
    {
        $this->dragging = false;
    }

    public function clear(): void
    {
        $this->anchorRow = null;
        $this->anchorColumn = null;
        $this->headRow = null;
        $this->headColumn = null;
        $this->dragging = false;
    }

    /** Czy jest co pokazywać i co odczytać. */
    public function isActive(): bool
    {
        return $this->headRow !== null && $this->headColumn !== null;
    }

    public function isDragging(): bool
    {
        return $this->dragging;
    }

    /** Prostokąt zaznaczenia w siatce znakowej albo `null`, gdy zaznaczenia nie ma. */
    public function bounds(): ?Rect
    {
        if ($this->anchorRow === null || $this->anchorColumn === null
            || $this->headRow === null || $this->headColumn === null) {
            return null;
        }

        $row = min($this->anchorRow, $this->headRow);
        $column = min($this->anchorColumn, $this->headColumn);

        return new Rect(
            $row,
            $column,
            abs($this->headRow - $this->anchorRow) + 1,
            abs($this->headColumn - $this->anchorColumn) + 1,
        );
    }

    /** Ile wierszy obejmuje zaznaczenie — jedyna liczba, którą krok mówi użytkownikowi. */
    public function rows(): int
    {
        $bounds = $this->bounds();

        return $bounds === null ? 0 : $bounds->rows;
    }

    /**
     * Klatka, na której zaznaczenie powstało — **i kasowanie, gdy ta klatka
     * przestaje być tą samą**.
     *
     * Trzy zmiany kasują zaznaczenie i wszystkie trzy widać w jednym miejscu,
     * bo składanie klatki pyta o nie co takt: **zmiana ekranu**, **otwarcie
     * okna nakładanego** i **zmiana rozmiaru okna**. Powód jest wspólny:
     * zaznaczenie jest prostokątem w siatce znakowej, a nie wskazaniem na treść,
     * więc po każdej z tych zmian wskazywałoby miejsce, którego już nie ma —
     * czyli kłamałoby, i to cicho.
     *
     * Przewijanie **nie kasuje** i jest to ta sama zasada widziana z drugiej
     * strony: zaznaczenie dotyczy klatki, więc treść przewinięta pod prostokątem
     * jest nową treścią zaznaczenia. Sięganie poza widok wymagałoby zaznaczenia
     * w pojęciach ekranu, czyli innego mechanizmu (poza zakresem kroku 56).
     */
    public function useFrame(string $screen, bool $overlayOpen, int $rows, int $columns): void
    {
        $frame = $screen . '|' . ($overlayOpen ? '1' : '0') . '|' . $rows . 'x' . $columns;

        if ($this->frame !== null && $this->frame !== $frame) {
            $this->clear();
        }

        $this->frame = $frame;
    }
}
