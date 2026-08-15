<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Overlay;

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

/**
 * Okno wyboru: pytanie o **więcej niż dwie odpowiedzi** (krok 42, D79
 * rozstrzygnięcie 4).
 *
 * Piąte okno rdzenia i — jak trzy poprzednie — **nie powołuje ani jednego
 * komponentu**: to `Dialog` z kroku 18 i `ListView` z tego samego kroku, złożone
 * dokładnie tak, jak składa je `MenuOverlay`. Nowy jest sposób ich użycia
 * i jedno rozstrzygnięcie: pozycje przychodzą **kluczami katalogu**, a nie
 * napisami, więc okno nadaje się do każdego pytania, nie tylko do tego, dla
 * którego powstało.
 *
 * Dlaczego nie `ConfirmOverlay` z trzecim przyciskiem: okno potwierdzenia oddaje
 * „tak” albo „nie” i cała jego budowa na tym stoi — ognisko startujące na
 * odmowie, `Esc` znaczący „nie”, dwa przyciski w wierszu. Kolizja nazw ma sześć
 * odpowiedzi (nadpisz, nadpisz wszystkie, pomiń, pomiń wszystkie, zmień nazwę,
 * przerwij), a sześciu przycisków nie da się ustawić w wierszu wąskiego okna ani
 * obsłużyć strzałkami tak, żeby dało się to zapamiętać.
 *
 * Wynik wraca **domknięciem** (D56), jak z każdego okna, i jest nim
 * `OverlayOutcome` — bo odpowiedź bywa początkiem czegoś dalszego: „zmień nazwę”
 * otwiera okno nazwy, a „nadpisz” wraca do okna postępu. Okno nie wie, co
 * uruchamia.
 *
 * **Odpowiedzi domyślnej nie ma i to jest różnica wobec okna potwierdzenia.**
 * Ognisko startuje na pozycji pierwszej, a nie na najbezpieczniejszej, bo lista
 * odpowiedzi nie ma porządku „groźna — bezpieczna”; za to `Esc` znaczy tyle, co
 * odpowiedź ostatnia — a tę wołający ustawia na wycofaniu się.
 */
final class ChoiceOverlay implements OverlayInterface
{
    private const ID = 'choice';

    /** Obwódka u góry, wiersz tytułu, obwódka u dołu. */
    private const CHROME_ROWS = 3;

    /** Wiersz, od którego zaczyna się lista — pod tytułem. */
    private const FIRST_ROW = 2;

    private const PADDING_COLUMNS = 2;

    private const MARGIN_ROWS = 2;

    private const MARGIN_COLUMNS = 4;

    /** Szerokość, na jaką okno się otwiera, gdy tytuł i odpowiedzi są krótsze. */
    private const ROOM_COLUMNS = 36;

    /** Górna granica szerokości — ta sama reguła i ten sam powód, co w `PromptOverlay`. */
    private const MAX_COLUMNS = 64;

    private int $selected = 0;

    /** @var list<string> identyfikatory odpowiedzi, w kolejności pokazywania */
    private readonly array $ids;

    /** @var list<string> etykiety odpowiedzi, już przetłumaczone */
    private readonly array $labels;

    /**
     * @param string                            $titleKey klucz katalogu, nie napis
     * @param array<string, string>             $parameters dane do podstawienia w tytule
     * @param array<string, string>             $options  identyfikator odpowiedzi → klucz jej etykiety;
     *                                                    kolejność zapisu jest kolejnością na liście,
     *                                                    a **ostatnia odpowiedź jest tą, którą znaczy `Esc`**
     * @param Closure(string): OverlayOutcome   $onChoice co zrobić z wybraną odpowiedzią
     */
    public function __construct(
        private readonly string $titleKey,
        private readonly array $parameters,
        array $options,
        private readonly Closure $onChoice,
        private readonly TranslatorPort $translator,
    ) {
        $this->ids = array_keys($options);
        $this->labels = array_values(array_map(
            static fn (string $key): string => $translator->translate($key),
            $options,
        ));
    }

    public function id(): string
    {
        return self::ID;
    }

    /** Okno staje pośrodku, jak pytanie — dotyczy wpisu, a nie miejsca na liście. */
    public function bounds(int $rows, int $columns): Rect
    {
        $height = min(count($this->ids) + self::CHROME_ROWS, max(1, $rows - self::MARGIN_ROWS));
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
            Key::ArrowUp => $this->pick(-1),
            Key::ArrowDown => $this->pick(1),
            Key::Enter => $this->answer($this->selected),
            // `Esc` znaczy odpowiedź ostatnią, a nie „zamknij okno bez odpowiedzi”:
            // praca stoi i czeka, więc okno zamknięte milczkiem zostawiłoby ją
            // stojącą na zawsze.
            Key::Escape => $this->answer(count($this->ids) - 1),
            // Wszystko inne należy do klawiszy globalnych albo do nikogo — pola
            // tekstowego tu nie ma, więc litera nie jest treścią.
            default => OverlayOutcome::ignored(),
        };
    }

    /** @return list<Primitive> */
    private function drawItems(Rect $bounds): array
    {
        $rows = [];

        // Przewijania nie ma i nie ma go po co być: lista odpowiedzi mieści się
        // w oknie, bo pytanie o kilkanaście odpowiedzi nie jest pytaniem.
        foreach (array_slice($this->labels, 0, $bounds->rows) as $label) {
            $rows[] = new ListRow($label);
        }

        return (new ListView($rows, $this->selected))->draw($bounds);
    }

    /** Szerokość, przy której najdłuższa odpowiedź i tytuł mieszczą się w całości. */
    private function width(): int
    {
        $widest = mb_strlen($this->title());

        foreach ($this->labels as $label) {
            $widest = max($widest, mb_strlen($label));
        }

        return min(max($widest, self::ROOM_COLUMNS), self::MAX_COLUMNS) + 2 * self::PADDING_COLUMNS;
    }

    private function pick(int $delta): OverlayOutcome
    {
        $count = count($this->ids);

        if ($count === 0) {
            return OverlayOutcome::stay();
        }

        $this->selected = max(0, min($this->selected + $delta, $count - 1));

        return OverlayOutcome::stay();
    }

    private function answer(int $index): OverlayOutcome
    {
        $id = $this->ids[$index] ?? null;

        return $id === null ? OverlayOutcome::stay() : ($this->onChoice)($id);
    }

    private function title(): string
    {
        return $this->translator->translate($this->titleKey, $this->parameters);
    }
}
