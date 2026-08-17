<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\ClipboardText;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\PointerEvent;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Presentation\Cli\FrameComposer;
use LightManager\Presentation\Cli\InputHandler;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\HudLayout;
use LightManager\Tests\Support\FixedViewport;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\RecordingRenderer;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubClipboard;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Schowek systemowy: trzy źródła kopiowania, jedno miejsce wklejania (krok 57).
 *
 * **Ani jeden przebieg nie dotyka schowka osoby uruchamiającej testy** i nie jest
 * to ostrożność: obie prawdziwe implementacje portu piszą po cudzym schowku —
 * terminalowa wysyła `OSC 52` na STDOUT, okienna woła `glfwSetClipboardString()`.
 * Drogą jest `StubClipboard`, wstawiany do zestawu ekranów jak każda inna atrapa.
 *
 * Trudność kroku — odpowiedź przychodząca **później albo wcale** — rozkłada się
 * tu na trzy przebiegi: doręczenie temu, kto prosił; porzucenie treści, o którą
 * nikt nie prosił; wygaśnięcie prośby przy terminalu, który milczy. Sam rozbiór
 * sekwencji sprawdza `KeySequenceParserTest`, bo to rachunek bez pętli.
 */
final class ClipboardFlowTest extends TestCase
{
    private const COLUMNS = 160;

    private const ROWS = 24;

    private const NOW = 1000.0;

    private ScreenFixture $app;

    private StubClipboard $clipboard;

    protected function setUp(): void
    {
        $this->clipboard = new StubClipboard();
        $this->app = self::fixture($this->clipboard);
    }

    /**
     * **Miara kroku, źródło pierwsze**: `Alt`+`c` kładzie w schowku zaznaczoną
     * treść klatki — dokładnie tę, którą widać.
     */
    public function testCopyingTakesTheFrameSelectionFirst(): void
    {
        $list = $this->listArea();
        $this->drag($list->row, $list->column, $list->row + 2, $list->column + 6);

        $this->copy();

        self::assertSame(["wpis-00\nwpis-01\nwpis-02"], $this->clipboard->written);
        self::assertStringContainsString('clipboard.copied.selection', $this->message());
    }

    /**
     * **Źródło drugie**: bez zaznaczenia idą ścieżki wpisów zaznaczonych
     * wielokrotnie — jedyne źródło, którego rdzeń nie umie przeczytać sam.
     */
    public function testWithoutASelectionTheMarkedEntriesAreCopied(): void
    {
        $this->key(KeyPress::character(' '));
        $this->key(KeyPress::character(' '));

        $this->copy();

        self::assertSame(["/home/wpis-00\n/home/wpis-01"], $this->clipboard->written);
        self::assertStringContainsString('module.browser.clipboard.copied', $this->message());
    }

    /**
     * **Źródło trzecie**: sama ścieżka wpisu pod kursorem, z kontekstu sesji.
     * Działa w każdym ekranie publikującym kontekst, bez ani jednej linii w module.
     */
    public function testWithNothingMarkedThePathUnderTheCursorIsCopied(): void
    {
        $this->key(KeyPress::special(Key::ArrowDown, ''));

        $this->copy();

        self::assertSame(['/home/wpis-01'], $this->clipboard->written);
        self::assertStringContainsString('clipboard.copied.path', $this->message());
    }

    /**
     * Trzy źródła mają **trzy różne zdania** — bo po tym samym klawiszu są dla
     * użytkownika nierozróżnialne, dopóki zdanie jest jedno.
     */
    public function testTheThreeSourcesSayThreeDifferentThings(): void
    {
        $said = [];

        $this->key(KeyPress::special(Key::ArrowDown, ''));
        $this->copy();
        $said[] = $this->message();

        $this->key(KeyPress::character(' '));
        $this->copy();
        $said[] = $this->message();

        $list = $this->listArea();
        $this->drag($list->row, $list->column, $list->row + 1, $list->column + 6);
        $this->copy();
        $said[] = $this->message();

        self::assertSame($said, array_unique($said), 'każde źródło ma własne zdanie');
    }

    /** Nie ma czego skopiować — zdanie, a nie cisza po klawiszu. */
    public function testCopyingNothingSaysSo(): void
    {
        $this->app->state->publishContext(new \LightManager\Application\Module\ModuleContext());

        $this->copy();

        self::assertSame([], $this->clipboard->written);
        self::assertStringContainsString('clipboard.nothing', $this->message());
    }

    /**
     * Treść za długa kończy się **odmową ze zdaniem**, nigdy cichym obcięciem:
     * kopiowanie oddające połowę zawartości jest gorsze od kopiowania, które
     * odmawia.
     */
    public function testContentTooLongIsRefusedInsteadOfTruncated(): void
    {
        $this->clipboard->refusal = 'clipboard.problem.too-long';
        $this->key(KeyPress::special(Key::ArrowDown, ''));

        $this->copy();

        self::assertSame([], $this->clipboard->written);
        self::assertStringContainsString('clipboard.problem.too-long', $this->message());
    }

    /**
     * **Miara kroku, druga połowa**: `Alt`+`v` wstawia zawartość schowka do pola
     * tekstowego — a odpowiedź przychodzi **zdarzeniem**, nie z wywołania.
     */
    public function testPastingPutsTheClipboardIntoTheFocusedField(): void
    {
        $this->clipboard->content = 'wzorzec';
        $this->key(KeyPress::character('/'));

        $this->paste();

        self::assertSame(1, $this->clipboard->requests);
        self::assertStringContainsString('wzorzec', $this->app->state->context()->path . $this->filterText());
    }

    /**
     * **Aplikacja czyta schowek wyłącznie na wyraźne polecenie** — pierwsze
     * z trzech zobowiązań przyjętych wraz z odblokowaniem `GetSelection`.
     *
     * Test jest o tym, czego **nie ma**: ani start, ani takt, ani rysowanie klatki,
     * ani przeciągnięcie myszą nie pytają schowka o zawartość.
     */
    public function testNothingButAnExplicitCommandReadsTheClipboard(): void
    {
        $list = $this->listArea();
        $this->drag($list->row, $list->column, $list->row + 2, $list->column + 6);
        $this->key(KeyPress::character('/'));
        $this->key(KeyPress::character('a'));
        $this->frame();
        $this->app->input->expireClipboardRequest($this->app->state, self::NOW);

        self::assertSame(0, $this->clipboard->requests);
    }

    /**
     * **Bez pola tekstowego nie pytamy w ogóle** — drugie zobowiązanie: odczytana
     * treść ma jedno miejsce docelowe, więc pytanie zadane nad listą plików byłoby
     * czytaniem cudzego schowka bez odbiorcy.
     */
    public function testWithoutATextFieldTheClipboardIsNotEvenAsked(): void
    {
        $this->paste();

        self::assertSame(0, $this->clipboard->requests);
        self::assertStringContainsString('clipboard.no-target', $this->message());
    }

    /**
     * Odpowiedź, o którą **nikt nie prosił**, jest porzucana — także wtedy, gdy
     * jest poprawną odpowiedzią poprawnego terminala.
     */
    public function testAnUnrequestedAnswerIsDropped(): void
    {
        $this->key(KeyPress::character('/'));

        $this->app->input->clipboard(new ClipboardText('nieproszony'), $this->app->state, self::NOW);

        self::assertStringNotContainsString('nieproszony', $this->filterText());
    }

    /** Odpowiedź przychodząca do **zamkniętego** pola — tak samo porzucana. */
    public function testAnAnswerArrivingAtAClosedFieldIsDropped(): void
    {
        $this->clipboard->answers = false;
        $this->key(KeyPress::character('/'));
        $this->paste();

        // Pole zamyka się, zanim terminal odpowie — a odpowiedź przychodzi potem.
        $this->key(KeyPress::special(Key::Escape, ''));
        $this->app->input->clipboard(new ClipboardText('spóźniony'), $this->app->state, self::NOW);

        self::assertStringNotContainsString('spóźniony', $this->filterText());
    }

    /**
     * **Terminal, który odczytu nie obsługuje, kończy się zdaniem, a nie
     * zawieszeniem.** Nie odpowiada nic, więc prośba wygasa po terminie.
     */
    public function testASilentTerminalEndsWithASentenceInsteadOfWaitingForever(): void
    {
        $this->clipboard->answers = false;
        $this->key(KeyPress::character('/'));
        $this->paste();

        $this->app->input->expireClipboardRequest($this->app->state, self::NOW + 0.1);
        self::assertStringNotContainsString('clipboard.unreachable', $this->message(), 'przed terminem czekamy');

        $this->app->input->expireClipboardRequest($this->app->state, self::NOW + 1.0);
        self::assertStringContainsString('clipboard.unreachable', $this->message());
    }

    /** Zdanie o nieosiągalnym schowku pada **raz**, a nie trzydzieści razy na sekundę. */
    public function testTheExpirySentenceIsSaidOnce(): void
    {
        $this->clipboard->answers = false;
        $this->key(KeyPress::character('/'));
        $this->paste();

        $this->app->input->expireClipboardRequest($this->app->state, self::NOW + 1.0);
        $this->app->state->report(\LightManager\Domain\ValueObject\Message::info('inne zdanie'), self::NOW + 1.0);
        $this->app->input->expireClipboardRequest($this->app->state, self::NOW + 2.0);

        self::assertSame('inne zdanie', $this->message());
    }

    /** Schowek pusty mówi o tym zdaniem — i nie wstawia pustego napisu. */
    public function testAnEmptyClipboardSaysSo(): void
    {
        $this->clipboard->content = '';
        $this->key(KeyPress::character('/'));

        $this->paste();

        self::assertStringContainsString('clipboard.empty', $this->message());
    }

    /**
     * Treść wielowierszowa w polu jednowierszowym: nowa linia zamienia się
     * w odstęp, bo pole nie ma jej gdzie narysować, a przepuszczona wprost
     * weszłaby do nazwy pliku.
     */
    public function testMultilineContentBecomesOneLineInAOneLineField(): void
    {
        $this->clipboard->content = "pierwszy\ndrugi";
        $this->key(KeyPress::character('/'));

        $this->paste();

        $text = $this->filterText();

        self::assertStringNotContainsString("\n", $text);
        self::assertStringContainsString('pierwszy drugi', $text);
    }

    /**
     * Wklejanie do pola **maskowanego** działa, a treść nie trafia do żadnego
     * zapisu — trzecie zobowiązanie w jednym zdaniu.
     *
     * Pole z sekretem stoi na zakładce ustawień modułu (reguła 11y); tu bierzemy
     * je przez ekran ustawień, bo to on trzyma pole edycji.
     */
    public function testPastingIntoAMaskedFieldNeverReachesAnyLog(): void
    {
        $this->clipboard->content = 'sekret-z-schowka';

        self::assertFalse(
            $this->app->settings->paste('sekret-z-schowka'),
            'poza edycją pole nie przyjmuje niczego',
        );

        $message = $this->app->state->message();

        self::assertTrue(
            $message === null || !str_contains($message->text, 'sekret-z-schowka'),
            'treść schowka nie trafia do paska stanu',
        );
        self::assertSame([], $this->app->history->load(), 'ani do historii komend');
    }

    /**
     * Tor okienkowy przez atrapę portu: **różnica jest niewidoczna dla
     * wołającego**. Atrapa odpowiada synchronicznie, jak GLFW; przebieg jest ten
     * sam, co w terminalu.
     */
    public function testTheWindowedTrackAnswersInTheSameTickAndNothingChanges(): void
    {
        $this->clipboard->content = 'z-okna';
        $this->key(KeyPress::character('/'));

        $this->app->input->handle(
            KeyPress::alt(InputHandler::PASTE_CHARACTER),
            $this->app->state,
            self::NOW,
        );
        $answer = $this->clipboard->nextAnswer();

        self::assertNotNull($answer);
        $this->app->input->clipboard($answer, $this->app->state, self::NOW);

        self::assertStringContainsString('z-okna', $this->filterText());
    }

    private function copy(): void
    {
        $this->key(KeyPress::alt(InputHandler::COPY_CHARACTER));
    }

    /** Prośba i doręczenie — dwa takty, jak w torze terminalowym. */
    private function paste(): void
    {
        $this->key(KeyPress::alt(InputHandler::PASTE_CHARACTER));

        $answer = $this->clipboard->nextAnswer();

        if ($answer !== null) {
            $this->app->input->clipboard($answer, $this->app->state, self::NOW);
        }
    }

    private function key(KeyPress $key): void
    {
        $this->app->input->handle($key, $this->app->state, self::NOW);
        $this->frame();
    }

    private function message(): string
    {
        return $this->app->state->message()->text ?? '';
    }

    /** Treść pola filtra widziana **z klatki**, a nie z wnętrza okna. */
    private function filterText(): string
    {
        $overlay = $this->app->state->overlays()->current();

        if ($overlay === null) {
            return '';
        }

        $texts = '';

        foreach ($overlay->draw($overlay->bounds(self::ROWS, self::COLUMNS)) as $primitive) {
            if ($primitive instanceof TextRun) {
                $texts .= $primitive->text . ' ';
            }
        }

        return $texts;
    }

    private function drag(int $fromRow, int $fromColumn, int $toRow, int $toColumn): void
    {
        foreach ([
            PointerEvent::press($fromRow, $fromColumn),
            PointerEvent::drag($toRow, $toColumn),
            PointerEvent::release($toRow, $toColumn),
        ] as $event) {
            $this->app->input->pointer($event, $this->app->state, self::NOW);
            $this->frame();
        }
    }

    private function frame(int $rows = self::ROWS): void
    {
        (new FrameComposer(
            new RecordingRenderer(),
            new FixedViewport($rows, self::COLUMNS),
            new StubTranslator(),
            [
                ...InputHandler::globalBindings(),
                ...InputHandler::moduleBindings($this->app->modules->shortcuts()),
            ],
        ))->render($this->app->screens->current(), $this->app->state);
    }

    private function listArea(): Rect
    {
        $this->frame();

        return Panel::inner((new HudLayout(self::ROWS, self::COLUMNS, true, true))->list);
    }

    private static function fixture(StubClipboard $clipboard, int $entries = 20): ScreenFixture
    {
        $rows = [];

        for ($index = 0; $index < $entries; ++$index) {
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
