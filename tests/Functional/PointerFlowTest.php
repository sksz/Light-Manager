<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\PointerButton;
use LightManager\Application\Dto\PointerEvent;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Presentation\Cli\FrameComposer;
use LightManager\Presentation\Cli\InputHandler;
use LightManager\Presentation\Cli\SplitSetting;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\HintTarget;
use LightManager\Presentation\Ui\HudLayout;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Tests\Support\FixedViewport;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\RecordingRenderer;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Wskaźnik przechodzi tę samą drogę, co klawisz (krok 55).
 *
 * Test patrzy na **skutek u użytkownika**, a nie na pola klas: kursor stanął
 * tam, gdzie kliknięto; stopka mówi o panelu, który wskazano; okno nakładane
 * połknęło kliknięcie w listę pod spodem. Miara kroku — „kliknięcie w wiersz
 * listy stawia na nim kursor” — jest zdaniem o przebiegu, więc tylko przebieg
 * umie ją sprawdzić.
 */
final class PointerFlowTest extends TestCase
{
    /** Szeroko: mieści się podział paneli i pełny pasek stanu. */
    private const COLUMNS = 160;

    /** Nisko na tyle, żeby lista miała co przewijać. */
    private const ROWS = 24;

    /** Dowolna chwila; liczy się wyłącznie różnica między odczytami. */
    private const NOW = 1000.0;

    private ScreenFixture $app;

    protected function setUp(): void
    {
        $this->app = self::fixture();
    }

    /**
     * Miara kroku: **kliknięcie w wiersz listy stawia na nim kursor**.
     *
     * Prostokąt bierze się z układu prawdziwej klatki, a nie z liczby wpisanej
     * w test — inaczej sprawdzałby własną arytmetykę, a nie aplikację.
     */
    public function testClickingARowPutsTheCursorOnIt(): void
    {
        $list = $this->listArea();

        self::assertSame('wpis-00', $this->selection());

        $this->click($list->row + 3, $list->column + 3);

        self::assertSame('wpis-03', $this->selection());
    }

    /** Kliknięcie poniżej ostatniego wpisu trafia w pustkę, a nie w koniec listy. */
    public function testClickingBelowTheLastEntryChangesNothing(): void
    {
        $this->app = self::fixture(2);
        $list = $this->listArea();

        $this->click($list->bottom(), $list->column + 3);

        self::assertSame('wpis-00', $this->selection());
    }

    /** Kliknięcie w drugi panel przenosi ognisko — widać to w stopce. */
    public function testClickingTheOtherPaneMovesTheFocus(): void
    {
        $this->enableSplit();

        self::assertStringContainsString('module.browser.focus.left', $this->footer());

        $content = $this->contentArea();
        $this->click($content->row + 2, $content->column + intdiv($content->columns, 2) + 4);

        self::assertStringContainsString('module.browser.focus.right', $this->footer());
    }

    /**
     * Kółko przewija **bez ruszania kursora** — a to znaczy, że okno musi się
     * odczepić, bo panel listowy woła `keepVisible()` przy każdym rysowaniu.
     */
    public function testTheWheelScrollsWithoutMovingTheCursor(): void
    {
        $list = $this->listArea();

        self::assertContains('wpis-00/', $this->listTexts());

        $this->scroll($list->row + 1, $list->column + 1, up: false);

        self::assertSame('wpis-00', $this->selection(), 'kursor zostaje tam, gdzie stał');
        self::assertNotContains('wpis-00/', $this->listTexts(), 'okno odjechało od kursora');
        self::assertContains('wpis-03/', $this->listTexts());
    }

    /** Ruch kursora klawiszem przyczepia okno z powrotem. */
    public function testMovingTheCursorReattachesTheWindow(): void
    {
        $list = $this->listArea();
        $this->scroll($list->row + 1, $list->column + 1, up: false);

        $this->press(Key::ArrowDown);

        self::assertContains('wpis-01/', $this->listTexts(), 'okno wróciło do kursora');
    }

    /**
     * Podwójne kliknięcie znaczy to, co `Enter` w tym miejscu — i idzie **tą
     * samą drogą**, przez `InputHandler::handle()`.
     */
    public function testDoubleClickEntersTheDirectoryUnderTheCursor(): void
    {
        $list = $this->listArea();
        [$row, $column] = [$list->row, $list->column + 3];

        $this->click($row, $column);
        $this->click($row, $column, self::NOW + 0.2);

        self::assertSame('/home/wpis-00', $this->app->state->context()->path);
    }

    /** Dwa kliknięcia dzielone dłuższą chwilą to dwa kliknięcia, a nie para. */
    public function testTwoSlowClicksAreNotADoubleClick(): void
    {
        $list = $this->listArea();
        [$row, $column] = [$list->row, $list->column + 3];

        $this->click($row, $column);
        $this->click($row, $column, self::NOW + 1.0);

        self::assertSame('/home', $this->app->state->context()->path);
    }

    /** Dwa szybkie kliknięcia w **różne** wiersze też parą nie są. */
    public function testTwoQuickClicksInDifferentRowsAreNotADoubleClick(): void
    {
        $list = $this->listArea();

        $this->click($list->row + 1, $list->column + 3);
        $this->click($list->row, $list->column + 3, self::NOW + 0.1);

        self::assertSame('/home', $this->app->state->context()->path);
    }

    /**
     * Okno nakładane jest modalne: kliknięcie **poza nim** nie robi nic i nie
     * zamyka okna.
     */
    public function testAClickOutsideAnOverlayDoesNothing(): void
    {
        $list = $this->listArea();
        $this->press(Key::F12);

        self::assertNotNull($this->app->state->overlays()->current());

        $this->click($list->row + 3, $list->column + 3);

        self::assertNotNull($this->app->state->overlays()->current(), 'okno zostaje otwarte');
        self::assertSame('wpis-00', $this->selection(), 'kursor pod spodem nietknięty');
    }

    /** Kliknięcie w podpowiedź stopki wykonuje jej klawisz. */
    public function testClickingAFooterHintRunsItsKey(): void
    {
        $target = $this->hintFor('help.key.help');

        self::assertNotNull($target, 'stopka pokazuje F1');

        $this->click($target->bounds->row, $target->bounds->column);

        self::assertSame('help', $this->app->screens->current()->id());
    }

    /** Kliknięcie w zakładkę pomocy przechodzi na nią. */
    public function testClickingATabSwitchesToIt(): void
    {
        $this->press(Key::F1);
        $help = $this->app->screens->current();
        $before = $this->textsOf($help);
        $second = $this->tabColumn(1);

        self::assertNotNull($second, 'pasek zakładek ma więcej niż jedną pozycję');

        $this->click($this->listArea()->row, $second);

        self::assertNotSame($before, $this->textsOf($help), 'treść zakładki się zmieniła');
    }

    /**
     * Kolumna, w której narysowano zakładkę o podanym numerze — **czytana
     * z klatki**, a nie liczona odstępem wpisanym w test.
     */
    private function tabColumn(int $index): ?int
    {
        $row = $this->listArea()->row;
        $columns = [];

        foreach ($this->primitives($this->app->screens->current()) as $primitive) {
            if ($primitive instanceof TextRun && $primitive->row === $row) {
                $columns[] = $primitive->column;
            }
        }

        sort($columns);

        return $columns[$index] ?? null;
    }

    /** @return array<string, array{float, int}> */
    public static function drags(): array
    {
        return [
            'do trzydziestu procent' => [0.3, 30],
            // Poza granicę nie wyjdzie: przeciągnięcie do samej krawędzi
            // zostawiłoby panel, którego nie da się już chwycić myszą.
            'poza lewą krawędź ścina się do minimum' => [0.0, 20],
            'poza prawą krawędź ścina się do maksimum' => [1.0, 80],
        ];
    }

    /**
     * Przeciągnięcie granicy podziału zmienia proporcję, zatrzymuje się na
     * granicach, a zwolnienie przycisku zapisuje ją w ustawieniach modułu.
     *
     * @param float $target ułamek szerokości, w który celuje przeciągnięcie
     * @param int   $saved  procent, który ma zostać zapisany
     */
    #[DataProvider('drags')]
    public function testDraggingTheBoundaryStopsAtItsLimitsAndPersistsOnRelease(float $target, int $saved): void
    {
        $this->enableSplit();
        $content = $this->contentArea();
        $row = $content->row + 1;
        $boundary = $content->column + intdiv($content->columns, 2);
        $to = $content->column + (int) round($content->columns * $target);

        $this->pointer(PointerEvent::press($row, $boundary));
        $this->pointer(PointerEvent::drag($row, $to));
        $this->pointer(PointerEvent::release($row, $to));

        self::assertSame(
            $saved,
            $this->app->state->settings()->moduleValue(BrowserSettings::ID, SplitSetting::KEY),
        );
    }

    /** Zapis pada **po zwolnieniu przycisku**, a nie przy każdym ruchu. */
    public function testDraggingDoesNotWriteTheSettingUntilTheButtonComesUp(): void
    {
        $this->enableSplit();
        $content = $this->contentArea();
        $row = $content->row + 1;

        $this->pointer(PointerEvent::press($row, $content->column + intdiv($content->columns, 2)));
        $this->pointer(PointerEvent::drag($row, $content->column + intdiv($content->columns, 3)));

        self::assertNull($this->app->state->settings()->moduleValue(BrowserSettings::ID, SplitSetting::KEY));
    }

    /** Prawy przycisk otwiera menu — po uprzednim postawieniu kursora. */
    public function testTheRightButtonOpensTheMenuOnTheRowItPointsAt(): void
    {
        $list = $this->listArea();

        $this->pointer(PointerEvent::press($list->row + 3, $list->column + 3, PointerButton::Right));

        self::assertSame('wpis-03', $this->selection());
        self::assertSame('menu', $this->app->state->overlays()->current()?->id());
    }

    /**
     * Układ liczony w tym teście musi zgadzać się z prawdziwą klatką — inaczej
     * wszystkie pozostałe kliknięcia trafiałyby obok, a test i tak przechodziłby
     * na własnej arytmetyce.
     */
    public function testTheLayoutInThisTestMatchesTheRealFrame(): void
    {
        $list = $this->listArea();
        $rows = [];

        foreach ($this->primitives($this->app->screens->current()) as $primitive) {
            if ($primitive instanceof TextRun && str_starts_with(trim($primitive->text), 'wpis-')) {
                $rows[$primitive->row] = $primitive->column;
            }
        }

        self::assertNotSame([], $rows);

        $numbers = array_keys($rows);
        sort($numbers);

        self::assertSame($list->row, $numbers[0], 'pierwszy wpis stoi w pierwszym wierszu treści');
        self::assertSame($list->column, reset($rows), 'wpisy zaczynają się w pierwszej kolumnie treści');
    }

    /** Kliknięcie środkowym przyciskiem nie robi nic — słownik go zna, aplikacja nie używa. */
    public function testTheMiddleButtonDoesNothing(): void
    {
        $list = $this->listArea();

        $this->pointer(PointerEvent::press($list->row + 3, $list->column + 3, PointerButton::Middle));

        self::assertSame('wpis-00', $this->selection());
    }

    private function selection(): ?string
    {
        return $this->app->state->context()->selection;
    }

    private function press(Key $key): void
    {
        $this->app->input->handle(KeyPress::special($key, ''), $this->app->state, self::NOW);
    }

    private function click(int $row, int $column, float $now = self::NOW): void
    {
        $this->pointer(PointerEvent::press($row, $column), $now);
    }

    private function scroll(int $row, int $column, bool $up): void
    {
        $this->pointer(PointerEvent::scroll($row, $column, $up));
    }

    private function pointer(PointerEvent $event, float $now = self::NOW): void
    {
        $this->app->input->pointer($event, $this->app->state, $now);
    }

    /**
     * Strefa środkowa — **ten sam rachunek, który robi `FrameComposer`**.
     *
     * `wideStatus` jest tu podane wprost i jest prawdziwe: podpowiedzi
     * przeglądarki nie mieszczą się w jednym wierszu przy 160 kolumnach, a przy
     * 24 wierszach pasek ma z czego urosnąć. Zgodność tej liczby z prawdziwą
     * klatką pilnuje `testTheLayoutInThisTestMatchesTheRealFrame()` — bez niego
     * test sprawdzałby własną arytmetykę.
     */
    private function zone(): Rect
    {
        $this->frame();

        return (new HudLayout(self::ROWS, self::COLUMNS, true, true))->list;
    }

    /** Prostokąt treści listy: wnętrze panelu strefy środkowej. */
    private function listArea(): Rect
    {
        return Panel::inner($this->zone());
    }

    /** Prostokąt oddany ekranowi przy podziale: **cały** zone, bo ekran oprawia się sam. */
    private function contentArea(): Rect
    {
        return $this->zone();
    }

    /** @return list<string> napisy narysowane w strefie środkowej */
    private function listTexts(): array
    {
        $content = $this->zone();
        $texts = [];

        foreach ($this->primitives($this->app->screens->current()) as $primitive) {
            if ($primitive instanceof TextRun && $primitive->row >= $content->row && $primitive->row <= $content->bottom()) {
                $texts[] = trim($primitive->text);
            }
        }

        return $texts;
    }

    /** @return list<string> */
    private function textsOf(ScreenInterface $screen): array
    {
        $texts = [];

        foreach ($this->primitives($screen) as $primitive) {
            if ($primitive instanceof TextRun) {
                $texts[] = $primitive->text;
            }
        }

        return $texts;
    }

    private function footer(): string
    {
        $this->frame();
        $texts = [];

        foreach ($this->primitives($this->app->screens->current()) as $primitive) {
            if ($primitive instanceof TextRun && $primitive->row >= self::ROWS - 3) {
                $texts[] = $primitive->text;
            }
        }

        return implode(' ', $texts);
    }

    private function hintFor(string $key): ?HintTarget
    {
        $this->frame();

        foreach ($this->app->state->hintTargets() as $target) {
            if ($target->binding->hintKey() === $key) {
                return $target;
            }
        }

        return null;
    }

    private function frame(?ScreenInterface $screen = null): void
    {
        $this->primitives($screen ?? $this->app->screens->current());
    }

    /** @return list<Primitive> */
    private function primitives(ScreenInterface $screen): array
    {
        $renderer = new RecordingRenderer();
        (new FrameComposer(
            $renderer,
            new FixedViewport(self::ROWS, self::COLUMNS),
            new StubTranslator(),
            [
                ...InputHandler::globalBindings(),
                ...InputHandler::moduleBindings($this->app->modules->shortcuts()),
            ],
        ))->render($screen, $this->app->state);

        $frame = $renderer->last();

        self::assertNotNull($frame);
        $primitives = [];

        foreach ($frame->planes as $plane) {
            foreach ($plane->primitives as $primitive) {
                $primitives[] = $primitive;
            }
        }

        return $primitives;
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
