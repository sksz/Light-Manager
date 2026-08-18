<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Module\ContextOrigin;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\AddressBook\Application\AddressBook;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Domain\ValueObject\HostTarget;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntry;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntryType;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubAddressBook;
use LightManager\Tests\Support\StubRemoteDirectory;
use LightManager\Tests\Support\StubSshSession;
use LightManager\Tests\Support\StubSshState;
use PHPUnit\Framework\TestCase;

/**
 * Zdalny katalog całą drogą użytkownika (krok 49).
 *
 * Przebieg sprawdza **zdanie-miarę kroku**: po połączeniu widać zdalny katalog
 * z nazwami, rozmiarami i datami, `Enter` wchodzi w podkatalog, `Backspace`
 * wraca wyżej.
 *
 * **Sieci nie ma tu ani przez chwilę** i jest to warunek, którego brak
 * w pierwszym podejściu wypuścił z testu prawdziwe procesy `sftp` do hosta
 * z przykładowego wpisu książki. Odczyt idzie przez `StubRemoteDirectory`,
 * a sesja przez `StubSshSession` — obie atrapy udają jedyne, o co w tej fazie
 * chodzi: że proces potomny właśnie się skończył i czym.
 *
 * Postać ekranu zmienia **takt**, a nie klawisz, więc każdy krok przebiegu
 * przechodzi przez `advanceWork()` — tę samą drogę, którą w aplikacji prowadzi
 * `GameLoop`.
 */
final class RemoteDirectoryFlowTest extends TestCase
{
    private const NOW = 100.0;

    private const COLUMNS = 90;

    private const ROWS = 24;

    private ScreenFixture $app;

    private StubSshSession $sessions;

    private StubAddressBook $hosts;

    private StubSshState $sshState;

    private StubRemoteDirectory $remote;

    protected function setUp(): void
    {
        $this->sessions = new StubSshSession();
        // Wpis książki adresowej wraz z rozdziałem `ssh` — od kroku 60 adres
        // przychodzi stamtąd, a port i login są polami rozdziału.
        $this->hosts = new StubAddressBook(new AddressBook([
            new AddressEntry('00000001', 'biuro', 'example.com', ['ssh' => ['port' => 22, 'user' => 'anna']]),
        ]));
        $this->sshState = new StubSshState();
        $this->remote = new StubRemoteDirectory([
            '/home/anna' => [
                new RemoteEntry('dokumenty', RemoteEntryType::Directory, null, 1_786_795_200, 0o755),
                new RemoteEntry('raport.txt', RemoteEntryType::File, 2048, 1_786_795_200, 0o644),
            ],
            '/home/anna/dokumenty' => [
                new RemoteEntry('umowa.pdf', RemoteEntryType::File, 4096, 1_786_795_200, 0o600),
            ],
        ]);
        $this->app = $this->fixture();
    }

    /**
     * **Sedno kroku**: sesja staje, a ekran modułu sam przechodzi w drugą
     * postać — ze spisu hostów na zdalny katalog.
     */
    public function testConnectingShowsTheRemoteDirectory(): void
    {
        $this->connect();

        $texts = $this->drawCurrent();

        self::assertContains('dokumenty/', $texts, 'katalog z ukośnikiem, jak w liście lokalnej');
        self::assertContains('raport.txt', $texts);
        // Napisy w przebiegu funkcjonalnym są **kluczami**, bo tłumacz jest tu
        // atrapą — stąd `2.0 kB` zamiast `2,0 kB`: separator dziesiętny bierze
        // się z języka, a atrapa żadnego nie zna.
        self::assertContains('2.0 kB', $texts, 'kolumna rozmiaru');
        self::assertContains('rw-r--r--', $texts, 'kolumna praw');
    }

    /** Górny pas mówi, na czyim katalogu stoimy — host i ścieżka naraz. */
    public function testTheHeaderSaysWhereWeAre(): void
    {
        $this->connect();

        self::assertContains(
            'module.ssh.remote.header(host=anna@example.com,path=/home/anna)',
            $this->headerTexts(),
        );
    }

    public function testEnterGoesInAndBackspaceComesBack(): void
    {
        $this->connect();
        $this->press(KeyPress::special(Key::Enter, "\r"));
        $this->advanceWork();

        self::assertContains('umowa.pdf', $this->drawCurrent());

        $this->press(KeyPress::special(Key::Backspace, "\x7f"));
        $this->advanceWork();

        $texts = $this->drawCurrent();

        self::assertContains('dokumenty/', $texts);
        self::assertContains(
            'module.ssh.remote.header(host=anna@example.com,path=/home/anna)',
            $this->headerTexts(),
        );
    }

    /** Filtr zawęża listę i **podświetla dopasowanie** — mechanizm rdzenia z kroku 30. */
    public function testTheFilterNarrowsTheListAndMarksTheMatch(): void
    {
        $this->connect();
        $this->press(KeyPress::character('/'));

        foreach (['r', 'a', 'p'] as $letter) {
            $this->press(KeyPress::character($letter));
        }

        $this->press(KeyPress::special(Key::Enter, "\r"));

        $texts = $this->drawCurrent();

        self::assertContains('raport.txt', $texts);
        self::assertNotContains('dokumenty/', $texts, 'katalog nie pasuje do filtra');
    }

    /**
     * `Ctrl`+`H` zamawia **nowy obieg** i zapisuje wybór w ustawieniach modułu —
     * bo klawisz i pozycja ustawień opisują tę samą rzecz.
     */
    public function testHiddenEntriesAreAskedForAgainAndRemembered(): void
    {
        $this->connect();
        $reads = $this->remote->reads;

        $this->press(KeyPress::ctrl('h'));

        self::assertSame($reads + 1, $this->remote->reads);
        self::assertTrue($this->remote->withHidden);
        self::assertTrue($this->app->settingsStore->saved[0]->moduleValue('ssh', 'showHidden'));
    }

    /**
     * Kontekst sesji jedzie do rdzenia **z pochodzeniem**, bo inaczej moduł
     * opisu pliku odczytałby ścieżkę `lstat`em i pokazał lokalny plik o tej
     * samej nazwie.
     */
    public function testThePublishedContextSaysTheEntryIsRemote(): void
    {
        $this->connect();

        $context = $this->app->state->context();

        self::assertSame(ContextOrigin::Remote, $context->origin);
        self::assertSame('anna@example.com', $context->originLabel);
        self::assertSame('/home/anna', $context->path);
        self::assertSame('dokumenty', $context->selection);
    }

    /** Rozłączenie wraca do spisu hostów — także to niechciane. */
    public function testADroppedSessionReturnsToTheHostBook(): void
    {
        $this->connect();
        $this->sessions->settleFailed($this->profile(), 'module.ssh.problem.dropped');
        $this->advanceWork();

        self::assertContains('biuro', $this->drawCurrent(), 'znowu widać spis hostów');
    }

    /** Ostatni katalog przeżywa sesję: zapisuje się pod nazwą wpisu książki. */
    public function testTheLastDirectoryIsRemembered(): void
    {
        $this->connect();
        $this->press(KeyPress::special(Key::Enter, "\r"));
        $this->advanceWork();

        // Katalog zapamiętuje się pod **identyfikatorem wpisu** książki, a nie
        // pod nazwą (krok 60).
        self::assertSame('/home/anna/dokumenty', $this->sshState->directories['00000001'] ?? null);
    }

    /** Łączy się i doprowadza ekran do postaci zdalnej. */
    private function connect(): void
    {
        $this->press(KeyPress::ctrl('s'));
        $this->sessions->settleConnected($this->profile());
        $this->advanceWork();
    }

    /**
     * Cel — **złożony tak, jak składa go moduł** (krok 60): wiersz książki
     * adresowej plus poświadczenie z sekcji modułu.
     */
    private function profile(): HostProfile
    {
        $entry = $this->hosts->load()->book->find('00000001');
        self::assertNotNull($entry);

        $port = $entry->value('ssh', 'port');
        $user = $entry->value('ssh', 'user');

        return HostTarget::of(
            $entry->id,
            $entry->name,
            $entry->address,
            $this->sshState->credentials($entry->id),
            is_int($port) ? $port : null,
            is_string($user) ? $user : null,
        );
    }

    private function press(KeyPress $key): void
    {
        $this->app->input->handle($key, $this->app->state, self::NOW);
    }

    private function advanceWork(): void
    {
        $this->app->ticker->tick($this->app->state, self::NOW);
        $this->app->input->advanceWork($this->app->state, self::NOW);
    }

    /** @return list<string> */
    private function drawCurrent(): array
    {
        return self::textsOf($this->app->screens->current()->draw(new Rect(0, 0, self::ROWS, self::COLUMNS)));
    }

    /** @return list<string> */
    private function headerTexts(): array
    {
        $header = $this->app->screens->current()->header();

        if ($header === null) {
            return [];
        }

        return self::textsOf($header->content->draw(new Rect(0, 0, 1, self::COLUMNS)));
    }

    /**
     * @param list<\LightManager\Application\Ui\Primitive\Primitive> $primitives
     *
     * @return list<string>
     */
    private static function textsOf(array $primitives): array
    {
        $texts = [];

        foreach ($primitives as $primitive) {
            if ($primitive instanceof TextRun) {
                $texts[] = trim($primitive->text);
            }
        }

        return $texts;
    }

    private function fixture(): ScreenFixture
    {
        $directories = (new InMemoryDirectoryRepository())->add('/', [Entry::file('plik.txt', 10)]);

        return new ScreenFixture(
            $directories->get(new DirectoryPath('/'), false),
            $directories,
            sessions: $this->sessions,
            sshState: $this->sshState,
            addressBook: $this->hosts,
            remote: $this->remote,
        );
    }
}
