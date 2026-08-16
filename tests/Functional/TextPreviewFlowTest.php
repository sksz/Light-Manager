<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Module\FileInfo\Infrastructure\TextPreviewService;
use LightManager\Module\FileInfo\Presentation\FileInfoModule;
use LightManager\Module\FileInfo\Presentation\FileInfoScreen;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\ResetsSingletons;
use LightManager\Tests\Support\StubBackgroundProcess;
use LightManager\Tests\Support\StubChecksums;
use LightManager\Tests\Support\StubFileInspector;
use LightManager\Tests\Support\StubFileStat;
use LightManager\Tests\Support\StubImagePreview;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Przewijanie podglądu **w linijkach panelu** — na prawdziwym pliku i przez
 * prawdziwą usługę odczytu.
 *
 * Atrapa portu (`StubTextPreview`) liczy wiersze zamiast bajtów, więc zawinięcia
 * nie widzi w ogóle — a to właśnie przy zawijaniu „linijka panelu” przestaje być
 * tym samym, co „wiersz pliku”. Ten zestaw idzie więc całą drogą: klawisz →
 * `FileInfoState` → `TextPreviewService` → plik na dysku.
 *
 * Pytanie, na które odpowiada, jest jedno i wąskie: **czy przewinięcie o linijkę
 * przesuwa obraz o dokładnie jedną linijkę** — także wtedy, gdy cały plik jest
 * jednym wierszem. Do 2026-08-12 nie przesuwało: jednostką był wiersz pliku, więc
 * strzałka na takim pliku skakałaby od razu na jego koniec.
 */
final class TextPreviewFlowTest extends TestCase
{
    use ResetsSingletons;

    /** Szeroko, żeby powstał podział, a z nim prawy panel (próg z kroku 24). */
    private const WIDE = 120;

    private string $directory;

    protected function setUp(): void
    {
        $this->resetSingleton(TextPreviewService::class);

        $directory = sys_get_temp_dir() . '/lm-scroll-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $this->directory = $directory;
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
        $this->resetSingleton(TextPreviewService::class);
    }

    /**
     * Plik będący **jedną długą linią**: strzałka przesuwa obraz o linijkę,
     * a nie na koniec pliku.
     *
     * To jest przypadek `.php-cs-fixer.cache` — ten sam, przy którym zgłoszono
     * niedziałające zawijanie.
     */
    public function testArrowScrollsOneRowInsideASingleLongLine(): void
    {
        $screen = $this->screen($this->write('jedna.json', str_repeat('0123456789', 400)));
        $bounds = new Rect(0, 0, 10, self::WIDE);

        $first = $this->rows($screen, $bounds);
        $this->focusPreview($screen, $bounds);

        $screen->handle(KeyPress::special(Key::ArrowDown, ''));
        $second = $this->rows($screen, $bounds);

        self::assertNotSame([], $first);
        self::assertSame(
            array_slice($first, 1),
            array_slice($second, 0, count($first) - 1),
            'obraz przesunął się o dokładnie jedną linijkę',
        );
    }

    /**
     * To samo **z włączonymi numerami wierszy** — i to jest przypadek, na którym
     * poprawka z 2026-08-12 potknęła się za pierwszym razem.
     *
     * Kolumna numerów zabiera treści kilka znaków, więc linijka jest o tyle
     * węższa. Dopóki czytający plik liczył ją z liczby **wczytanych wierszy**,
     * a rysujący z liczby wierszy **prostokąta**, przewinięcie o linijkę mijało
     * się z obrazem o szerokość tej kolumny — na pliku o jednej długiej linii
     * o dwa znaki na każdy krok.
     */
    public function testArrowScrollsOneRowAlsoWithLineNumbersOn(): void
    {
        $screen = $this->screen($this->write('jedna.json', str_repeat('0123456789', 400)), numbers: true);
        $bounds = new Rect(0, 0, 10, self::WIDE);

        $first = $this->rows($screen, $bounds);
        $this->focusPreview($screen, $bounds);

        $screen->handle(KeyPress::special(Key::ArrowDown, ''));
        $second = $this->rows($screen, $bounds);

        // Numer stoi tylko przy pierwszym kawałku wiersza, więc z listy napisów
        // trzeba go odsiać — treść zostaje sama.
        $first = array_values(array_filter($first, static fn (string $text): bool => $text !== '1'));
        $second = array_values(array_filter($second, static fn (string $text): bool => $text !== '1'));

        self::assertNotSame([], $first);
        self::assertSame(
            array_slice($first, 1),
            array_slice($second, 0, count($first) - 1),
            'kolumna numerów nie ma prawa przestawić przewijania',
        );
    }

    /**
     * `End` na pliku będącym **jedną długą linią** pokazuje jej koniec, a nie
     * przypadkowe miejsce w środku.
     *
     * Pierwsza wersja skoku cofała się o „panel wierszy” i na takim pliku
     * lądowała tam, dokąd sięgnął budżet odczytu — czyli kilka kilobajtów od
     * początku. Koniec pliku, którego nie widać z końca, nie jest końcem.
     */
    public function testEndReachesTheTailOfASingleLongLine(): void
    {
        $content = str_repeat('0123456789', 400) . 'KONIEC';
        $screen = $this->screen($this->write('jedna.json', $content));
        $bounds = new Rect(0, 0, 10, self::WIDE);

        $this->rows($screen, $bounds);
        $this->focusPreview($screen, $bounds);
        $screen->handle(KeyPress::special(Key::End, ''));

        $tail = $this->rows($screen, $bounds);

        self::assertNotSame([], $tail);
        self::assertStringEndsWith('KONIEC', $tail[count($tail) - 1], 'ostatnia linijka to koniec pliku');
    }

    /** Ta sama droga w drugą stronę wraca co do znaku. */
    public function testArrowUpUndoesArrowDownInsideALongLine(): void
    {
        $screen = $this->screen($this->write('jedna.json', str_repeat('0123456789', 400)));
        $bounds = new Rect(0, 0, 10, self::WIDE);

        $before = $this->rows($screen, $bounds);
        $this->focusPreview($screen, $bounds);

        $screen->handle(KeyPress::special(Key::ArrowDown, ''));
        $this->rows($screen, $bounds);
        $screen->handle(KeyPress::special(Key::ArrowUp, ''));

        self::assertSame($before, $this->rows($screen, $bounds));
    }

    /**
     * `PgDn` przesuwa o **panel tego, co widać**, a nie o tyle wierszy pliku, ile
     * panel ma linijek.
     *
     * To była druga twarz tej samej usterki: przy zawijaniu wierszy widać mniej
     * niż linijek, więc przewinięcie o „tyle wierszy” przeskakiwało treść.
     */
    public function testPageDownMovesByExactlyOnePanelOfWhatWasVisible(): void
    {
        // Wiersze na tyle długie, że każdy zawija się na trzy linijki panelu.
        $lines = [];

        for ($index = 0; $index < 40; ++$index) {
            $lines[] = str_repeat(sprintf('%02d.', $index), 48);
        }

        $content = implode("\n", $lines) . "\n";
        $bounds = new Rect(0, 0, 8, self::WIDE);

        // Prostokąt ma osiem wierszy, ale dwa zjada obwódka panelu — treści
        // zostaje sześć linijek i to **one** są miarą panelu. Równość sprawdzamy
        // przez porównanie dwóch dróg do tego samego miejsca, a nie przez
        // oglądanie tekstu: przy zawinięciach linijki bywają nierozróżnialne, a
        // pytanie brzmi o **odległość**, nie o treść.
        $paged = $this->screen($this->write('paged.txt', $content));
        $this->rows($paged, $bounds);
        $this->focusPreview($paged, $bounds);
        $paged->handle(KeyPress::special(Key::PageDown, ''));

        $stepped = $this->screen($this->write('stepped.txt', $content));
        $first = $this->rows($stepped, $bounds);
        $this->focusPreview($stepped, $bounds);

        for ($step = 0; $step < count($first); ++$step) {
            $stepped->handle(KeyPress::special(Key::ArrowDown, ''));
        }

        self::assertCount(6, $first);
        self::assertSame(
            $this->rows($stepped, $bounds),
            $this->rows($paged, $bounds),
            'panel to tyle linijek, ile widać — ani jednej więcej',
        );
    }

    /** `End` pokazuje koniec pliku, a `Home` wraca na początek. */
    public function testEndShowsTheTailAndHomeComesBack(): void
    {
        $lines = [];

        for ($index = 0; $index < 200; ++$index) {
            $lines[] = 'wiersz-' . $index;
        }

        $screen = $this->screen($this->write('dlugi.txt', implode("\n", $lines) . "\n"));
        $bounds = new Rect(0, 0, 6, self::WIDE);

        $this->rows($screen, $bounds);
        $this->focusPreview($screen, $bounds);

        $screen->handle(KeyPress::special(Key::End, ''));

        // Skok rozlicza się jak każde przewinięcie — po panelu na klatkę.
        $tail = [];

        for ($frame = 0; $frame < 4; ++$frame) {
            $tail = $this->rows($screen, $bounds);
        }

        self::assertContains('wiersz-199', $tail, 'widać ostatni wiersz pliku');

        $screen->handle(KeyPress::special(Key::Home, ''));

        self::assertContains('wiersz-0', $this->rows($screen, $bounds));
    }

    /**
     * Po skoku na koniec **numery wierszy znikają**, bo kotwica stanęła po bajcie
     * i numeru nie znamy. `Home` je przywraca.
     */
    public function testLineNumbersVanishAfterJumpingToTheEnd(): void
    {
        $lines = [];

        for ($index = 0; $index < 200; ++$index) {
            $lines[] = 'wiersz-' . $index;
        }

        $screen = $this->screen($this->write('dlugi.txt', implode("\n", $lines) . "\n"), numbers: true);
        $bounds = new Rect(0, 0, 6, self::WIDE);

        self::assertContains('1', $this->rows($screen, $bounds), 'na początku numer jest znany');

        $this->focusPreview($screen, $bounds);
        $screen->handle(KeyPress::special(Key::End, ''));

        $tail = [];

        for ($frame = 0; $frame < 4; ++$frame) {
            $tail = $this->rows($screen, $bounds);
        }

        self::assertNotContains('200', $tail, 'numeru nie zgadujemy');

        $screen->handle(KeyPress::special(Key::Home, ''));

        self::assertContains('1', $this->rows($screen, $bounds), 'Home przywraca numerację');
    }

    /** @return list<string> napisy prawego panelu, w kolejności rysowania */
    private function rows(FileInfoScreen $screen, Rect $bounds): array
    {
        $texts = [];
        $half = intdiv($bounds->columns, 2);

        foreach ($screen->draw($bounds) as $primitive) {
            if ($primitive instanceof TextRun && $primitive->column > $half) {
                $texts[] = $primitive->text;
            }
        }

        return $texts;
    }

    private function focusPreview(FileInfoScreen $screen, Rect $bounds): void
    {
        $screen->draw($bounds);
        $screen->handle(KeyPress::special(Key::Tab, ''));
    }

    private function screen(string $path, bool $numbers = false): FileInfoScreen
    {
        $settings = new InMemorySettings(
            (new Settings())
                ->withModuleValue(FileInfoSettings::ID, FileInfoSettings::TEXT_PREVIEW, true)
                ->withModuleValue(FileInfoSettings::ID, FileInfoSettings::LINE_NUMBERS, $numbers),
        );

        $state = new LoopState($settings->current());
        $module = new FileInfoModule(
            $state,
            new StubTranslator(),
            $settings,
            StubImagePreview::unreadable(),
            new StubBackgroundProcess(),
            new StubFileInspector('ASCII text'),
            (new StubFileStat())->add($path),
            new StubChecksums(),
        );

        // Kwerendy modułu w rejestrze — tak, jak robi to `Bootstrap` (krok 53).
        $state->queries()->useModules([$module]);

        $screen = $module->screen();

        self::assertInstanceOf(FileInfoScreen::class, $screen);
        $screen->useContext(new ModuleContext(
            dirname($path),
            basename($path),
            ContextEntryKind::File,
        ));

        return $screen;
    }

    private function write(string $name, string $content): string
    {
        $path = $this->directory . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }
}
