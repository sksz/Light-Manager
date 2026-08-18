<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * Okno kwerend jako **drugi tryb okna komend** (krok 53, D92 nr 7).
 *
 * Przebieg użytkownika, a nie jednostka: `F12` otwiera okno, `Tab` przy pustym
 * polu przełącza tryb, wpisana nazwa pyta rejestr, a odpowiedź staje w tym samym
 * oknie. Test pilnuje przy tym rzeczy, której nie widać w żadnym teście
 * jednostkowym: **spis jest widokiem na rejestr**, więc pokazuje dokładnie to,
 * co w nim stoi — ani jednej pozycji więcej.
 */
final class QueryWindowFlowTest extends TestCase
{
    private const NOW = 1000.0;

    private ScreenFixture $app;

    protected function setUp(): void
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/home', [Entry::directory('dokumenty'), Entry::file('notatka.txt', 12)])
            ->add('/home/dokumenty', []);

        $this->app = new ScreenFixture(
            $directories->get(new DirectoryPath('/home'), false),
            $directories,
        );
    }

    /** Okno otwiera się w trybie czynności — `F12` ma znaczyć zawsze to samo. */
    public function testTheWindowOpensOnCommands(): void
    {
        $this->special(Key::F12);

        self::assertStringContainsString('layout.zone.command', $this->overlayText());
    }

    /** `Tab` przy pustym polu przełącza tryb, a tytuł okna to pokazuje. */
    public function testTabOnAnEmptyLineSwitchesToQueries(): void
    {
        $this->special(Key::F12);
        $this->special(Key::Tab);

        $text = $this->overlayText();

        self::assertStringContainsString('layout.zone.query', $text);
        self::assertStringNotContainsString('layout.zone.command', $text);
    }

    /** Drugi `Tab` wraca do czynności — tryb jest przełącznikiem, nie drogą w jedną stronę. */
    public function testTabAgainComesBack(): void
    {
        $this->special(Key::F12);
        $this->special(Key::Tab);
        $this->special(Key::Tab);

        self::assertStringContainsString('layout.zone.command', $this->overlayText());
    }

    /** Zamknięcie i ponowne otwarcie zaczyna od czynności. */
    public function testReopeningStartsOnCommandsAgain(): void
    {
        $this->special(Key::F12);
        $this->special(Key::Tab);
        $this->special(Key::Escape);
        $this->special(Key::F12);

        self::assertStringContainsString('layout.zone.command', $this->overlayText());
    }

    /** Rdzeń wnosi swoje źródła danych tak samo, jak wnosi komendy. */
    public function testTheCoreBringsItsOwnDataSources(): void
    {
        $names = array_map(
            static fn (QueryInterface $query): string => $query->name(),
            $this->app->state->queries()->all(),
        );

        self::assertContains('core.settings', $names);
        self::assertContains('core.modules', $names);
        self::assertContains('core.queries', $names);
        self::assertContains('core.context', $names);
    }

    /**
     * Spis jest **widokiem na rejestr**: pokazuje jego początek w kolejności
     * alfabetycznej, a nie drugą, ręcznie prowadzoną listę.
     *
     * Sprawdzamy początek, bo okno zajmuje najwyżej pół ekranu i reszta pozycji
     * czeka za przewinięciem — tak samo, jak przy komendach.
     */
    public function testTheListIsAViewOnTheRegistry(): void
    {
        $this->special(Key::F12);
        $this->special(Key::Tab);

        $text = $this->overlayText();
        $names = array_map(
            static fn (QueryInterface $query): string => $query->name(),
            $this->app->state->queries()->all(),
        );

        // Pierwsza alfabetycznie jest od kroku 60 kwerenda książki adresowej;
        // do tamtego kroku była nią `audio.effects`.
        self::assertSame('address-book.entries', $names[0], 'rejestr trzyma kolejność alfabetyczną');

        foreach (array_slice($names, 0, 8) as $name) {
            self::assertStringContainsString($name, $text, 'spis pomija ' . $name);
        }

        self::assertStringContainsString('module.audio.query.effects', $text, 'opis idzie przez katalog');
    }

    /** Wpisana kwerenda odpowiada **w tym samym oknie**, a okno zostaje otwarte. */
    public function testAskingShowsTheAnswerInTheWindow(): void
    {
        $this->ask('core.theme');

        $text = $this->overlayText();

        self::assertStringContainsString('layout.zone.query', $text, 'okno zostaje otwarte');
        self::assertStringContainsString('grafit', $text);
    }

    /** Odpowiedź jednowierszowa rozkłada się na pary `pole: wartość`. */
    public function testASingleRecordIsShownAsFieldsAndValues(): void
    {
        $this->ask('core.viewport');

        $text = $this->overlayText();

        self::assertStringContainsString('rows', $text);
        self::assertStringContainsString('columns', $text);
        self::assertStringContainsString('renderer', $text);
    }

    /** Kwerenda z argumentem — wiersz rozbiera ten sam parser, co przy komendach. */
    public function testAskingWithAnArgument(): void
    {
        $this->ask('browser.entries 0');

        self::assertStringContainsString('notatka.txt', $this->overlayText());
    }

    /**
     * Nieznane źródło mówi zdaniem i nie zamyka okna.
     *
     * Przykładem był do kroku 54 `docker.images` — i przestał nim być dokładnie
     * wtedy, gdy tamten krok dał modułowi Dockera kwerendy. Nazwa jest odtąd
     * spoza **przestrzeni jakiegokolwiek modułu**, więc nie ma jak przestać być
     * nieznana przy następnym rozszerzeniu aplikacji.
     */
    public function testAnUnknownNameIsAnsweredWithASentence(): void
    {
        $this->ask('nosuchmodule.things');

        $message = $this->app->state->message();

        self::assertNotNull($message, 'nieznane źródło mówi zdaniem');
        self::assertStringContainsString('query.problem.unknown', $message->text);
        self::assertTrue($this->app->state->overlays()->isOpen());
    }

    /** Kwerenda **niczego nie zmienia**: zaznaczenie pod spodem zostaje na miejscu. */
    public function testAskingChangesNothingUnderneath(): void
    {
        $before = $this->app->state->context()->selection;

        $this->ask('browser.entries');

        self::assertSame($before, $this->app->state->context()->selection);
    }

    private function ask(string $line): void
    {
        $this->special(Key::F12);
        $this->special(Key::Tab);

        foreach (mb_str_split($line) as $character) {
            $this->character($character);
        }

        $this->special(Key::Enter);
    }

    private function character(string $character): bool
    {
        return $this->app->input->handle(KeyPress::character($character), $this->app->state, self::NOW);
    }

    private function special(Key $key): bool
    {
        return $this->app->input->handle(KeyPress::special($key, ''), $this->app->state, self::NOW);
    }

    private function overlayText(): string
    {
        $overlay = $this->app->state->overlays()->current();

        if ($overlay === null) {
            return '';
        }

        return implode('|', array_map(
            static fn (TextRun $run): string => $run->text,
            self::runsOf($overlay->draw($overlay->bounds(30, 120))),
        ));
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<TextRun>
     */
    private static function runsOf(array $primitives): array
    {
        $runs = [];

        foreach ($primitives as $primitive) {
            if ($primitive instanceof TextRun) {
                $runs[] = $primitive;
            }
        }

        return $runs;
    }
}
