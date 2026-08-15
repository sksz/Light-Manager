<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Overlay;

use Closure;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Rect;
use LightManager\Presentation\Ui\Component\Dialog;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\ListView;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Widok stosu cofnięć: co się wydarzyło i co da się cofnąć (krok 44, D81 nr 6 i 8).
 *
 * Okno jest **widokiem na stos, a nie drugim stosem**: pozycje przychodzą
 * gotowe (`ListRow` z rolą), a wybrana wraca numerem do domknięcia — okno nie
 * wie, czym jest operacja, ani jak się ją cofa. Leży w module, bo zna jego
 * stan pośrednio (buduje je `EntryUndo` z wpisów dziennika) — tą samą regułą,
 * którą `FilterOverlay` leży przy panelu (krok 30).
 *
 * Pozycje nieodwracalne stoją **wyszarzone i niewybieralne**: kursor je
 * przeskakuje, a lista dzięki nim odpowiada także na pytanie „co się właściwie
 * wydarzyło” — nieodwracalność mówi rola, nie brak wpisu. Gdy odwracalnej nie
 * ma ani jednej, okno dalej ma sens jako historia, a `Enter` odmawia zdaniem.
 *
 * Cofać wolno **dowolną pozycję**, nie tylko wierzchołek — a że stan dysku
 * mógł się od tamtej pory zmienić, wykonalność sprawdza wykonawca tuż przed
 * wykonaniem i przy odmowie zapisu nie zdejmuje.
 *
 * Złożone z `Dialog`u i `ListView`, jak menu z kroku 32 — komponentu nie
 * dokłada, więc i scenariusza pomiarowego nie przynosi (reguła D48).
 */
final class UndoOverlay implements OverlayInterface
{
    private const ID = 'undo';

    /** Obwódka u góry, wiersz tytułu, obwódka u dołu. */
    private const CHROME_ROWS = 3;

    private const FIRST_ROW = 2;

    private const PADDING_COLUMNS = 2;

    private const MARGIN_ROWS = 2;

    private const MARGIN_COLUMNS = 4;

    private readonly ScrollWindow $window;

    private int $selected = 0;

    /**
     * @param list<ListRow>              $rows       pozycje stosu, najnowsza pierwsza
     * @param list<bool>                 $selectable czy pozycję wolno cofnąć — równoległa do `$rows`
     * @param Closure(int): OverlayOutcome $onPick   cofnięcie wybranej pozycji
     */
    public function __construct(
        private readonly array $rows,
        private readonly array $selectable,
        private readonly Closure $onPick,
        private readonly TranslatorPort $translator,
    ) {
        $this->window = new ScrollWindow();
        $this->selected = $this->firstSelectable();
    }

    public function id(): string
    {
        return self::ID;
    }

    public function bounds(int $rows, int $columns): Rect
    {
        $height = min(count($this->rows) + self::CHROME_ROWS, max(1, $rows - self::MARGIN_ROWS));
        $width = min($this->width(), max(1, $columns - self::MARGIN_COLUMNS));

        return new Rect(
            max(0, intdiv($rows - $height, 2)),
            max(0, intdiv($columns - $width, 2)),
            $height,
            $width,
        );
    }

    public function draw(Rect $bounds): array
    {
        $primitives = (new Dialog($this->title(), []))->draw($bounds);
        $list = $bounds
            ->inset(0, self::PADDING_COLUMNS)
            ->rowsFrom(self::FIRST_ROW, $bounds->rows - self::CHROME_ROWS);

        if ($list->isEmpty()) {
            return $primitives;
        }

        $offset = $this->window->keepVisible($this->selected, count($this->rows), $list->rows);

        foreach ((new ListView(
            array_slice($this->rows, $offset, $list->rows),
            $this->selected - $offset,
            $this->window->position(count($this->rows), min($list->rows, count($this->rows))),
        ))->draw($list) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    public function bindings(): array
    {
        $bindings = [];

        if ($this->hasSelectable()) {
            $bindings[] = KeyBinding::of([Key::Enter], 'module.browser.undo.key.run');
        }

        $bindings[] = KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'module.browser.undo.key.pick');
        $bindings[] = KeyBinding::of([Key::Escape], 'module.browser.undo.key.close');

        return $bindings;
    }

    public function handle(KeyPress $key): OverlayOutcome
    {
        return match ($key->key) {
            Key::Escape, Key::F3 => OverlayOutcome::close(),
            Key::Enter => $this->run(),
            Key::ArrowUp => $this->pick(-1),
            Key::ArrowDown => $this->pick(1),
            default => OverlayOutcome::ignored(),
        };
    }

    private function title(): string
    {
        return $this->translator->translate('module.browser.undo.title');
    }

    /**
     * Krok kursora z przeskokiem pozycji niewybieralnych — w stronę ruchu,
     * a gdy dalej nic nie ma, kursor zostaje, gdzie stał.
     */
    private function pick(int $delta): OverlayOutcome
    {
        $count = count($this->rows);
        $next = $this->selected + $delta;

        while ($next >= 0 && $next < $count && !($this->selectable[$next] ?? false)) {
            $next += $delta;
        }

        if ($next >= 0 && $next < $count) {
            $this->selected = $next;
        }

        return OverlayOutcome::stay();
    }

    private function run(): OverlayOutcome
    {
        if (!($this->selectable[$this->selected] ?? false)) {
            return OverlayOutcome::stay(); // sama historia — cofać nie ma czego
        }

        return ($this->onPick)($this->selected);
    }

    private function firstSelectable(): int
    {
        foreach ($this->selectable as $index => $ok) {
            if ($ok) {
                return $index;
            }
        }

        return 0;
    }

    private function hasSelectable(): bool
    {
        return in_array(true, $this->selectable, true);
    }

    private function width(): int
    {
        $widest = mb_strlen($this->title());

        foreach ($this->rows as $row) {
            $widest = max($widest, mb_strlen($row->left) + (mb_strlen($row->right) > 0 ? 2 + mb_strlen($row->right) : 0));
        }

        return $widest + 2 * self::PADDING_COLUMNS;
    }
}
