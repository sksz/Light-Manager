<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\Scrollbar;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * Zakładka ustawień dłuższa od okna **przewija się**, zamiast gubić pozycje
 * (krok 47, dług C).
 *
 * Defekt był starszy od trzech faz planu i niewidoczny w żadnej klasie z osobna:
 * `VStack` dzieli wiersze przez `Distribution`, a szczelina, której wiersza nie
 * starczyło, **nie rysuje się wcale** (reguła 11e). Zakładka po prostu kończyła
 * się w połowie — bez przycięcia, bez wielokropka, bez suwaka. Dlatego przebieg
 * rysuje ekran w **niskim oknie** i czyta narysowane wiersze: tylko tak widać
 * różnicę między „pozycji nie ma” a „pozycji nie widać”.
 */
final class SettingsScrollFlowTest extends TestCase
{
    private const NOW = 1000.0;

    /** Okno, w którym zakładka o jedenastu pozycjach przestaje się mieścić. */
    private const LOW = 10;

    private ScreenFixture $app;

    protected function setUp(): void
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/home', [Entry::directory('dokumenty'), Entry::file('notatka.txt', 12)]);

        $this->app = new ScreenFixture($directories->get(new DirectoryPath('/home'), false), $directories);
        $this->press(Key::F2);
    }

    /** Ostatnia pozycja najdłuższej zakładki jest osiągalna — dotąd nie była wcale. */
    public function testTheLastItemOfALongTabBecomesVisibleAfterScrolling(): void
    {
        $this->longestTab();

        $first = $this->rowsOnScreen();

        self::assertNotSame([], $first);

        $this->press(Key::End);

        $last = $this->rowsOnScreen();

        self::assertNotSame($first, $last, 'widok pojechał za kursorem');
        self::assertNotSame(
            $first[count($first) - 1] ?? '',
            $last[count($last) - 1] ?? '',
            'ostatni widoczny wiersz to już inna pozycja',
        );
    }

    /** Pasek zakładek **nie przewija się** — zostaje jedynym wskaźnikiem miejsca. */
    public function testTheTabBarStaysPutWhileTheContentScrolls(): void
    {
        $this->longestTab();

        $before = $this->rowsOnScreen()[0] ?? '';

        $this->press(Key::End);

        self::assertSame($before, $this->rowsOnScreen()[0] ?? '', 'pierwszy wiersz to nadal pasek zakładek');
    }

    /** Suwak pojawia się dopiero wtedy, gdy jest co przewijać. */
    public function testTheScrollbarShowsUpOnlyWhenTheTabIsTooLong(): void
    {
        $this->longestTab();

        self::assertTrue($this->hasScrollbar(self::LOW), 'niskie okno: zakładka się nie mieści');
        self::assertFalse($this->hasScrollbar(40), 'wysokie okno: nie ma czego przewijać');
    }

    /**
     * **Suwak nie siada na wartościach** — treść oddaje mu kolumnę na czas
     * przewijania (wzorem `Table`, reguła 11e).
     *
     * Usterkę pokazał dopiero prawdziwy terminal: wartości wyrównane do prawej
     * krawędzi („1024”, „30”) wchodziły pod szynę, bo szczeliny `VStack`
     * dostawały pełną szerokość strefy. Test porównuje **prawą krawędź napisów**
     * z kolumną suwaka; sam pomiar przewijania by tego nie złapał, bo pozycje
     * były na swoich miejscach — tylko o kolumnę za szerokie.
     */
    public function testTheScrollbarDoesNotSitOnTheValues(): void
    {
        $this->longestTab();

        $bar = null;
        $rightmost = 0;

        foreach ($this->app->settings->draw(new Rect(0, 0, self::LOW, 80)) as $primitive) {
            if ($primitive instanceof Scrollbar) {
                $bar = $primitive->bounds->column;

                continue;
            }

            if ($primitive instanceof TextRun) {
                $rightmost = max($rightmost, $primitive->column + mb_strlen($primitive->text) - 1);
            }
        }

        self::assertNotNull($bar, 'zakładka jest dłuższa od okna, więc suwak stoi');
        self::assertLessThan($bar, $rightmost, 'napisy kończą się przed szyną suwaka');
    }

    /**
     * Położenie pamięta się **osobno dla każdej zakładki** (`useContext()`,
     * wzorem `SectionState` z kroku 22).
     */
    public function testEachTabRemembersItsOwnPosition(): void
    {
        $this->longestTab();
        $this->press(Key::End);

        $scrolled = $this->rowsOnScreen();

        // Wyjście na pasek zakładek, zakładka obok i z powrotem.
        $this->press(Key::Home);
        $this->press(Key::ArrowUp);
        $this->press(Key::ArrowRight);
        $this->press(Key::ArrowLeft);

        self::assertNotSame($scrolled, $this->rowsOnScreen(), 'wróciliśmy na początek zakładki');
    }

    /** `PageDown` skacze dalej niż strzałka, a `Home` wraca na pierwszą pozycję. */
    public function testPageAndHomeMoveByMoreThanOneRow(): void
    {
        $this->longestTab();

        $this->press(Key::ArrowDown);
        $afterArrow = $this->rowsOnScreen();

        $this->press(Key::PageDown);
        $afterPage = $this->rowsOnScreen();

        self::assertNotSame($afterArrow, $afterPage, 'strona przesuwa widok, strzałka jeszcze nie');

        $this->press(Key::Home);

        self::assertSame($afterArrow[0] ?? '', $this->rowsOnScreen()[0] ?? '');
        self::assertNotSame($afterPage, $this->rowsOnScreen(), 'Home wraca na początek');
    }

    /**
     * Wejście w treść **najdłuższej** zakładki: pasek zakładek przewija się
     * w prawo tyle razy, ile jest zakładek, a widok zatrzymuje się na tej,
     * która ma najwięcej pozycji.
     */
    private function longestTab(): void
    {
        $best = 0;
        $bestCount = -1;
        $tabs = ScreenFixture::settingsTabs($this->app->modules);

        foreach ($tabs as $index => $tab) {
            if ($tab->itemCount() > $bestCount) {
                $bestCount = $tab->itemCount();
                $best = $index;
            }
        }

        for ($step = 0; $step < $best; ++$step) {
            $this->press(Key::ArrowRight);
        }

        $this->press(Key::ArrowDown);

        self::assertGreaterThan(self::LOW, $bestCount + 3, 'zakładka musi być dłuższa od okna');
    }

    /** @return list<string> wiersze narysowane w niskim oknie, w kolejności od góry */
    private function rowsOnScreen(int $rows = self::LOW): array
    {
        $lines = [];

        foreach ($this->app->settings->draw(new Rect(0, 0, $rows, 80)) as $primitive) {
            if (!$primitive instanceof TextRun) {
                continue;
            }

            $lines[$primitive->row] = ($lines[$primitive->row] ?? '') . trim($primitive->text);
        }

        ksort($lines);

        return array_values($lines);
    }

    private function hasScrollbar(int $rows): bool
    {
        foreach ($this->app->settings->draw(new Rect(0, 0, $rows, 80)) as $primitive) {
            if ($primitive instanceof Scrollbar) {
                return true;
            }
        }

        return false;
    }

    private function press(Key $key): void
    {
        $this->app->input->handle(KeyPress::special($key, "\e"), $this->app->state, self::NOW);
    }
}
