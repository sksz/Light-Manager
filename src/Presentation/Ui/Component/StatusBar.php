<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Primitive\Bar;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Primitive\Weight;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Presentation\Ui\ComponentInterface;
use LightManager\Presentation\Ui\HintTarget;
use LightManager\Presentation\Ui\StatusHints;

/**
 * Pasek stanu: komunikat po lewej w kolorze swojego tonu, podpowiedzi klawiszy
 * po prawej, między nimi pionowa przegroda.
 *
 * Podpowiedzi ustępują komunikatowi: w wąskim oknie długi błąd jest ważniejszy
 * od przypomnienia, gdzie jest wyjście. Bez tej reguły oba napisy nachodziły na
 * siebie literami. Krok 40 tej reguły **nie rusza** — dokłada ponad nią drugą,
 * o ustępowaniu wewnątrz samych podpowiedzi (`StatusHints`).
 *
 * Od kroku 40 pasek rysuje **tyle wierszy, ile dostał prostokątem**, a nie jeden.
 * Wiersz drugi jest w całości podpowiedzi: komunikat zostaje w pierwszym, bo to
 * on ma być przeczytany od razu, a jego miejsce nie ma prawa zależeć od tego, ile
 * klawiszy akurat działa. O tym, czy pasek dostanie ten drugi wiersz, rozstrzyga
 * `HudLayout` — pytany wcześniej przez `FrameComposer`, bo wysokość strefy zależy
 * odtąd od treści.
 */
final class StatusBar implements ComponentInterface
{
    /** Oddech między komunikatem a przegrodą — poniżej niego podpowiedzi znikają. */
    private const GAP_COLUMNS = 2;

    public function __construct(
        private readonly string $message = '',
        private readonly Role $tone = Role::Info,
        private readonly StatusHints $hints = new StatusHints(),
    ) {
    }

    /**
     * Ile kolumn zostaje podpowiedziom w wierszu, w którym stoi komunikat.
     *
     * Rachunek jest publiczny, bo potrzebują go **dwa** miejsca i nie wolno im
     * się rozjechać: tu — przy rysowaniu, i w `FrameComposer` — przy pytaniu, czy
     * pasek ma urosnąć do dwóch wierszy. Odpowiedź na to pytanie musi paść
     * **przed** podziałem okna, czyli zanim ten prostokąt w ogóle powstanie.
     */
    public static function hintColumns(int $columns, string $message): int
    {
        if ($message === '') {
            return max(0, $columns);
        }

        return max(0, $columns - mb_strlen(Label::fit($message, $columns)) - self::GAP_COLUMNS);
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $primitives = [];
        $message = $this->message === '' ? '' : Label::fit($this->message, $bounds->columns);

        if ($message !== '') {
            $primitives[] = new TextRun($bounds->row, $bounds->column, $message, $this->tone);
        }

        foreach ($this->hints->lines(self::budgets($bounds, $this->message)) as $index => $line) {
            // Wiersz bez ani jednej pozycji jest legalny i znaczy „tu się nic nie
            // zmieściło” — przy długim komunikacie podpowiedzi zaczynają się
            // dopiero w drugim wierszu. Numer wiersza pochodzi z położenia na
            // liście, więc pustego nie wolno pominąć milczeniem.
            if ($line === '') {
                continue;
            }

            $row = $bounds->row + $index;
            $column = self::lineColumn($bounds, $line);
            $primitives[] = new TextRun($row, $column, $line, Role::Muted);

            if ($index === 0) {
                $primitives[] = new Bar(new Rect($row, $column - 1, 1, 1), Role::Border, Weight::Hairline);
            }
        }

        return $primitives;
    }

    /**
     * Prostokąty poszczególnych podpowiedzi — mapa trafień stopki (krok 55).
     *
     * Statyczne i tutaj, a nie w `FrameComposer`, z tego samego powodu, co
     * `hintColumns()`: wyrównanie treści do prawej krawędzi jest własnością
     * **tego** komponentu, a `draw()` i mapa trafień nie mają prawa liczyć go
     * dwiema drogami. Wołający dostaje gotowe prostokąty w siatce znakowej.
     *
     * @return list<HintTarget>
     */
    public static function hintTargets(Rect $bounds, string $message, StatusHints $hints): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $budgets = self::budgets($bounds, $message);
        $lines = $hints->lines($budgets);
        $targets = [];

        foreach ($hints->placements($budgets) as $placement) {
            $line = $lines[$placement->line] ?? null;

            if ($line === null || $line === '') {
                continue;
            }

            $targets[] = new HintTarget(
                new Rect(
                    $bounds->row + $placement->line,
                    self::lineColumn($bounds, $line) + $placement->offset,
                    1,
                    $placement->length,
                ),
                $placement->binding,
            );
        }

        return $targets;
    }

    /** Pierwsza kolumna wiersza podpowiedzi: treść stopki jest wyrównana do prawej. */
    private static function lineColumn(Rect $bounds, string $line): int
    {
        return $bounds->column + $bounds->columns - mb_strlen($line);
    }

    /**
     * Budżet kolumn każdego wiersza: pierwszy dzieli się z komunikatem, kolejne
     * dostają całą szerokość.
     *
     * @return list<int>
     */
    private static function budgets(Rect $bounds, string $message): array
    {
        $budgets = [self::hintColumns($bounds->columns, $message)];

        for ($row = 1; $row < $bounds->rows; ++$row) {
            $budgets[] = max(0, $bounds->columns);
        }

        return $budgets;
    }
}
