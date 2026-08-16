<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation\Overlay;

use Closure;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Presentation\Ui\Component\Dialog;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\ListView;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Wybór jednej pozycji z listy **danych** — okno modułu (krok 54, D94).
 *
 * **Nie jest to `ChoiceOverlay` i nie mogło nim być.** Okno rdzenia mówi o sobie
 * wprost: *„przewijania nie ma i nie ma go po co być: lista odpowiedzi mieści się
 * w oknie, bo pytanie o kilkanaście odpowiedzi nie jest pytaniem"* — a jego
 * `drawItems()` ucina nadmiar `array_slice`iem **milczkiem**. To założenie jest
 * prawdziwe dla odpowiedzi („nadpisz / pomiń / przerwij") i **fałszywe dla
 * danych**: maszyna z czterdziestoma obrazami albo klaster z trzydziestoma
 * wdrożeniami gubiłby pozycje bez śladu, a użytkownik zobaczyłby listę, która po
 * prostu się kończy.
 *
 * Drugą różnicą jest pochodzenie napisów. `ChoiceOverlay` bierze pozycje jako
 * **klucze katalogu**, bo odpowiedzi są tekstem interfejsu; nazwa obrazu jest
 * daną i przez katalog nie przechodzi (reguła 7 mówi o tekstach widocznych dla
 * użytkownika, a nie o wartościach, które aplikacja wyłącznie pokazuje).
 *
 * Rdzeń **nie rośnie o ani jedną linię**: okno znające dane modułu mieszka w jego
 * `Presentation/Overlay` (reguła 11, precedens `FilterOverlay` z kroku 30),
 * a złożone jest z tego samego, co `ChoiceOverlay` — `Dialog` i `ListView` —
 * plus `ScrollWindow` z kroku 18. Jedno okno obsługuje **oba** wybory czynności
 * `k8s.deploy-image`: obraz i wdrożenie.
 *
 * `Esc` znaczy **wycofanie się**, a nie odpowiedź ostatnią (różnica wobec okna
 * rdzenia): pozycje są danymi, więc żadnej z nich nie da się z góry uznać za
 * „przerwij".
 */
final class PickOverlay implements OverlayInterface
{
    private const ID = 'k8s.pick';

    /** Obwódka u góry, wiersz tytułu, obwódka u dołu. */
    private const CHROME_ROWS = 3;

    /** Wiersz, od którego zaczyna się lista — pod tytułem. */
    private const FIRST_ROW = 2;

    private const PADDING_COLUMNS = 2;

    private const MARGIN_ROWS = 4;

    private const MARGIN_COLUMNS = 4;

    /** Szerokość, na jaką okno się otwiera, gdy tytuł i pozycje są krótsze. */
    private const ROOM_COLUMNS = 40;

    /** Górna granica szerokości — ta sama reguła, co w oknach rdzenia. */
    private const MAX_COLUMNS = 76;

    private int $selected = 0;

    private readonly ScrollWindow $window;

    /** Ile wierszy zmieściło się w ostatniej klatce — jedyna droga do „strona w dół”. */
    private int $lastCapacity = 1;

    /**
     * @param list<PickItem>                  $items      pozycje; pusta lista nie ma prawa tu dojść —
     *                                                    okno bez pozycji nie otwiera się (wzorem menu
     *                                                    z kroku 32), a wołający mówi wtedy zdaniem
     * @param array<string, string|int|float> $parameters dane do podstawienia w tytule
     * @param Closure(PickItem): OverlayOutcome $onPick   co zrobić z wybraną pozycją
     * @param Closure(): OverlayOutcome         $onCancel co zrobić przy wycofaniu — czynność stoi
     *                                                    w środku łańcucha, więc `Esc` musi mieć
     *                                                    czym po sobie posprzątać
     */
    public function __construct(
        private readonly string $titleKey,
        private readonly array $parameters,
        private readonly array $items,
        private readonly Closure $onPick,
        private readonly Closure $onCancel,
        private readonly TranslatorPort $translator,
    ) {
        $this->window = new ScrollWindow();
        $this->window->useContext(self::ID);
    }

    public function id(): string
    {
        return self::ID;
    }

    public function bounds(int $rows, int $columns): Rect
    {
        $height = min(count($this->items) + self::CHROME_ROWS, max(1, $rows - self::MARGIN_ROWS));
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
        if ($bounds->rows < 2 || $bounds->columns < 2) {
            return [];
        }

        $primitives = (new Dialog($this->title(), []))->draw($bounds);
        $list = $bounds
            ->inset(0, self::PADDING_COLUMNS)
            ->rowsFrom(self::FIRST_ROW, $bounds->rows - self::CHROME_ROWS);

        if ($list->isEmpty()) {
            return $primitives;
        }

        foreach ($this->drawItems($list) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    public function bindings(): array
    {
        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'choice.key.pick', 'choice.key.pick.short'),
            KeyBinding::of([Key::Enter], 'choice.key.answer', 'choice.key.answer.short'),
            KeyBinding::of([Key::Escape], 'choice.key.cancel', 'choice.key.cancel.short'),
        ];
    }

    public function handle(KeyPress $key): OverlayOutcome
    {
        return match ($key->key) {
            Key::ArrowUp => $this->move(-1),
            Key::ArrowDown => $this->move(1),
            Key::PageUp => $this->move(-$this->lastCapacity),
            Key::PageDown => $this->move($this->lastCapacity),
            Key::Home => $this->moveTo(0),
            Key::End => $this->moveTo(count($this->items) - 1),
            Key::Enter => $this->pick(),
            Key::Escape => ($this->onCancel)(),
            default => OverlayOutcome::ignored(),
        };
    }

    /** @return list<Primitive> */
    private function drawItems(Rect $bounds): array
    {
        $count = count($this->items);
        $capacity = max(1, $bounds->rows);
        $offset = $this->window->keepVisible($this->selected, $count, $capacity);
        $this->lastCapacity = $capacity;

        $rows = [];

        foreach (array_slice($this->items, $offset, $capacity) as $item) {
            $rows[] = new ListRow($item->label, $item->detail);
        }

        return (new ListView(
            $rows,
            $this->selected - $offset,
            $this->window->position($count, $capacity),
        ))->draw($bounds);
    }

    /** Szerokość, przy której najdłuższa pozycja i tytuł mieszczą się w całości. */
    private function width(): int
    {
        $widest = mb_strlen($this->title());

        foreach ($this->items as $item) {
            $length = mb_strlen($item->label) + ($item->detail === '' ? 0 : mb_strlen($item->detail) + 1);
            $widest = max($widest, $length);
        }

        return min(max($widest, self::ROOM_COLUMNS), self::MAX_COLUMNS) + 2 * self::PADDING_COLUMNS;
    }

    private function move(int $delta): OverlayOutcome
    {
        return $this->moveTo($this->selected + $delta);
    }

    private function moveTo(int $index): OverlayOutcome
    {
        $count = count($this->items);

        if ($count === 0) {
            return OverlayOutcome::stay();
        }

        $this->selected = max(0, min($index, $count - 1));

        return OverlayOutcome::stay();
    }

    private function pick(): OverlayOutcome
    {
        $item = $this->items[$this->selected] ?? null;

        return $item === null ? OverlayOutcome::stay() : ($this->onPick)($item);
    }

    private function title(): string
    {
        return $this->translator->translate($this->titleKey, $this->parameters);
    }
}
