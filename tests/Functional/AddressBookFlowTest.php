<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\AddressBook\Application\AddressBook;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubAddressBook;
use LightManager\Tests\Support\StubSshState;
use PHPUnit\Framework\TestCase;

/**
 * Książka adresowa jako moduł — przebieg użytkownika (krok 60).
 *
 * Sprawdza to, czego nie widać w testach jednostkowych: że **jeden wpis widzą
 * dwa moduły**, że pola rozdziału `ssh` przychodzą do łańcucha okien z cudzej
 * deklaracji i że materiał uwierzytelnienia nie wychodzi kwerendą.
 *
 * Żaden z tych przebiegów nie otwiera połączenia ani nie dotyka dokumentu stanu
 * maszyny testowej — porty są atrapami, jak w krokach 48–52.
 */
final class AddressBookFlowTest extends TestCase
{
    private const NOW = 100.0;

    private const COLUMNS = 100;

    private const ROWS = 24;

    private ScreenFixture $app;

    private StubAddressBook $book;

    private StubSshState $sshState;

    protected function setUp(): void
    {
        $this->book = new StubAddressBook(new AddressBook([
            new AddressEntry('0000000a', 'biuro', 'example.com', ['ssh' => ['port' => 2222, 'user' => 'anna']]),
            new AddressEntry('0000000b', 'zapasowy', 'backup.example.com'),
        ]));
        $this->sshState = new StubSshState();
        $this->app = $this->fixture();
    }

    /** `Ctrl`+`W` otwiera książkę, a drugie naciśnięcie ją zamyka. */
    public function testTheShortcutOpensAndClosesTheBook(): void
    {
        $this->press(KeyPress::ctrl('w'));

        self::assertSame('address-book', $this->app->screens->current()->id());

        $this->press(KeyPress::ctrl('w'));

        self::assertSame('browser', $this->app->screens->current()->id());
    }

    /** Spis pokazuje nazwę, adres i identyfikator — ten ostatni wpisuje się w komendach. */
    public function testTheListShowsNameAddressAndIdentifier(): void
    {
        $this->openBook();

        $texts = implode("\n", $this->drawCurrent());

        self::assertStringContainsString('biuro', $texts);
        self::assertStringContainsString('example.com', $texts);
        self::assertStringContainsString('0000000a', $texts);
    }

    /**
     * `F7` prowadzi łańcuchem okien: nazwa → adres → **pola rozdziału `ssh`**.
     *
     * Pola pochodzą z kwerendy `ssh.address-fields` cudzego modułu — czyli
     * z deklaracji, której książka nie zna z kodu, tylko z odpowiedzi (D105 nr 3).
     */
    public function testAddingWalksThroughTheChapterFieldsOfAnotherModule(): void
    {
        $this->openBook();
        $this->press(KeyPress::special(Key::F7, "\e[18~"));

        $this->type('serwis');
        $this->accept();
        $this->type('10.0.0.5');
        $this->accept();

        // Trzecie ogniwo to już pole rozdziału — port SSH, podpowiedziany
        // wartością domyślną z deklaracji modułu Ssh. Kasujemy ją, bo okno
        // wpisuje za kursorem, a nie zamiast treści.
        self::assertSame('prompt', $this->app->state->overlays()->current()?->id(), 'port pyta oknem');
        $this->press(KeyPress::special(Key::Backspace, "\x7f"));
        $this->press(KeyPress::special(Key::Backspace, "\x7f"));
        $this->type('2200');
        $this->accept();
        $this->type('ola');
        $this->accept();

        self::assertNull($this->app->state->overlays()->current(), 'łańcuch się domknął');

        $added = $this->book->load()->book->findByName('serwis');
        self::assertNotNull($added);
        self::assertSame('10.0.0.5', $added->address);
        self::assertSame(2200, $added->value('ssh', 'port'), 'liczba wchodzi jako liczba');
        self::assertSame('ola', $added->value('ssh', 'user'));
    }

    /** Wpis nie do przyjęcia kończy się **zdaniem**, a nie śladem stosu. */
    public function testAnImpossibleAddressIsRefusedWithASentence(): void
    {
        $this->openBook();
        $this->press(KeyPress::special(Key::F7, "\e[18~"));
        $this->type('podstęp');
        $this->accept();
        $this->type('-oProxyCommand=x');
        $this->accept();

        self::assertSame(0, $this->book->saves, 'książka nie zapisuje wpisu, którego nie przyjęła');
        self::assertStringContainsString('module.address-book.entry.address.invalid', $this->message());
    }

    /** `F8` pyta, zanim usunie — bo na identyfikator wpisu powołują się obcy. */
    public function testRemovingAsksFirst(): void
    {
        $this->openBook();
        $this->press(KeyPress::special(Key::F8, "\e[19~"));

        self::assertSame('confirm', $this->app->state->overlays()->current()?->id());
        self::assertSame(0, $this->book->saves);

        $this->press(KeyPress::special(Key::ArrowLeft, "\e[D"));
        $this->accept();

        self::assertSame(1, $this->book->saves);
        self::assertSame(['0000000b'], $this->book->load()->book->ids());
    }

    /** `Ctrl`+`F` zawęża spis, a `Esc` zdejmuje **zawężenie, nie ekran**. */
    public function testTheFilterNarrowsTheListAndEscapeLiftsItFirst(): void
    {
        $this->openBook();
        $this->press(KeyPress::ctrl('f'));
        $this->type('zapas');
        $this->accept();

        $texts = implode("\n", $this->drawCurrent());
        self::assertStringContainsString('zapasowy', $texts);
        self::assertStringNotContainsString('biuro', $texts);

        $this->press(KeyPress::special(Key::Escape, "\e"));

        self::assertSame('address-book', $this->app->screens->current()->id(), 'ekran zostaje');
        self::assertStringContainsString('biuro', implode("\n", $this->drawCurrent()));
    }

    /**
     * **Jeden wpis, dwa moduły**: adres dopisany w książce widać w spisie hostów
     * modułu sesji zdalnej — bez restartu i bez ani jednego typu przez granicę.
     */
    public function testAnEntryAddedInTheBookShowsUpInTheRemoteSessionList(): void
    {
        $this->openBook();
        $this->press(KeyPress::special(Key::F7, "\e[18~"));
        $this->type('serwis');
        $this->accept();
        $this->type('10.0.0.5');
        $this->accept();

        // Wpis powstaje **po adresie**, a pola rozdziału dopisują się po nim —
        // więc `Esc` w polu portu zostawia gotowy adres (patrz `EntryFlow`).
        $this->press(KeyPress::special(Key::Escape, "\e"));
        $this->press(KeyPress::ctrl('s'));

        self::assertStringContainsString('serwis', implode("\n", $this->drawCurrent()));
    }

    /**
     * **Materiał uwierzytelnienia nie wychodzi kwerendą** (11w).
     *
     * Test przeszukuje spłaszczone wiersze obu kwerend książki za ścieżką klucza
     * — wzorem kroku 58, w którym ta sama granica pilnowała celu tunelu.
     */
    public function testNoAuthenticationMaterialLeavesThroughTheQueries(): void
    {
        $this->sshState->saveCredentials(
            '0000000a',
            new \LightManager\Module\Ssh\Domain\ValueObject\HostCredentials(
                \LightManager\Module\Ssh\Domain\ValueObject\AuthMethod::Key,
                '/sekrety/klucz',
            ),
        );

        $flattened = '';

        foreach (['address-book.entries', 'address-book.entry'] as $name) {
            foreach ($this->app->state->queries()->ask($name)->rows() as $row) {
                $flattened .= implode('|', array_map(strval(...), $row));
            }
        }

        self::assertStringNotContainsString('/sekrety/', $flattened);
        self::assertStringNotContainsString('keyPath', $flattened);
    }

    /**
     * Otwiera książkę — **po takcie**, jak robi to pętla aplikacji.
     *
     * Takt jest tu potrzebny z powodu, który warto nazwać: to w nim moduł sesji
     * zdalnej zakłada swój rozdział książki (krok 60). Przebieg pomijający takt
     * sprawdzałby aplikację, w której nikt się książce nie przedstawił — czyli
     * nie tę, którą uruchamia użytkownik.
     */
    private function openBook(): void
    {
        $this->app->ticker->tick($this->app->state, self::NOW);
        $this->press(KeyPress::ctrl('w'));
    }

    private function type(string $text): void
    {
        foreach (mb_str_split($text) as $character) {
            $this->press(KeyPress::character($character));
        }
    }

    private function accept(): void
    {
        $this->press(KeyPress::special(Key::Enter, "\r"));
    }

    private function press(KeyPress $key): void
    {
        $this->app->input->handle($key, $this->app->state, self::NOW);
    }

    private function message(): string
    {
        $message = $this->app->state->message();
        self::assertNotNull($message, 'zdanie w pasku stanu');

        return $message->text;
    }

    /** @return list<string> */
    private function drawCurrent(): array
    {
        return self::textsOf($this->app->screens->current()->draw(new Rect(0, 0, self::ROWS, self::COLUMNS)));
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

    private function fixture(): ScreenFixture
    {
        $directories = (new InMemoryDirectoryRepository())->add('/', [Entry::file('plik.txt', 1)]);

        return new ScreenFixture(
            $directories->get(new DirectoryPath('/'), false),
            $directories,
            sshState: $this->sshState,
            addressBook: $this->book,
        );
    }
}
