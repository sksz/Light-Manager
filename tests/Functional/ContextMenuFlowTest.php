<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * Menu kontekstowe od klawisza do skutku (krok 32).
 *
 * Przebieg sprawdza to, czego nie widać w żadnej klasie z osobna: że menu jest
 * **drugim wejściem do rejestru komend**, a nie drugim zbiorem czynności.
 * Najważniejszy jest tu test wołający obie drogi — wybór pozycji i wpisanie tej
 * samej nazwy w oknie komend — bo dopiero on odpowiada, czy „dokładnie to samo”
 * jest prawdą, czy tylko zamiarem.
 */
final class ContextMenuFlowTest extends TestCase
{
    private const NOW = 1000.0;

    private ScreenFixture $app;

    protected function setUp(): void
    {
        $this->app = self::fixture();
    }

    /**
     * Menu na katalogu: wejście, opis wpisu i **trzy operacje na plikach**.
     *
     * Do kroku 47 pozycje były dwie, bo czynności kroku 41 nie umiały otworzyć
     * okna (D75, rozstrzygnięcie 5). Zdolność `OpensOverlay` to zmieniła i ten
     * test jest dowodem spłaty tamtego długu.
     */
    public function testMenuOnADirectoryShowsWhatCanBeDoneWithADirectory(): void
    {
        $this->special(Key::F9);

        self::assertTrue($this->app->state->overlays()->isOpen());
        self::assertSame(
            ['browser.delete', 'browser.mkdir', 'browser.open', 'browser.rename', 'file-info.show'],
            $this->itemsOnScreen(),
        );
    }

    /**
     * Menu na pliku traci pozycję wejścia — bo tylko ona wymaga katalogu.
     * Operacje zostają: zmienić nazwę i usunąć wolno jedno i drugie.
     */
    public function testMenuOnAFileShowsLess(): void
    {
        $this->special(Key::ArrowDown);
        $this->special(Key::F9);

        self::assertSame(
            ['browser.delete', 'browser.mkdir', 'browser.rename', 'file-info.show'],
            $this->itemsOnScreen(),
        );
    }

    /**
     * Przełączniki widoku są w rejestrze, ale **nie w menu**, i po kroku 47
     * granica brzmi inaczej niż w D69: menu pokazuje czynności zmieniające
     * **zawartość miejsca**, a nie **sposób oglądania** tego miejsca. Przy
     * takiej granicy `browser.mkdir` wchodzi (tworzy wpis), a `browser.hidden`
     * i `browser.tree` zostają poza — mimo że żadna z tych trzech nie dotyczy
     * zaznaczenia.
     */
    public function testViewCommandsStayOutOfTheMenu(): void
    {
        $this->special(Key::F9);

        $items = $this->itemsOnScreen();

        self::assertNotContains('browser.hidden', $items);
        self::assertNotContains('browser.tree', $items);
        self::assertNotNull($this->app->commandRegistry->find('browser.hidden'), 'ale w rejestrze są');
        self::assertNotNull($this->app->commandRegistry->find('browser.tree'));
    }

    /**
     * Pusty katalog: menu **otwiera się z jedną pozycją** — i to jest zmiana,
     * którą przyniósł krok 47.
     *
     * Do niego pusty katalog dostawał zdanie „nie ma czego pokazać”, bo każda
     * pozycja wymagała zaznaczenia, a zaznaczenia w pustym katalogu nie ma.
     * `browser.mkdir` zaznaczenia nie wymaga i jest jedyną czynnością, która ma
     * tu sens — pusty katalog to dokładnie to miejsce, w którym chce się coś
     * utworzyć. Sam mechanizm „menu bez pozycji się nie otwiera” zostaje
     * i pilnuje go `MenuOverlayTest`.
     */
    public function testEmptyDirectoryOffersTheOnlyThingThatMakesSenseThere(): void
    {
        $this->special(Key::Enter);
        $this->special(Key::F9);

        self::assertTrue($this->app->state->overlays()->isOpen());
        self::assertSame(['browser.mkdir'], $this->itemsOnScreen());
    }

    /**
     * **Kryterium ukończenia kroku**: wybranie pozycji robi dokładnie to samo,
     * co komenda o tej nazwie wpisana w oknie komend.
     *
     * Obie drogi jadą przez osobne zestawy aplikacji, bo każda zmienia stan —
     * porównanie ma dotyczyć skutku, a nie kolejności.
     */
    public function testChoosingAnItemDoesExactlyWhatTheNamedCommandDoes(): void
    {
        $throughMenu = self::fixture();
        $throughMenu->input->handle(KeyPress::special(Key::F9, ''), $throughMenu->state, self::NOW);

        // `browser.open` stoi w menu trzecia (po `delete` i `mkdir`), a wybór
        // idzie strzałkami — pozycji nie wskazuje się numerem nigdzie indziej.
        $throughMenu->input->handle(KeyPress::special(Key::ArrowDown, ''), $throughMenu->state, self::NOW);
        $throughMenu->input->handle(KeyPress::special(Key::ArrowDown, ''), $throughMenu->state, self::NOW);
        $throughMenu->input->handle(KeyPress::special(Key::Enter, "\r"), $throughMenu->state, self::NOW);

        $throughWindow = self::fixture();
        $throughWindow->input->handle(KeyPress::special(Key::F12, ''), $throughWindow->state, self::NOW);

        foreach (mb_str_split('browser.open') as $character) {
            $throughWindow->input->handle(KeyPress::character($character), $throughWindow->state, self::NOW);
        }

        $throughWindow->input->handle(KeyPress::special(Key::Enter, "\r"), $throughWindow->state, self::NOW);

        self::assertSame('/home/dokumenty', $throughMenu->state->context()->path);
        self::assertSame(
            $throughWindow->state->context()->path,
            $throughMenu->state->context()->path,
            'obie drogi kończą się w tym samym katalogu',
        );
        self::assertFalse($throughMenu->state->overlays()->isOpen(), 'menu zamyka się po wykonaniu');
    }

    /** Pozycja otwierająca ekran modułu robi to samo, co skrót `Ctrl`+`D`. */
    public function testItemThatOpensAModuleScreenOpensIt(): void
    {
        $this->special(Key::ArrowDown);
        $this->special(Key::F9);

        // Na pliku menu ma cztery pozycje, a `file-info.show` jest ostatnia.
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowDown);
        $this->special(Key::Enter);

        self::assertSame('file-info', $this->app->screens->current()->id());
    }

    /** `Esc` zamyka menu i nie zostawia po sobie ani zmiany katalogu, ani komunikatu. */
    public function testEscapeClosesTheMenuAndChangesNothing(): void
    {
        $this->special(Key::F9);
        $this->special(Key::Escape);

        self::assertFalse($this->app->state->overlays()->isOpen());
        self::assertSame('/home', $this->app->state->context()->path);
        self::assertNull($this->app->state->message());
    }

    /** Menu jest modalne jak każde okno: `F10` przechodzi, strzałka pod spód — nie. */
    public function testMenuIsModalButLetsTheGlobalKeysThrough(): void
    {
        $before = $this->app->state->context()->selection;

        $this->special(Key::F9);
        $this->special(Key::ArrowDown);

        self::assertSame($before, $this->app->state->context()->selection, 'lista pod spodem stoi');

        self::assertTrue($this->special(Key::F10));
    }

    /** Wybór z menu nie jest pisaniem, więc nie ma czego pamiętać w historii komend. */
    public function testMenuDoesNotWriteToTheCommandHistory(): void
    {
        $this->special(Key::F9);
        $this->special(Key::ArrowDown);
        $this->special(Key::ArrowDown);
        $this->special(Key::Enter);

        self::assertSame([], $this->app->history->entries);
    }

    private static function fixture(): ScreenFixture
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/home', [Entry::directory('dokumenty'), Entry::file('notatka.txt', 12)])
            ->add('/home/dokumenty', []);

        return new ScreenFixture($directories->get(new DirectoryPath('/home'), false), $directories);
    }

    /**
     * Nazwy pozycji odczytane z narysowanego menu — czyli z tego, co użytkownik
     * naprawdę widzi.
     *
     * @return list<string>
     */
    private function itemsOnScreen(): array
    {
        $overlay = $this->app->state->overlays()->current();

        if ($overlay === null) {
            return [];
        }

        $names = [];

        foreach ($overlay->draw($overlay->bounds(24, 80)) as $primitive) {
            if (!$primitive instanceof TextRun) {
                continue;
            }

            $text = trim($primitive->text);

            if ($this->app->commandRegistry->find($text) !== null) {
                $names[] = $text;
            }
        }

        return $names;
    }

    private function special(Key $key): bool
    {
        return $this->app->input->handle(KeyPress::special($key, ''), $this->app->state, self::NOW);
    }
}
