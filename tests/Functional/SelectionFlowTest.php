<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\PointerButton;
use LightManager\Application\Dto\PointerEvent;
use LightManager\Application\Ui\Plane;
use LightManager\Application\Ui\Primitive\TextMark;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Presentation\Cli\FrameComposer;
use LightManager\Presentation\Cli\InputHandler;
use LightManager\Presentation\Cli\SplitSetting;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\HudLayout;
use LightManager\Tests\Support\FixedViewport;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\RecordingRenderer;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Zaznaczanie treści klatki wskaźnikiem (krok 56).
 *
 * Test patrzy na **skutek u użytkownika**: prostokąt widać na klatce, a to, co
 * pod nim, da się odczytać i jest tym samym napisem, który widać. Miara kroku —
 * „przeciągnięcie przez pięć wierszy listy zaznacza dokładnie te pięć wierszy,
 * a odczytana treść jest tym samym napisem, który widać na ekranie” — jest
 * zdaniem o przebiegu, więc tylko przebieg umie ją sprawdzić.
 */
final class SelectionFlowTest extends TestCase
{
    /** Szeroko: mieści się podział paneli i pełny pasek stanu. */
    private const COLUMNS = 160;

    private const ROWS = 24;

    private const NOW = 1000.0;

    private ScreenFixture $app;

    protected function setUp(): void
    {
        $this->app = self::fixture();
    }

    /**
     * Miara kroku: **przeciągnięcie przez pięć wierszy zaznacza te pięć
     * wierszy**, a odczytana treść jest tym, co widać na liście.
     */
    public function testDraggingAcrossFiveRowsSelectsExactlyThoseRows(): void
    {
        $list = $this->listArea();

        $this->drag($list->row, $list->column, $list->row + 4, $list->column + 11);

        self::assertSame(5, $this->app->state->selection()->rows());
        self::assertSame(
            ['wpis-00/', 'wpis-01/', 'wpis-02/', 'wpis-03/', 'wpis-04/'],
            $this->app->state->selectionText(),
        );
    }

    /**
     * Zaznaczenie **widać** — czwarta płaszczyzna klatki, złożona z `TextMark`ów
     * i wyłącznie z nich (słownik prymitywów zostaje zamknięty).
     */
    public function testTheSelectionIsDrawnAsMarksOnItsOwnPlane(): void
    {
        $list = $this->listArea();
        $this->drag($list->row, $list->column, $list->row + 2, $list->column + 7);

        $plane = $this->planeOf('selection');

        self::assertNotNull($plane, 'klatka ma płaszczyznę zaznaczenia');
        self::assertCount(3, $plane->primitives, 'jeden prymityw na wiersz');

        foreach ($plane->primitives as $primitive) {
            self::assertInstanceOf(TextMark::class, $primitive);
            self::assertSame(Role::Marquee, $primitive->ground);
            self::assertSame(Role::SelectionText, $primitive->role);
        }
    }

    /** Klatka bez zaznaczenia **nie płaci** za nie ani jedną płaszczyzną. */
    public function testAFrameWithoutASelectionHasNoSuchPlane(): void
    {
        self::assertNull($this->planeOf('selection'));
        self::assertSame([], $this->app->state->selectionText());
    }

    /** Samo kliknięcie stawia kursor i **nie zostawia** zaznaczenia. */
    public function testAClickWithoutMovementSelectsNothing(): void
    {
        $list = $this->listArea();

        $this->pointer(PointerEvent::press($list->row + 3, $list->column + 3));
        $this->pointer(PointerEvent::release($list->row + 3, $list->column + 3));

        self::assertFalse($this->app->state->selection()->isActive());
        self::assertSame('wpis-03', $this->app->state->context()->selection, 'kursor stanął tam, gdzie kliknięto');
    }

    /** Nowe naciśnięcie kasuje poprzedni prostokąt — jak w każdym interfejsie. */
    public function testANewPressClearsThePreviousSelection(): void
    {
        $list = $this->listArea();
        $this->drag($list->row, $list->column, $list->row + 3, $list->column + 5);

        $this->pointer(PointerEvent::press($list->row + 6, $list->column));

        self::assertFalse($this->app->state->selection()->isActive());
    }

    /** Zdanie o liczbie wierszy pada **po zwolnieniu przycisku**, a nie w trakcie. */
    public function testTheRowCountIsReportedOnReleaseOnly(): void
    {
        $list = $this->listArea();

        $this->pointer(PointerEvent::press($list->row, $list->column));
        $this->pointer(PointerEvent::drag($list->row + 2, $list->column + 5));

        self::assertNull($this->app->state->message(), 'w trakcie przeciągania stopka milczy');

        $this->pointer(PointerEvent::release($list->row + 2, $list->column + 5));

        $message = $this->app->state->message();

        self::assertNotNull($message, 'po zwolnieniu przycisku stopka mówi, ile zaznaczono');
        self::assertStringContainsString('selection.rows', $message->text);
    }

    /**
     * **Granica podziału ma pierwszeństwo**: przeciągnięcie zaczęte na niej
     * przesuwa granicę i nie zaznacza ani jednej komórki.
     */
    public function testDraggingTheSplitBoundaryDoesNotSelect(): void
    {
        $this->enableSplit();
        $content = $this->contentArea();
        $row = $content->row + 1;
        $boundary = $content->column + intdiv($content->columns, 2);

        $this->pointer(PointerEvent::press($row, $boundary));
        $this->pointer(PointerEvent::drag($row, $content->column + intdiv($content->columns, 3)));

        self::assertFalse($this->app->state->selection()->isActive(), 'granica zjadła przeciągnięcie');

        $this->pointer(PointerEvent::release($row, $content->column + intdiv($content->columns, 3)));

        self::assertSame(
            33,
            $this->app->state->settings()->moduleValue(BrowserSettings::ID, SplitSetting::KEY),
            'a proporcja się zmieniła',
        );
    }

    /** Przeciągnięcie prawym przyciskiem nie zaznacza — zaznacza wyłącznie lewy. */
    public function testOnlyTheLeftButtonSelects(): void
    {
        $list = $this->listArea();

        $this->pointer(PointerEvent::press($list->row, $list->column, PointerButton::Right));
        $this->pointer(PointerEvent::drag($list->row + 3, $list->column + 5, PointerButton::Right));

        self::assertFalse($this->app->state->selection()->isActive());
    }

    /** Zmiana ekranu kasuje zaznaczenie — wskazywałoby miejsce, którego już nie ma. */
    public function testChangingTheScreenClearsTheSelection(): void
    {
        $list = $this->listArea();
        $this->drag($list->row, $list->column, $list->row + 3, $list->column + 5);

        $this->press(Key::F1);
        $this->frame();

        self::assertFalse($this->app->state->selection()->isActive());
    }

    /** Otwarcie okna nakładanego — tak samo. */
    public function testOpeningAnOverlayClearsTheSelection(): void
    {
        $list = $this->listArea();
        $this->drag($list->row, $list->column, $list->row + 3, $list->column + 5);

        $this->press(Key::F12);
        $this->frame();

        self::assertFalse($this->app->state->selection()->isActive());
    }

    /** Zmiana rozmiaru okna — tak samo, i to jest trzeci z trzech powodów. */
    public function testResizingTheWindowClearsTheSelection(): void
    {
        $list = $this->listArea();
        $this->drag($list->row, $list->column, $list->row + 3, $list->column + 5);

        $this->frame(rows: self::ROWS - 2);

        self::assertFalse($this->app->state->selection()->isActive());
    }

    /**
     * Zaznaczenie przecina panele i bierze **wszystko, co obrysowano** — wraz
     * z obwódką, jeśli obrysowano obwódkę. Prostokąt, a nie przepływ.
     */
    public function testTheSelectionIsRectangularAndTakesWhatWasDrawnOver(): void
    {
        $list = $this->listArea();

        $this->drag($list->row, $list->column + 2, $list->row + 1, $list->column + 5);

        self::assertSame(['is-0', 'is-0'], $this->app->state->selectionText());
    }

    private function press(Key $key): void
    {
        $this->app->input->handle(KeyPress::special($key, ''), $this->app->state, self::NOW);
    }

    /** Naciśnięcie, przeciągnięcie i zwolnienie — trzy zdarzenia, jak u użytkownika. */
    private function drag(int $fromRow, int $fromColumn, int $toRow, int $toColumn): void
    {
        $this->pointer(PointerEvent::press($fromRow, $fromColumn));
        $this->pointer(PointerEvent::drag($toRow, $toColumn));
        $this->pointer(PointerEvent::release($toRow, $toColumn));
    }

    private function pointer(PointerEvent $event): void
    {
        $this->app->input->pointer($event, $this->app->state, self::NOW);
        $this->frame();
    }

    private function planeOf(string $id): ?Plane
    {
        foreach ($this->frame() as $plane) {
            if ($plane->id === $id) {
                return $plane;
            }
        }

        return null;
    }

    /**
     * Klatka złożona tak, jak składa ją aplikacja — bo zaznaczenie czyta to, co
     * **narysowano**, a nie to, co miało zostać narysowane.
     *
     * @return list<Plane>
     */
    private function frame(int $rows = self::ROWS): array
    {
        $renderer = new RecordingRenderer();
        (new FrameComposer(
            $renderer,
            new FixedViewport($rows, self::COLUMNS),
            new StubTranslator(),
            [
                ...InputHandler::globalBindings(),
                ...InputHandler::moduleBindings($this->app->modules->shortcuts()),
            ],
        ))->render($this->app->screens->current(), $this->app->state);

        $frame = $renderer->last();

        self::assertNotNull($frame);

        return $frame->planes;
    }

    /** Strefa środkowa — ten sam rachunek, który robi `FrameComposer`. */
    private function zone(): Rect
    {
        $this->frame();

        return (new HudLayout(self::ROWS, self::COLUMNS, true, true))->list;
    }

    private function listArea(): Rect
    {
        return Panel::inner($this->zone());
    }

    private function contentArea(): Rect
    {
        return $this->zone();
    }

    private function enableSplit(): void
    {
        $this->app->state->applySettings($this->app->state->settings()->withModuleValue(
            BrowserSettings::ID,
            BrowserSettings::SPLIT,
            true,
        ));
        $this->frame();
    }

    private static function fixture(int $entries = 40): ScreenFixture
    {
        $rows = [];

        for ($index = 0; $index < $entries; ++$index) {
            $rows[] = Entry::directory(sprintf('wpis-%02d', $index));
        }

        $directories = (new InMemoryDirectoryRepository())->add('/', [Entry::directory('home')])->add('/home', $rows);

        foreach ($rows as $entry) {
            $directories->add('/home/' . $entry->name, [Entry::file('plik.txt', 10)]);
        }

        return new ScreenFixture($directories->get(new DirectoryPath('/home'), false), $directories);
    }
}
