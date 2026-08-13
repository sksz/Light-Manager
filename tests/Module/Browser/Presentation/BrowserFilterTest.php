<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Browser\Presentation;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextMark;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * Filtrowanie listy i podświetlenie dopasowania — krok 30.
 *
 * Test jedzie **całą drogą klawisza**, przez `InputHandler`, a nie po metodach
 * ekranu: filtr rozkłada się na trzy piętra — okno nakładane, stan panelu
 * i komponent listy — a rzeczy, które w tym kroku mogą pójść źle, dzieją się
 * dokładnie na stykach. Zawężenie widoczne dopiero w następnej klatce albo
 * zaznaczenie przeskakujące na obcy wpis nie objawiłyby się w żadnej z tych
 * klas z osobna.
 */
final class BrowserFilterTest extends TestCase
{
    private ScreenFixture $app;

    protected function setUp(): void
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/', [Entry::directory('home')])
            ->add('/home', [
                Entry::directory('dokumenty'),
                Entry::file('notatka.txt', 12),
                Entry::file('raport-dokumentacja.pdf', 2048),
                Entry::file('zażółć.txt', 7),
            ])
            ->add('/home/dokumenty', [Entry::file('umowa.pdf', 2048)]);

        $this->app = new ScreenFixture(
            $directories->get(new DirectoryPath('/home'), false),
            $directories,
        );
    }

    public function testSlashOpensTheFilterField(): void
    {
        $this->press(KeyPress::character('/'));

        $overlay = $this->app->state->overlays()->current();

        self::assertNotNull($overlay);
        self::assertSame('browser.filter', $overlay->id());
    }

    /** Wpisanie fragmentu zawęża listę **w tej samej klatce**, bez czekania na `Enter`. */
    public function testTypingNarrowsTheListImmediately(): void
    {
        $this->filter('dok');

        self::assertSame(['dokumenty', 'raport-dokumentacja.pdf'], $this->names());
    }

    public function testMatchingIgnoresCase(): void
    {
        $this->filter('DOK');

        self::assertSame(['dokumenty', 'raport-dokumentacja.pdf'], $this->names());
    }

    /** Dopasowanie liczy się w znakach, więc nazwa spoza ASCII zawęża się tak samo. */
    public function testMatchingWorksBeyondAscii(): void
    {
        $this->filter('ŻÓŁ');

        self::assertSame(['zażółć.txt'], $this->names());
    }

    /** Dopasowany fragment widać w klatce — ósmym prymitywem, na właściwej kolumnie. */
    public function testTheMatchedFragmentIsVisibleInTheFrame(): void
    {
        $this->filter('dok');

        $marks = self::marksOf($this->app->browser->draw(ScreenFixture::panel(6, 60)));

        self::assertCount(2, $marks);
        self::assertSame(['dok', 'dok'], array_map(static fn (TextMark $mark): string => $mark->text, $marks));
        // Pierwszy wiersz zaczyna się dopasowaniem, w drugim stoi ono za `raport-`.
        self::assertSame([2, 9], array_map(static fn (TextMark $mark): int => $mark->column, $marks));
    }

    /** Lista bez filtra nie niesie ani jednego prymitywu podświetlenia. */
    public function testAnUnfilteredListCarriesNoHighlightAtAll(): void
    {
        self::assertSame([], self::marksOf($this->app->browser->draw(ScreenFixture::panel(6, 60))));
    }

    /**
     * Zaznaczenie **przeżywa zawężenie**: stoi na tym samym wpisie, choć jego
     * numer w liście jest już inny.
     */
    public function testSelectionSurvivesTheNarrowing(): void
    {
        $this->press(KeyPress::special(Key::ArrowDown, ''));
        $this->press(KeyPress::special(Key::ArrowDown, ''));

        self::assertSame('raport-dokumentacja.pdf', $this->selected());

        $this->filter('dok');

        self::assertSame('raport-dokumentacja.pdf', $this->selected(), 'ten sam wpis, inny numer');
        self::assertSame(['dokumenty', 'raport-dokumentacja.pdf'], $this->names());
    }

    /** Zaznaczenie na wpisie, który wypadł z listy, schodzi na pierwsze dopasowanie. */
    public function testSelectionMovesToTheFirstMatchWhenItsEntryIsGone(): void
    {
        $this->press(KeyPress::special(Key::ArrowDown, ''));

        self::assertSame('notatka.txt', $this->selected());

        $this->filter('dok');

        self::assertSame('dokumenty', $this->selected());
    }

    /**
     * `Enter` zatwierdza: pole znika, **zawężenie zostaje**, a zaznaczenie stoi
     * tam, dokąd użytkownik doszedł.
     */
    public function testEnterKeepsTheNarrowingAndTheEntryReached(): void
    {
        $this->filter('dok');
        $this->press(KeyPress::special(Key::ArrowDown, ''));
        $this->press(KeyPress::special(Key::Enter, ''));

        self::assertFalse($this->app->state->overlays()->isOpen(), 'pole zniknęło');
        self::assertSame(['dokumenty', 'raport-dokumentacja.pdf'], $this->names(), 'lista została zawężona');
        self::assertSame('raport-dokumentacja.pdf', $this->selected());
    }

    /**
     * `Esc` odmawia: pełna lista wraca **wraz z wpisem sprzed otwarcia**, nawet
     * jeśli filtr zdążył przesunąć zaznaczenie gdzie indziej.
     */
    public function testEscapeRestoresTheListAndTheEntryFromBeforeTheFilter(): void
    {
        $this->press(KeyPress::special(Key::ArrowDown, ''));

        self::assertSame('notatka.txt', $this->selected());

        $this->filter('dok');

        self::assertSame('dokumenty', $this->selected(), 'wpis sprzed filtra nie pasuje, więc zaznaczenie zeszło');

        $this->press(KeyPress::special(Key::Escape, ''));

        self::assertFalse($this->app->state->overlays()->isOpen());
        self::assertCount(4, $this->names(), 'pełna lista wróciła');
        self::assertSame('notatka.txt', $this->selected(), 'i wpis, na którym stała');
    }

    /** Strzałki w otwartym polu przesuwają zaznaczenie listy pod spodem. */
    public function testArrowsMoveTheSelectionWhileTheFieldIsOpen(): void
    {
        $this->filter('dok');

        self::assertSame('dokumenty', $this->selected());

        $this->press(KeyPress::special(Key::ArrowDown, ''));

        self::assertSame('raport-dokumentacja.pdf', $this->selected());
    }

    /** `Esc` na liście zawężonej, ale bez pola, zdejmuje sam filtr. */
    public function testEscapeOnTheListDropsTheFilterLeftBehind(): void
    {
        $this->filter('dok');
        $this->press(KeyPress::special(Key::Enter, ''));
        $this->press(KeyPress::special(Key::Escape, ''));

        self::assertCount(4, $this->names());
        self::assertSame('dokumenty', $this->selected(), 'zaznaczenie zostaje tam, dokąd doszło przez filtr');
    }

    /** Filtr znika przy zmianie katalogu — fragment nazwy znaczy tam co innego. */
    public function testTheFilterDisappearsWhenTheDirectoryChanges(): void
    {
        $this->filter('dok');
        $this->press(KeyPress::special(Key::Enter, ''));
        $this->press(KeyPress::special(Key::Enter, ''));

        self::assertSame('/home/dokumenty', $this->directoryPath());
        self::assertSame(['umowa.pdf'], $this->names());

        $this->press(KeyPress::special(Key::Backspace, ''));

        self::assertSame('/home', $this->directoryPath());
        self::assertCount(4, $this->names(), 'po powrocie lista jest pełna');
    }

    /** Filtr, do którego nic nie pasuje, mówi to wprost — a nie „katalog jest pusty”. */
    public function testAnEmptyResultSaysWhyItIsEmpty(): void
    {
        $this->filter('nieistniejące');

        $texts = array_map(
            static fn (TextRun $run): string => $run->text,
            self::runsOf($this->app->browser->draw(ScreenFixture::panel(6, 60))),
        );

        self::assertContains('module.browser.filter.none', $texts);
    }

    /**
     * Zdanie o braku wyników staje **pod nagłówkiem kolumn**, a nie zamiast niego.
     *
     * Usterka zgłoszona 2026-08-12: panel bez wyników tracił wiersz z nazwami
     * kolumn, więc przy podziale ekranu jedna lista miała nagłówek, a druga nie.
     */
    public function testTheColumnHeaderSurvivesAnEmptyResult(): void
    {
        $this->app->state->applySettings($this->app->state->settings()->withModuleValue(
            BrowserSettings::ID,
            BrowserSettings::COLUMN_HEADER,
            true,
        ));

        $this->filter('nieistniejące');

        // Panel szeroki, bo atrapa tłumacza oddaje **klucze**, a te są dłuższe od
        // napisów, które zobaczy użytkownik — w wąskiej kolumnie kończą się
        // wielokropkiem i nie dałyby się porównać.
        $runs = self::runsOf($this->app->browser->draw(ScreenFixture::panel(6, 100)));
        $texts = array_map(static fn (TextRun $run): string => $run->text, $runs);

        self::assertContains('module.browser.column.name', $texts, 'nagłówek kolumn został');
        self::assertContains('module.browser.filter.none', $texts);

        self::assertSame(
            self::rowOf($runs, 'module.browser.column.name') + 1,
            self::rowOf($runs, 'module.browser.filter.none'),
            'zdanie stoi wiersz pod nagłówkiem',
        );
    }

    /**
     * Ponowne otwarcie pola wraca do wpisanego fragmentu, zamiast kasować go
     * w milczeniu: `/` na zawężonej liście znaczy „popraw”, nie „zacznij od nowa”.
     *
     * Sprawdza to `Backspace`, bo tylko on rozróżnia oba zachowania: na polu
     * z treścią skraca filtr do „do”, a na pustym nie robi nic i lista wróciłaby
     * pełna.
     */
    public function testReopeningTheFieldKeepsWhatWasTyped(): void
    {
        $this->filter('dok');
        $this->press(KeyPress::special(Key::Enter, ''));
        $this->press(KeyPress::character('/'));
        $this->press(KeyPress::special(Key::Backspace, ''));

        self::assertSame(['dokumenty', 'raport-dokumentacja.pdf'], $this->names());

        $this->press(KeyPress::special(Key::Backspace, ''));
        $this->press(KeyPress::special(Key::Backspace, ''));

        self::assertCount(4, $this->names(), 'pole opróżnione do końca znaczy brak filtra');
    }

    /** Znacznik w pasie ścieżki jest jedynym śladem filtra po zamknięciu pola. */
    public function testThePathLineCarriesTheFilterMarker(): void
    {
        $this->filter('dok');
        $this->press(KeyPress::special(Key::Enter, ''));

        $header = $this->app->browser->header();

        self::assertNotNull($header);
        self::assertStringContainsString('module.browser.filter.marker', implode('', array_map(
            static fn (TextRun $run): string => $run->text,
            self::runsOf($header->content->draw(new Rect(0, 2, 1, 80))),
        )));
    }

    private function filter(string $fragment): void
    {
        $this->press(KeyPress::character('/'));

        foreach (mb_str_split($fragment) as $character) {
            $this->press(KeyPress::character($character));
        }
    }

    private function press(KeyPress $key): void
    {
        $this->app->input->handle($key, $this->app->state, 0.0);
    }

    /**
     * Nazwy widoczne w liście — czytane z **klatki**, a nie z agregatu.
     *
     * Tak właśnie widzi je użytkownik i tylko tak widać, że zawężenie doszło
     * przez wszystkie trzy piętra: stan panelu, komponent listy i tabelę.
     *
     * @return list<string>
     */
    private function names(): array
    {
        $names = [];

        foreach (self::runsOf($this->app->browser->draw(ScreenFixture::panel(10, 60))) as $run) {
            if ($run->column === ScreenFixture::panel()->column) {
                $names[] = rtrim($run->text, '/');
            }
        }

        return $names;
    }

    private function selected(): ?string
    {
        return $this->app->state->context()->selection;
    }

    private function directoryPath(): string
    {
        return $this->app->state->context()->path;
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<TextRun>
     */
    private static function runsOf(array $primitives): array
    {
        return array_values(array_filter(
            $primitives,
            static fn (Primitive $primitive): bool => $primitive instanceof TextRun,
        ));
    }

    /**
     * Wiersz, w którym stoi napis o podanej treści; `-1` — nie ma go w klatce.
     *
     * @param list<TextRun> $runs
     */
    private static function rowOf(array $runs, string $text): int
    {
        foreach ($runs as $run) {
            if ($run->text === $text) {
                return $run->row;
            }
        }

        return -1;
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<TextMark>
     */
    private static function marksOf(array $primitives): array
    {
        return array_values(array_filter(
            $primitives,
            static fn (Primitive $primitive): bool => $primitive instanceof TextMark,
        ));
    }
}
