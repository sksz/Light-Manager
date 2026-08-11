<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Cli\Screen;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Presentation\Cli\Screen\HelpScreen;
use LightManager\Presentation\Ui\Component\Section;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * Zakładka „Sterowanie” jako pierwszy prawdziwy użytkownik zwijanej sekcji
 * (krok 22).
 *
 * Komponent pokryty samym testem to API zaprojektowane na domysł (krok 18, P5) —
 * dlatego krok przestawia na sekcje spis, który już istniał i który po dołożeniu
 * modułów przestał się mieścić w oknie. Ten test patrzy na niego tak, jak patrzy
 * użytkownik: przez klatkę i przez klawisze.
 *
 * Napisy są tu **kluczami**, bo `ScreenFixture` wstrzykuje `StubTranslator` —
 * test sprawdza budowę spisu, a nie brzmienie katalogu.
 */
final class HelpScreenSectionsTest extends TestCase
{
    private ScreenFixture $app;

    protected function setUp(): void
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/home', [Entry::directory('dokumenty'), Entry::file('notatka.txt', 12)])
            ->add('/home/dokumenty', []);

        $this->app = new ScreenFixture($directories->get(new DirectoryPath('/home'), false), $directories);
    }

    public function testEveryGroupOfKeysIsASectionWithItsOwnHeader(): void
    {
        self::assertSame(
            ['help.section.global', 'layout.zone.settings', 'layout.zone.help', 'layout.zone.command'],
            $this->labels($this->app->help),
            'wiązania rdzenia dostały nagłówek, którego przed krokiem 22 nie miały',
        );
    }

    public function testEnterCollapsesTheSectionUnderTheCursorAndHidesItsKeys(): void
    {
        $help = $this->app->help;

        self::assertContains('F10', $this->keyColumn($help), 'wyjście jest wiązaniem rdzenia — pierwsza sekcja');

        $help->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertNotContains('F10', $this->keyColumn($help), 'zwinięta sekcja chowa swoje wiersze');
        self::assertContains(
            'help.section.global',
            $this->labels($help),
            'nagłówek zostaje — inaczej nie dałoby się rozwinąć',
        );
    }

    public function testEnterASecondTimeBringsTheKeysBack(): void
    {
        $help = $this->app->help;
        $help->handle(KeyPress::special(Key::Enter, "\r"));
        $help->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertContains('F10', $this->keyColumn($help));
    }

    public function testArrowDownMovesTheCursorToTheNextSection(): void
    {
        $help = $this->app->help;
        $help->handle(KeyPress::special(Key::ArrowDown, "\e[B"));
        $help->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(
            [Section::OPEN, Section::CLOSED],
            [$this->markerOf($help, 'help.section.global'), $this->markerOf($help, 'layout.zone.settings')],
            'zwinęła się druga sekcja, bo na niej stanął kursor',
        );
    }

    /** Znacznik mówi o stanie sekcji, zanim użytkownik cokolwiek naciśnie. */
    public function testEverySectionStartsExpanded(): void
    {
        foreach ($this->headers($this->app->help) as $header) {
            self::assertStringStartsWith(Section::OPEN, $header);
        }
    }

    public function testCursorStopsAtTheLastSection(): void
    {
        $help = $this->app->help;

        for ($step = 0; $step < 10; ++$step) {
            $help->handle(KeyPress::special(Key::ArrowDown, "\e[B"));
        }

        $help->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(
            [Section::OPEN, Section::OPEN, Section::OPEN, Section::CLOSED],
            $this->markers($help),
            'kursor nie wyjeżdża poza ostatnią sekcję, więc zwija właśnie ją',
        );
    }

    public function testCollapseSurvivesSwitchingTabsThereAndBack(): void
    {
        $help = $this->app->help;
        $help->handle(KeyPress::special(Key::Enter, "\r"));
        $help->handle(KeyPress::special(Key::ArrowRight, "\e[C"));
        $help->handle(KeyPress::special(Key::ArrowLeft, "\e[D"));

        self::assertNotContains('F10', $this->keyColumn($help), 'zwinięcie wisi na kluczu sekcji, nie na klatce');
    }

    /**
     * Ponowne otwarcie pomocy sprowadza **kursor** na górę, ale nie rozwija tego,
     * co użytkownik zwinął. Zwinięcie jest jego decyzją i ma trwać tyle, co
     * uruchomienie aplikacji — inaczej chowanie sekcji, do której się nie zagląda,
     * trzeba by powtarzać po każdym `Esc`.
     */
    public function testReopeningHelpKeepsWhatTheUserCollapsedAndBringsTheCursorBack(): void
    {
        $help = $this->app->help;
        $help->handle(KeyPress::special(Key::ArrowDown, "\e[B"));
        $help->handle(KeyPress::special(Key::Enter, "\r"));
        $help->reset();

        self::assertSame(Section::CLOSED, $this->markerOf($help, 'layout.zone.settings'));

        $help->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(
            [Section::CLOSED, Section::CLOSED, Section::OPEN, Section::OPEN],
            $this->markers($help),
            'kursor wrócił na pierwszą sekcję, więc Enter zwinął ją, a nie drugą',
        );
    }

    /** Zakładka „Aplikacja” zostaje płaska — `Enter` nie ma tam czego zwijać. */
    public function testEnterDoesNothingOnTheFlatTab(): void
    {
        $help = $this->app->help;
        $help->handle(KeyPress::special(Key::ArrowRight, "\e[C"));

        $before = self::textsOf($help->draw(self::bounds()));
        $help->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame($before, self::textsOf($help->draw(self::bounds())));
        self::assertSame([], $this->headers($help), 'płaska zakładka nie ma nagłówków sekcji');
    }

    private static function bounds(): Rect
    {
        return new Rect(0, 0, 40, 60);
    }

    /**
     * Wiersze nagłówków sekcji z klatki — poznaje się je po znaczniku.
     *
     * @return list<string>
     */
    private function headers(HelpScreen $help): array
    {
        $headers = [];

        foreach (self::textsOf($help->draw(self::bounds())) as $text) {
            if (str_starts_with($text, Section::OPEN) || str_starts_with($text, Section::CLOSED)) {
                $headers[] = $text;
            }
        }

        return $headers;
    }

    /**
     * Same etykiety sekcji, bez znacznika i odstępu.
     *
     * @return list<string>
     */
    private function labels(HelpScreen $help): array
    {
        return array_map(static fn (string $header): string => mb_substr($header, 2), $this->headers($help));
    }

    /**
     * Same znaczniki, w kolejności sekcji.
     *
     * @return list<string>
     */
    private function markers(HelpScreen $help): array
    {
        return array_map(static fn (string $header): string => mb_substr($header, 0, 1), $this->headers($help));
    }

    /** Znacznik sekcji o zadanej etykiecie albo zdanie mówiące, że jej nie ma. */
    private function markerOf(HelpScreen $help, string $label): string
    {
        foreach ($this->headers($help) as $header) {
            if (mb_substr($header, 2) === $label) {
                return mb_substr($header, 0, 1);
            }
        }

        return '(brak sekcji ' . $label . ')';
    }

    /**
     * Kolumna z nazwami klawiszy — pierwsze słowo każdego wiersza treści.
     *
     * @return list<string>
     */
    private function keyColumn(HelpScreen $help): array
    {
        $keys = [];

        foreach (self::textsOf($help->draw(self::bounds())) as $text) {
            $first = strtok(trim($text), ' ');

            if ($first !== false) {
                $keys[] = $first;
            }
        }

        return $keys;
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
