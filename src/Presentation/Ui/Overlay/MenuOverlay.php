<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Overlay;

use LightManager\Application\Command\AppliesToSelection;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandRegistry;
use LightManager\Application\Command\CommandTransition;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Domain\ValueObject\Message;
use LightManager\Presentation\Ui\Component\Dialog;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\ListView;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Menu kontekstowe: co da się zrobić z tym, co jest zaznaczone (krok 32).
 *
 * Okno jest **widokiem na rejestr komend, a nie drugim rejestrem działań** —
 * i to jest jedyny warunek, pod którym w ogóle powstało. Pozycje bierze
 * z `CommandRegistry`, wybraną wywołuje `execute()`, czyli tą samą linią, co
 * okno komend. Dopisanie komendy modułu nadal kosztuje jedną zmianę w module
 * i zero w rdzeniu (reguła 15): komenda, która zadeklaruje `AppliesToSelection`,
 * pojawia się tu sama.
 *
 * Ponad okno komend menu wnosi dokładnie dwie rzeczy: **wybór bez pisania**
 * i **zawężenie do zaznaczenia**. Wszystko poza tym — podpowiedzi, uzupełnianie
 * `Tab`em, historia, argumenty — zostaje tam, gdzie było, bo tam ma pole
 * tekstowe, w którym ma sens.
 *
 * Zaznaczenie przychodzi **przy otwarciu**, a nie co klatkę: menu zużywa
 * klawisze (reguła 10), więc dopóki stoi, zaznaczenie nie ma jak się zmienić.
 * Migawka jest przez to zarazem odpowiedzią na pytanie „czego właściwie dotyczy
 * ta lista” — tego, na czym stał kursor, gdy użytkownik nacisnął klawisz.
 *
 * Prostokąt staje **pośrodku**, jak okno potwierdzenia, i to nie jest wybór
 * estetyczny: rdzeń nie wie, gdzie moduł narysował zaznaczenie (lista czy
 * drzewo, który z dwóch paneli), a pytanie ekranu o współrzędne kursora
 * otworzyłoby `ScreenInterface` na współrzędne, których żaden kontrakt nie zna.
 *
 * Komponent nie powstał tu ani jeden — okno składa się z `Dialog`u i `ListView`,
 * dokładnie tak, jak zapowiadał plan kroku.
 */
final class MenuOverlay implements OverlayInterface
{
    private const ID = 'menu';

    /** Obwódka u góry, wiersz tytułu, obwódka u dołu. */
    private const CHROME_ROWS = 3;

    /** Wiersz, od którego zaczyna się lista — pod tytułem. */
    private const FIRST_ROW = 2;

    private const PADDING_COLUMNS = 2;

    /** Odstęp między nazwą komendy a jej opisem. */
    private const GAP_COLUMNS = 2;

    private const MARGIN_ROWS = 2;

    private const MARGIN_COLUMNS = 4;

    private readonly ScrollWindow $window;

    private int $selected = 0;

    /**
     * Komendy pasujące do zaznaczenia — wynik zawężenia zrobionego przy otwarciu.
     *
     * @var list<CommandInterface&AppliesToSelection>
     */
    private array $items = [];

    private ModuleContext $context;

    public function __construct(
        private readonly CommandRegistry $registry,
        private readonly TranslatorPort $translator,
    ) {
        $this->window = new ScrollWindow();
        $this->context = new ModuleContext();
    }

    /**
     * Zaznaczenie, którego menu dotyczy — podawane przez rdzeń tuż przed
     * otwarciem.
     *
     * Zawęża listę i zaczyna oglądanie od początku, więc zastępuje `reset()`
     * z `Resettable`: kolejność byłaby tam pułapką, bo stos okien woła `reset()`
     * **po** otwarciu i skasowałby dopiero co policzone pozycje.
     */
    public function useContext(ModuleContext $context): void
    {
        $this->context = $context;
        $this->items = $this->matching($context);
        $this->selected = 0;
        $this->window->scrollBy(-PHP_INT_MAX);
    }

    /** Czy dla zaznaczenia nie ma ani jednej czynności — wtedy okno się nie otwiera. */
    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * Zdanie zamiast pustego okna.
     *
     * Menu bez pozycji byłoby ślepą uliczką: trzeba je zamknąć, żeby dowiedzieć
     * się, że nic w nim nie było. Pasek stanu mówi to samo bez otwierania
     * czegokolwiek.
     */
    public function emptyMessage(): Message
    {
        return Message::info($this->translator->translate('menu.empty'));
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
            KeyBinding::of([Key::Enter], 'menu.key.run'),
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'menu.key.pick'),
            KeyBinding::of([Key::Escape], 'menu.key.close'),
        ];
    }

    public function handle(KeyPress $key): OverlayOutcome
    {
        return match ($key->key) {
            Key::Escape, Key::F9 => OverlayOutcome::close(),
            Key::Enter => $this->run(),
            Key::ArrowUp => $this->pick(-1),
            Key::ArrowDown => $this->pick(1),
            // Wszystko inne należy do klawiszy globalnych albo do nikogo: menu
            // nie ma pola tekstowego, więc litera nie jest tu treścią.
            default => OverlayOutcome::ignored(),
        };
    }

    /**
     * Komendy, które mają sens dla tego zaznaczenia — w kolejności, w jakiej
     * trzyma je rejestr, czyli alfabetycznie.
     *
     * @return list<CommandInterface&AppliesToSelection>
     */
    private function matching(ModuleContext $context): array
    {
        $items = [];

        foreach ($this->registry->all() as $command) {
            if ($command instanceof AppliesToSelection && $command->appliesTo($context)) {
                $items[] = $command;
            }
        }

        return $items;
    }

    /** @return list<Primitive> */
    private function drawItems(Rect $bounds): array
    {
        $offset = $this->window->keepVisible($this->selected, count($this->items), $bounds->rows);
        $rows = [];

        foreach (array_slice($this->items, $offset, $bounds->rows) as $command) {
            $rows[] = new ListRow($command->name(), $this->descriptionOf($command));
        }

        return (new ListView(
            $rows,
            $this->selected - $offset,
            $this->window->position(count($this->items), min($bounds->rows, count($rows))),
        ))->draw($bounds);
    }

    /**
     * Szerokość, przy której najdłuższa pozycja mieści się w całości: nazwa,
     * odstęp i opis.
     */
    private function width(): int
    {
        $widest = mb_strlen($this->title());

        foreach ($this->items as $command) {
            $widest = max(
                $widest,
                mb_strlen($command->name()) + self::GAP_COLUMNS + mb_strlen($this->descriptionOf($command)),
            );
        }

        return $widest + 2 * self::PADDING_COLUMNS;
    }

    private function pick(int $delta): OverlayOutcome
    {
        $count = count($this->items);

        if ($count === 0) {
            return OverlayOutcome::stay();
        }

        $this->selected = max(0, min($this->selected + $delta, $count - 1));

        return OverlayOutcome::stay();
    }

    /**
     * Wybrana pozycja wykonuje się dokładnie tak, jak komenda o tej nazwie
     * wpisana w oknie komend — z jedną różnicą, która wynika z braku pola
     * tekstowego: `CommandTransition::Stay` znaczy tam „wiersz zostaje, jest co
     * poprawić”, a tutaj nie ma czego poprawiać, więc menu zamyka się razem
     * z komunikatem. Historii też nie dopisuje: pamięta się to, co wpisane,
     * a menu jest właśnie sposobem, żeby nie pisać.
     */
    private function run(): OverlayOutcome
    {
        $command = $this->items[$this->selected] ?? null;

        if ($command === null) {
            return OverlayOutcome::stay();
        }

        $outcome = $command->execute($command->inputFor($this->context));

        if ($outcome->transition === CommandTransition::Quit) {
            return OverlayOutcome::quit();
        }

        return $outcome->screenId === null
            ? OverlayOutcome::close($outcome->message)
            : OverlayOutcome::opens($outcome->screenId, $outcome->message);
    }

    private function title(): string
    {
        return $this->translator->translate('menu.title');
    }

    private function descriptionOf(CommandInterface $command): string
    {
        return $this->translator->translate($command->descriptionKey());
    }
}
