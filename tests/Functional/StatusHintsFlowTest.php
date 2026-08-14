<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Ui\Frame;
use LightManager\Application\Ui\Plane;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\Message;
use LightManager\Infrastructure\Diagnostics\FrameSerializer;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\FileInfo\Infrastructure\TextPreviewService;
use LightManager\Presentation\Cli\FrameComposer;
use LightManager\Presentation\Cli\InputHandler;
use LightManager\Presentation\Ui\DeclaresFocus;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Module\ReadsContext;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\Transition;
use LightManager\Tests\Support\FixedViewport;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\RecordingRenderer;
use LightManager\Tests\Support\ResetsSingletons;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Stopka mówi o miejscu, na którym stoi ognisko (krok 40).
 *
 * Test patrzy na **klatkę i na klawisze**, a nie na klasy, bo błąd, który tu
 * naprawdę grozi, jest rozjazdem między jednym a drugim: pasek stanu obiecuje
 * klawisz, którego w tym miejscu nikt nie obsługuje, albo milczy o tym, który
 * działa. Ostatni test pilnuje tego dla **wszystkich ekranów i wszystkich położeń
 * ogniska naraz** — na wzór `PrimitiveTranslationTableTest` z kroku 30.
 */
final class StatusHintsFlowTest extends TestCase
{
    use ResetsSingletons;

    /** Szeroko i wysoko: mieści się podział, pas podglądu i pasek dwuwierszowy. */
    private const COLUMNS = 160;

    private const ROWS = 40;

    private ScreenFixture $app;

    private string $directory = '';

    protected function setUp(): void
    {
        $this->resetSingleton(TextPreviewService::class);
        $this->app = self::fixture();
    }

    protected function tearDown(): void
    {
        // Warunek jest tu **przed** pętlą, a nie za nią: `glob('/*')` na pustej
        // ścieżce wyliczyłby zawartość korzenia systemu plików.
        if ($this->directory !== '') {
            foreach (glob($this->directory . '/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($this->directory);
        }

        $this->resetSingleton(TextPreviewService::class);
    }

    /** Sedno kroku: ognisko przenosi się klawiszem, a stopka mówi o tym od razu. */
    public function testMovingTheFocusChangesTheFooterInTheSameFrame(): void
    {
        $this->enableSplit();

        self::assertStringContainsString('module.browser.focus.left', $this->footer($this->app->browser));

        $this->app->browser->handle(new KeyPress(Key::Tab, "\t"));

        self::assertStringContainsString('module.browser.focus.right', $this->footer($this->app->browser));
    }

    /**
     * Ten sam ekran, inny widok panelu — inne znaczenie strzałek poziomych i inna
     * stopka. Do kroku 40 obie wyglądały identycznie.
     */
    public function testTheViewOfTheFocusedPaneChangesWhatTheFooterPromises(): void
    {
        self::assertStringContainsString('module.browser.help.open', $this->footer($this->app->browser));

        $this->app->browser->handle(KeyPress::ctrl('t'));
        $footer = $this->footer($this->app->browser);

        self::assertStringContainsString('module.browser.focus.tree', $footer);
        self::assertStringContainsString('module.browser.help.tree.expand', $footer);
    }

    /**
     * Okno nakładane **wypiera** ekran, bo klawisze do niego nie schodzą
     * (`InputHandler::toOverlay`, krok 19). Stopka mówiąca wtedy o liście plików
     * obiecywałaby klawisze, których okno nie przepuści.
     */
    public function testAnOverlayDisplacesTheScreenInsteadOfAddingToIt(): void
    {
        $this->app->input->handle(KeyPress::special(Key::F12, "\eO"), $this->app->state, 0.0);
        $footer = $this->footer($this->app->browser);

        self::assertStringContainsString('command.key.run', $footer);
        self::assertStringNotContainsString('module.browser.help.open', $footer);
        self::assertStringContainsString('help.key.help', $footer, 'klawisze globalne zostają');
    }

    /**
     * Skróty modułów działają wszędzie, a do kroku 40 stopka nigdy o nich nie
     * mówiła: `globalBindings()` ich nie zna, bo powstają z rejestru modułów.
     */
    public function testModuleShortcutsFinallyReachTheFooter(): void
    {
        // Szeroko, bo skrót modułu stoi w grupie globalnej i ustępuje **pierwszy**:
        // w oknie 160 kolumn wypada z paska, i tak ma być.
        $footer = $this->footer($this->app->browser, 800);

        self::assertStringContainsString('Ctrl+D', $footer);
        self::assertStringContainsString('module.file-info.name', $footer);
    }

    /**
     * Reguła kroku 18 zostaje nietknięta: w wąskim oknie długi błąd jest
     * ważniejszy od przypomnienia, gdzie jest wyjście.
     */
    public function testHintsNeverCoverTheMessage(): void
    {
        $this->app->state->report(Message::error(str_repeat('bardzo długi komunikat o błędzie ', 4)), 0.0);
        $runs = $this->statusRuns($this->app->browser, 120, 30);
        $message = null;

        foreach ($runs as $run) {
            if ($run->role === Role::Danger) {
                $message = $run;
            }
        }

        self::assertNotNull($message, 'komunikat musi być widoczny w całości, jaki by nie był długi');

        foreach ($runs as $run) {
            if ($run->role !== Role::Muted || $run->row !== $message->row) {
                continue;
            }

            self::assertGreaterThan(
                $message->column + mb_strlen($message->text),
                $run->column,
                'podpowiedź weszła na komunikat',
            );
        }
    }

    /** Pasek rośnie do dwóch wierszy dopiero z potrzeby — i wtedy naprawdę rośnie. */
    public function testTheBarTakesASecondRowOnlyWhenTheHintsDoNotFitInOne(): void
    {
        // Osiemset kolumn, bo w teście tłumacz oddaje **klucze**, a te są dłuższe od
        // napisów: pełna stopka przeglądarki ma tu ponad czterysta znaków.
        $wide = self::rowsOf($this->statusRuns($this->app->browser, 800, self::ROWS));
        $narrow = self::rowsOf($this->statusRuns($this->app->browser, 120, self::ROWS));

        self::assertCount(1, $wide, 'w szerokim oknie wszystko mieści się w wierszu');
        self::assertCount(2, $narrow, 'w węższym stopka schodzi do drugiego wiersza');
    }

    /**
     * **Właściwy sens kroku, sprawdzany jednym testem dla wszystkich miejsc.**
     *
     * Dla każdego ekranu i każdego położenia ogniska: każde wiązanie pokazane
     * w stopce musi być obsłużone przez `handle()` tego miejsca. „Obsłużone”
     * znaczy: **co najmniej jeden klawisz z zestawu** robi coś widocznego —
     * zmienia klatkę, stan, spis wiązań albo oddaje skutek. Zestaw, a nie każdy
     * klawisz z osobna, bo `↑` na pierwszej pozycji listy nie robi nic i robić
     * nie ma, a stopka migocząca na krańcach listy byłaby gorsza od stopki
     * milczącej.
     *
     * @param callable(self): array{ScreenInterface, string} $place
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('places')]
    public function testEveryHintShownIsHandledWhereItIsShown(callable $place): void
    {
        [$screen, $name] = $place($this);
        $bindings = self::shownIn($screen);

        self::assertNotSame([], $bindings, 'miejsce bez ani jednego wiązania: ' . $name);

        foreach ($bindings as $binding) {
            [$fresh, ] = $place($this);
            self::assertTrue(
                $this->reacts($fresh, $binding),
                sprintf('%s obiecuje „%s”, a nic się po nim nie dzieje', $name, $binding->display()),
            );
        }
    }

    /**
     * Miejsca, w których staje ognisko — po jednym wpisie na położenie, wraz
     * z przygotowaniem, które je tam stawia.
     *
     * @return iterable<string, array{callable(self): array{ScreenInterface, string}}>
     */
    public static function places(): iterable
    {
        yield 'przeglądarka: lista' => [static function (self $test): array {
            $test->app = self::fixture();
            $test->app->browser->handle(KeyPress::special(Key::ArrowDown, "\e[B"));

            return [$test->app->browser, 'lista plików'];
        }];

        yield 'przeglądarka: drzewo' => [static function (self $test): array {
            $test->app = self::fixture();
            $test->app->browser->handle(KeyPress::ctrl('t'));
            $test->app->browser->handle(KeyPress::special(Key::ArrowDown, "\e[B"));

            return [$test->app->browser, 'drzewo katalogów'];
        }];

        yield 'przeglądarka: podział' => [static function (self $test): array {
            $test->app = self::fixture();
            $test->enableSplit();
            $test->app->browser->handle(KeyPress::special(Key::ArrowDown, "\e[B"));

            return [$test->app->browser, 'panel przy podziale'];
        }];

        yield 'przeglądarka: filtr' => [static function (self $test): array {
            $test->app = self::fixture();
            // Przez `InputHandler`, a nie wprost przez ekran: pole filtra jest oknem
            // nakładanym, więc bez rdzenia skutek `ScreenOutcome::opens()` przepadłby
            // i filtr nigdy by się nie założył.
            foreach ([KeyPress::character('/'), KeyPress::character('d'), KeyPress::special(Key::Enter, "\r")] as $key) {
                $test->app->input->handle($key, $test->app->state, 0.0);
            }

            return [$test->app->browser, 'lista z filtrem'];
        }];

        yield 'opis pliku: sekcje' => [static function (self $test): array {
            $test->app = self::fixture();
            $screen = $test->fileInfoOnText();
            $screen->handle(KeyPress::special(Key::ArrowDown, "\e[B"));

            return [$screen, 'sekcje opisu pliku'];
        }];

        yield 'opis pliku: podgląd' => [static function (self $test): array {
            $test->app = self::fixture();
            $screen = $test->fileInfoOnText();
            $screen->handle(new KeyPress(Key::Tab, "\t"));
            $screen->handle(KeyPress::special(Key::ArrowDown, "\e[B"));

            return [$screen, 'podgląd tekstu'];
        }];

        yield 'ustawienia: pasek zakładek' => [static function (self $test): array {
            $test->app = self::fixture();

            return [$test->app->settings, 'pasek zakładek'];
        }];

        yield 'ustawienia: pozycja' => [static function (self $test): array {
            $test->app = self::fixture();
            $test->app->settings->handle(KeyPress::special(Key::ArrowDown, "\e[B"));

            return [$test->app->settings, 'pozycja ustawień'];
        }];

        yield 'ustawienia: czynność' => [static function (self $test): array {
            $test->app = self::fixture();

            for ($step = 0; $step < 20; ++$step) {
                $test->app->settings->handle(KeyPress::special(Key::ArrowDown, "\e[B"));
            }

            return [$test->app->settings, 'wiersz czynności'];
        }];

        yield 'ustawienia: edycja' => [static function (self $test): array {
            $test->app = self::fixture();
            $test->editTextSetting();

            return [$test->app->settings, 'pozycja tekstowa w edycji'];
        }];

        yield 'pomoc' => [static function (self $test): array {
            $test->app = self::fixture();
            $test->app->help->handle(KeyPress::special(Key::ArrowDown, "\e[B"));

            return [$test->app->help, 'okno pomocy'];
        }];
    }

    /**
     * Wiązania, które zobaczy użytkownik: miejsce z ogniskiem plus ekran, bez
     * powtórzeń — czyli dokładnie to, co składa `StatusHints`.
     *
     * @return list<KeyBinding>
     */
    private static function shownIn(ScreenInterface $screen): array
    {
        $focus = $screen instanceof DeclaresFocus ? $screen->focus() : null;
        $shown = $focus === null ? [] : $focus->bindings;

        foreach ($screen->bindings() as $binding) {
            foreach ($shown as $earlier) {
                if ($earlier->sameAs($binding)) {
                    continue 2;
                }
            }

            $shown[] = $binding;
        }

        return $shown;
    }

    /** Czy którykolwiek klawisz wiązania robi w tym miejscu cokolwiek widocznego. */
    private function reacts(ScreenInterface $screen, KeyBinding $binding): bool
    {
        foreach (self::pressesOf($binding) as $press) {
            $before = $this->snapshot($screen);
            $outcome = $screen->handle($press);

            if ($outcome->message !== null
                || $outcome->overlay !== null
                || $outcome->transition !== Transition::Stay
                || $this->snapshot($screen) !== $before
            ) {
                return true;
            }
        }

        return false;
    }

    /** @return list<KeyPress> */
    private static function pressesOf(KeyBinding $binding): array
    {
        if ($binding->character !== null) {
            return [match (true) {
                $binding->ctrl => KeyPress::ctrl($binding->character),
                $binding->alt => KeyPress::alt($binding->character),
                default => KeyPress::character($binding->character),
            }];
        }

        $presses = [];

        foreach ($binding->keys as $key) {
            $presses[] = KeyPress::special($key, "\e");
        }

        return $presses;
    }

    /**
     * Ślad miejsca: klatka zapisana **serializatorem projektu** (ten sam, którym
     * porównuje się złote klatki), pas górny, spis wiązań i kontekst sesji.
     *
     * Serializator, a nie własne wyliczanie napisów, bo połowa czynności nie zmienia
     * ani jednej litery: przeniesienie kursora sekcji zmienia **rolę** wiersza,
     * a ruch karetki — położenie podświetlenia.
     */
    private function snapshot(ScreenInterface $screen): string
    {
        $bounds = new Rect(0, 0, 24, self::COLUMNS);
        $parts = [(new FrameSerializer())->toText(
            new Frame([new Plane('snapshot', $bounds, $screen->draw($bounds))]),
        )];

        $header = $screen->header();

        if ($header !== null) {
            foreach (self::textsOf($header->content->draw(new Rect(0, 0, 1, self::COLUMNS))) as $text) {
                $parts[] = $text;
            }
        }

        foreach (self::shownIn($screen) as $binding) {
            $parts[] = $binding->display() . ' ' . $binding->descriptionKey;
        }

        $context = $this->app->state->context();
        $parts[] = $context->path . '|' . ($context->selection ?? '');

        return implode("\n", $parts);
    }

    /** Napisy stopki w jednym ciągu — tak, jak zobaczy je użytkownik. */
    private function footer(ScreenInterface $screen, int $columns = self::COLUMNS, int $rows = self::ROWS): string
    {
        $texts = [];

        foreach ($this->statusRuns($screen, $columns, $rows) as $run) {
            $texts[] = $run->text;
        }

        return implode(' ', $texts);
    }

    /**
     * Prymitywy tekstowe strefy paska stanu — cztery ostatnie wiersze okna,
     * czyli najwyższa strefa, jaką pasek może dostać.
     *
     * @return list<TextRun>
     */
    private function statusRuns(ScreenInterface $screen, int $columns, int $rows): array
    {
        $renderer = new RecordingRenderer();
        (new FrameComposer(
            $renderer,
            new FixedViewport($rows, $columns),
            new StubTranslator(),
            [
                ...InputHandler::globalBindings(),
                ...InputHandler::moduleBindings($this->app->modules->shortcuts()),
            ],
        ))->render($screen, $this->app->state);

        $frame = $renderer->last();

        self::assertNotNull($frame);
        $runs = [];

        foreach ($frame->planes as $plane) {
            if ($plane->id !== 'content') {
                continue;
            }

            foreach ($plane->primitives as $primitive) {
                // Strefa stanu jest zawsze ostatnia, a jej treść stoi w wierszach
                // `rows-3` i `rows-2` (obwódka bierze pierwszy i ostatni). Wiersz
                // `rows-4` należy jeszcze do pasa podglądu i nie ma tu czego szukać.
                if ($primitive instanceof TextRun && $primitive->row >= $rows - 3) {
                    $runs[] = $primitive;
                }
            }
        }

        return $runs;
    }

    /**
     * @param list<TextRun> $runs
     *
     * @return list<int>
     */
    private static function rowsOf(array $runs): array
    {
        $rows = [];

        foreach ($runs as $run) {
            if ($run->role === Role::Muted && !in_array($run->row, $rows, true)) {
                $rows[] = $run->row;
            }
        }

        return $rows;
    }

    /** Ekran opisu pliku ustawiony na prawdziwym pliku tekstowym, po szerokiej klatce. */
    private function fileInfoOnText(): ScreenInterface
    {
        $this->directory = sys_get_temp_dir() . '/lm-hints-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
        // Pierwszy wiersz **dłuższy od panelu**, żeby `Alt`+`Z` miało co zawinąć:
        // przy samych krótkich wierszach przełącznik zawijania nie zmienia klatki
        // o ani jeden znak, a test nie odróżniłby go od klawisza martwego.
        $lines = str_repeat('bardzo długi wiersz bez podziału ', 20) . "\n";

        for ($line = 1; $line <= 200; ++$line) {
            // Wiersze **różne**, bo przewinięcie pliku o dwustu jednakowych wierszach
            // daje klatkę co do znaku taką samą — i test nie odróżniłby przewijania
            // działającego od nieistniejącego.
            $lines .= 'wiersz numer ' . $line . ' treści pliku' . "\n";
        }

        file_put_contents($this->directory . '/opis.txt', $lines);

        $screen = $this->app->fileInfo;

        self::assertInstanceOf(ReadsContext::class, $screen);
        $screen->useContext(new ModuleContext($this->directory, 'opis.txt', ContextEntryKind::File));

        // Podział prawego panelu powstaje przy rysowaniu, a `Tab` pyta o wynik
        // ostatniej klatki — więc klatka musi być pierwsza.
        $screen->draw(new Rect(0, 0, 24, self::COLUMNS));

        return $screen;
    }

    /**
     * Kursor ustawień na pozycji tekstowej, wprowadzony w tryb edycji.
     *
     * Miejsca szukamy po **deklaracji ogniska**, a nie po numerze pozycji: numer
     * zmieniłby się przy pierwszym dołożonym ustawieniu, a deklaracja mówi wprost,
     * że to jest ta pozycja, którą `Enter` otwiera do pisania.
     */
    private function editTextSetting(): void
    {
        $settings = $this->app->settings;

        for ($tab = 0; $tab < 8; ++$tab) {
            $settings->reset();

            for ($step = 0; $step < $tab; ++$step) {
                $settings->handle(KeyPress::special(Key::ArrowRight, "\e[C"));
            }

            for ($item = 0; $item < 16; ++$item) {
                $settings->handle(KeyPress::special(Key::ArrowDown, "\e[B"));

                foreach ($settings->focus()->bindings as $binding) {
                    if ($binding->descriptionKey !== 'help.key.edit') {
                        continue;
                    }

                    $settings->handle(KeyPress::special(Key::Enter, "\r"));

                    // Wartość wpisana, bo karetka w pustym polu nie ma dokąd pójść:
                    // `←`, `→`, `Home` i `End` są wtedy martwe wszystkie naraz —
                    // nie dlatego, że pole ich nie obsługuje, tylko dlatego, że nie
                    // ma czego przewijać.
                    foreach (['-', 'a', 'b'] as $letter) {
                        $settings->handle(KeyPress::character($letter));
                    }

                    return;
                }
            }
        }

        self::fail('nie ma ani jednej pozycji tekstowej — test edycji nie ma czego sprawdzić');
    }

    private function enableSplit(): void
    {
        $this->app->state->applySettings($this->app->state->settings()->withModuleValue(
            BrowserSettings::ID,
            BrowserSettings::SPLIT,
            true,
        ));
    }

    private static function fixture(): ScreenFixture
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/', [Entry::directory('home')])
            // Dwa katalogi z tą samą literą w nazwie i to nie jest ozdoba: pozycja
            // „lista z filtrem” zawęża listę do `d`, a musi zostać w niej dość wpisów,
            // żeby strzałka miała dokąd pójść, i katalog, do którego `Enter` wejdzie.
            ->add('/home', [
                Entry::file('alfa.txt', 12),
                Entry::directory('dane'),
                Entry::directory('dokumenty'),
                Entry::file('zeta.png', 4096),
            ])
            ->add('/home/dane', [Entry::file('spis.csv', 64)])
            ->add('/home/dokumenty', [Entry::file('umowa.pdf', 2048)]);

        return new ScreenFixture($directories->get(new DirectoryPath('/home'), false), $directories);
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<string>
     */
    private static function textsOf(array $primitives): array
    {
        $texts = [];

        foreach ($primitives as $primitive) {
            if ($primitive instanceof TextRun) {
                $texts[] = $primitive->text;
            }
        }

        return $texts;
    }
}
