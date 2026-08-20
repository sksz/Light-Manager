<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\PointerEvent;
use LightManager\Application\Ui\Plane;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Presentation\Cli\FrameComposer;
use LightManager\Presentation\Cli\InputHandler;
use LightManager\Presentation\Ui\Component\Dialog;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\DragsOwnContent;
use LightManager\Presentation\Ui\HudLayout;
use LightManager\Presentation\Ui\Overlay\ConfirmOverlay;
use LightManager\Presentation\Ui\Overlay\MessageOverlay;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Tests\Support\DraggingOverlay;
use LightManager\Tests\Support\FixedViewport;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\RecordingRenderer;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubClipboard;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Zaznaczanie treści **w oknie nakładanym** (krok 77).
 *
 * Miara kroku jest zdaniem o przebiegu — „odcisk `SHA256:…` z pytania
 * o nieznany klucz hosta daje się obrysować myszą i skopiować” — więc sprawdza
 * ją przebieg, a nie test jednostkowy. Do kroku 77 nie dało się tego zrobić
 * wcale: `InputHandler::pointer()` kończył drogę na oknie, więc zaznaczanie nie
 * widziało ani jednego zdarzenia, a warstwa tekstowa powstawała z płaszczyzn
 * **bez** okna, więc czytała ekran spod niego.
 */
final class SelectionInOverlayFlowTest extends TestCase
{
    private const COLUMNS = 160;

    private const ROWS = 24;

    private const NOW = 1000.0;

    /** Odcisk krótki na tyle, żeby pytanie zmieściło się w jednym wierszu okna. */
    private const FINGERPRINT = 'SHA256:9r4Kx7ab';

    private ScreenFixture $app;

    private StubClipboard $clipboard;

    protected function setUp(): void
    {
        $this->clipboard = new StubClipboard();
        $this->app = self::fixture($this->clipboard);
    }

    /**
     * **Miara kroku**: obrysowane pytanie o klucz hosta oddaje odcisk.
     */
    public function testDraggingOverAnOverlayReadsItsContent(): void
    {
        $window = $this->openHostKeyQuestion();

        $this->dragOver($window);

        self::assertStringContainsString(
            self::FINGERPRINT,
            implode("\n", $this->app->state->selectionText()),
        );
    }

    /**
     * **Druga połowa miary**: `Alt`+`c` kładzie w schowku to, co obrysowano —
     * bez ani jednej linii w `copyable()`, bo zaznaczenie jest tam pierwszym
     * źródłem od kroku 57 i nie pyta, na czym leży prostokąt.
     */
    public function testCopyingTakesTheSelectionMadeOverAnOverlay(): void
    {
        $window = $this->openHostKeyQuestion();
        $this->dragOver($window);

        $this->app->input->handle(
            KeyPress::alt(InputHandler::COPY_CHARACTER),
            $this->app->state,
            self::NOW,
        );

        self::assertCount(1, $this->clipboard->written);
        self::assertStringContainsString(self::FINGERPRINT, $this->clipboard->written[0]);

        // Zdanie rozstrzyga, **które** ze trzech źródeł zadziałało: bez tej
        // asercji test przechodziłby także wtedy, gdyby zaznaczenia nie było
        // wcale, bo `ConfirmOverlay` deklaruje `CopiesContent` od kroku 57
        // i oddałby ten sam odcisk drugą drogą.
        self::assertStringContainsString(
            'clipboard.copied.selection',
            $this->app->state->message()->text ?? '',
        );
    }

    /**
     * Prostokąt jest **płaszczyzną wierzchnią**, a nie czwartą z pięciu — inaczej
     * zaznaczenie narysowane na oknie chowałoby się pod nim.
     */
    public function testTheSelectionIsDrawnOnTopOfTheOverlay(): void
    {
        $window = $this->openHostKeyQuestion();
        $this->dragOver($window);

        $ids = array_map(static fn (Plane $plane): string => $plane->id, $this->frame());

        self::assertNotSame([], $ids);
        self::assertSame('selection', end($ids));
        self::assertContains('confirm', $ids);
    }

    /**
     * Przeciągnięcie zaczęte **na oknie** i skończone **na ekranie** bierze
     * jedno i drugie: zaznaczenie dotyczy klatki, a klatka niesie okno razem
     * z ekranem.
     */
    public function testADragMaySpanTheOverlayAndTheScreenUnderneath(): void
    {
        $window = $this->openHostKeyQuestion();
        $list = $this->listArea();

        $this->drag(
            $window->bottom(),
            $window->right(),
            $window->row - 2,
            $list->column,
        );

        $text = implode("\n", $this->app->state->selectionText());

        self::assertStringContainsString(self::FINGERPRINT, $text, 'treść okna');
        self::assertStringContainsString('wpis-0', $text, 'treść ekranu pod nim');
    }

    /**
     * Otwarcie okna kasuje — reguła kroku 56 zostaje w mocy, bo klatka z oknem
     * i klatka bez okna to dwie różne klatki (D106 nr 1).
     */
    public function testOpeningAnOverlayStillClearsTheSelection(): void
    {
        $list = $this->listArea();
        $this->drag($list->row, $list->column, $list->row + 3, $list->column + 5);

        $this->openHostKeyQuestion();

        self::assertFalse($this->app->state->selection()->isActive());
    }

    /** Zamknięcie okna kasuje zaznaczenie zrobione **w** nim — druga strona tej samej reguły. */
    public function testClosingAnOverlayClearsTheSelectionMadeInIt(): void
    {
        $window = $this->openHostKeyQuestion();
        $this->dragOver($window);

        self::assertTrue($this->app->state->selection()->isActive());

        $this->app->state->overlays()->close();
        $this->frame();

        self::assertFalse($this->app->state->selection()->isActive());
    }

    /**
     * **Podmiana okna kasuje** — i to jest jedyna zmiana, jaką krok 77 zrobił
     * w samym `SelectionState`. Do niego klucz klatki niósł flagę „jest okno /
     * nie ma okna”, więc łańcuch okien (`OverlayOutcome::replace()`, krok 41)
     * przechodził niezauważony i prostokąt wisiał nad treścią, której nikt nie
     * wskazał.
     */
    public function testReplacingOneOverlayWithAnotherClearsTheSelection(): void
    {
        $window = $this->openHostKeyQuestion();
        $this->dragOver($window);

        self::assertTrue($this->app->state->selection()->isActive(), 'prostokąt najpierw powstał');

        $this->app->state->overlays()->open(new MessageOverlay(new Dialog('inne okno', ['treść'])));
        $this->frame();

        self::assertFalse($this->app->state->selection()->isActive());
    }

    /**
     * Przewijanie **nie kasuje** — w oknie tak samo jak w ekranie (D106 nr 3):
     * treść przewinięta pod prostokątem jest nową treścią zaznaczenia.
     */
    public function testScrollingDoesNotClearTheSelectionMadeOverAnOverlay(): void
    {
        $window = $this->openHostKeyQuestion();
        $this->dragOver($window);

        $this->pointer(PointerEvent::scroll($window->row, $window->column, up: false));

        self::assertTrue($this->app->state->selection()->isActive());
    }

    /**
     * **Modalność zostaje nietknięta.** Przeciągnięcie po oknie buduje prostokąt
     * i nic poza tym: kursor listy pod spodem stoi tam, gdzie stał.
     */
    public function testDraggingOverAnOverlayDoesNotReachTheScreenUnderneath(): void
    {
        $before = $this->app->state->context()->selectionPath();
        $window = $this->openHostKeyQuestion();

        $this->dragOver($window);

        self::assertTrue($this->app->state->selection()->isActive(), 'przeciągnięcie naprawdę padło');
        self::assertSame($before, $this->app->state->context()->selectionPath());
    }

    /**
     * Okno prowadzące **własne** przeciągnięcie zabiera je zaznaczaniu —
     * bliźniak reguły, którą ekran ma od kroku 56 dla granicy podziału.
     *
     * Deklarującego w aplikacji nie ma (D106 nr 2), więc deklaruje atrapa; test
     * jest o tym, że rdzeń **pyta**, a nie o tym, że ktoś odpowiada twierdząco.
     */
    public function testAnOverlayLeadingItsOwnDragTakesThePointerFromTheSelection(): void
    {
        $overlay = new DraggingOverlay();
        $this->app->state->overlays()->open($overlay);
        $this->frame();

        $this->drag(1, 1, 3, 12);

        self::assertFalse($this->app->state->selection()->isActive());

        $overlay->stopDragging();
        $this->drag(1, 1, 3, 12);

        self::assertTrue($this->app->state->selection()->isActive());
    }

    /**
     * Ekran pod oknem **nie jest pytany** o własne przeciągnięcie — a scenariusz
     * jest prawdziwy, nie teoretyczny: granicę podziału chwyta się naciśnięciem,
     * a okno ma prawo otworzyć się, zanim padnie zwolnienie przycisku. Ekran
     * zostaje wtedy w stanie „trzymam granicę” i przy pytaniu skierowanym do
     * niego odbierałby prostokąt zaznaczeniu robionemu nad oknem.
     */
    public function testTheScreenLeftMidDragUnderAnOverlayDoesNotStealThePointer(): void
    {
        $this->enableSplit();
        $content = (new HudLayout(self::ROWS, self::COLUMNS, true, true))->list;

        // Chwyt granicy **bez zwolnienia** — ekran zostaje w trakcie własnego
        // przeciągnięcia.
        $this->pointer(PointerEvent::press(
            $content->row + 1,
            $content->column + intdiv($content->columns, 2),
        ));

        self::assertTrue($this->screenDragsOwn(), 'ekran naprawdę trzyma granicę');

        $window = $this->openHostKeyQuestion();
        $this->dragOver($window);

        self::assertTrue($this->screenDragsOwn(), 'i nadal ją trzyma');
        self::assertStringContainsString(
            self::FINGERPRINT,
            implode("\n", $this->app->state->selectionText()),
            'a mimo to prostokąt powstał nad oknem',
        );
    }

    private function screenDragsOwn(): bool
    {
        $screen = $this->app->screens->current();

        return $screen instanceof DragsOwnContent && $screen->isDraggingOwn();
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

    /** Pytanie o klucz hosta, otwarte i policzone — wraz z prostokątem, jaki zajęło. */
    private function openHostKeyQuestion(): Rect
    {
        $overlay = new ConfirmOverlay(
            'ssh.host-key',
            ['fingerprint' => self::FINGERPRINT],
            static fn (): OverlayOutcome => OverlayOutcome::close(),
            new StubTranslator(),
        );

        $this->app->state->overlays()->open($overlay);
        $this->frame();

        return $this->overlayBounds($overlay);
    }

    private function overlayBounds(OverlayInterface $overlay): Rect
    {
        return $overlay->bounds(self::ROWS, self::COLUMNS);
    }

    /** Obrys całego okna — tak, jak robi to ręka sięgająca po odcisk. */
    private function dragOver(Rect $window): void
    {
        $this->drag($window->row, $window->column, $window->bottom(), $window->right());
    }

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

    /**
     * Klatka złożona tak, jak składa ją aplikacja.
     *
     * @return list<Plane>
     */
    private function frame(): array
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
        ))->render($this->app->screens->current(), $this->app->state);

        $frame = $renderer->last();

        self::assertNotNull($frame);

        return $frame->planes;
    }

    private function listArea(): Rect
    {
        return Panel::inner((new HudLayout(self::ROWS, self::COLUMNS, true, true))->list);
    }

    private static function fixture(StubClipboard $clipboard): ScreenFixture
    {
        $rows = [];

        for ($index = 0; $index < 40; ++$index) {
            $rows[] = Entry::directory(sprintf('wpis-%02d', $index));
        }

        $directories = (new InMemoryDirectoryRepository())->add('/', [Entry::directory('home')])->add('/home', $rows);

        foreach ($rows as $entry) {
            $directories->add('/home/' . $entry->name, [Entry::file('plik.txt', 10)]);
        }

        return new ScreenFixture(
            $directories->get(new DirectoryPath('/home'), false),
            $directories,
            clipboard: $clipboard,
        );
    }
}
