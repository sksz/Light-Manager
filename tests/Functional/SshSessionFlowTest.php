<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\AddressBook\Application\AddressBook;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Ssh\Application\RemoteTransferState;
use LightManager\Module\Ssh\Application\SessionStage;
use LightManager\Module\Ssh\Application\TransferDirection;
use LightManager\Module\Ssh\Domain\ValueObject\AuthMethod;
use LightManager\Module\Ssh\Domain\ValueObject\HostCredentials;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Domain\ValueObject\HostTarget;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntry;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntryType;
use LightManager\Presentation\Ui\Module\ReadsContext;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubAddressBook;
use LightManager\Tests\Support\StubRemoteDirectory;
use LightManager\Tests\Support\StubSshSession;
use LightManager\Tests\Support\StubSshState;
use PHPUnit\Framework\TestCase;

/**
 * Sesja zdalna całą drogą użytkownika (krok 48).
 *
 * Przebieg sprawdza **zdanie-miarę kroku**: `Ctrl`+`S` pokazuje spis hostów,
 * `Enter` łączy z podświetlonym, pasek górny mówi, z kim aplikacja jest
 * połączona, a host o nieznanym odcisku **zatrzymuje połączenie pytaniem**, na
 * które trzeba odpowiedzieć.
 *
 * Klawisze idą przez `InputHandler`, bo skrót modułu jest klawiszem
 * **globalnym** i bez rdzenia w ogóle by nie zadziałał; pracę posuwa
 * `advanceWork()` — ta sama droga, którą w aplikacji prowadzi ją `GameLoop`
 * przez `RunsWork`. Sprawdzamy mechanizm, a nie jego atrapę.
 *
 * **Sieci nie ma tu ani przez chwilę.** `StubSshSession::settle*()` udaje
 * jedyne, o co w tym kroku chodzi: że proces potomny właśnie się skończył —
 * i czym. Reguła całej Fazy XVII jest w tym miejscu dosłowna (D84, D87).
 */
final class SshSessionFlowTest extends TestCase
{
    private const NOW = 100.0;

    private const COLUMNS = 90;

    private const ROWS = 24;

    private ScreenFixture $app;

    private StubSshSession $sessions;

    private StubAddressBook $hosts;

    private StubSshState $sshState;

    protected function setUp(): void
    {
        $this->sessions = new StubSshSession();
        // Wpisy książki adresowej wraz z rozdziałem `ssh` — od kroku 60 to
        // stamtąd moduł sesji zdalnej bierze adresy, a port i login są polami
        // rozdziału, nie polami książki.
        $this->hosts = new StubAddressBook(new AddressBook([
            new AddressEntry('aaaaaaa1', 'biuro', 'example.com', ['ssh' => ['port' => 22, 'user' => 'anna']]),
            new AddressEntry('aaaaaaa2', 'dom', '192.168.1.10', ['ssh' => ['port' => 2222, 'user' => 'jan']]),
        ]));
        $this->sshState = new StubSshState();
        $this->app = $this->fixture();
    }

    /** `Ctrl`+`S` otwiera spis, a drugie naciśnięcie go zamyka. */
    public function testTheShortcutOpensAndClosesTheHostBook(): void
    {
        $this->openHosts();

        // Tożsamość ekranu zmieniła się w kroku 49 z `ssh-hosts` na `ssh`:
        // moduł wnosi **jeden ekran w dwóch postaciach**, a `ScreenStack` liczy
        // po tożsamości, więc dwie znaczyłyby dwa wpisy na stosie dla czegoś,
        // co użytkownik widzi jako jedno miejsce.
        self::assertSame('ssh', $this->app->screens->current()->id());

        $this->press(KeyPress::ctrl('s'));

        self::assertSame('browser', $this->app->screens->current()->id());
    }

    /** Spis pokazuje nazwę, adres i stan każdego wpisu. */
    public function testTheBookShowsWhatIsInIt(): void
    {
        $this->openHosts();
        $texts = implode(' ', $this->drawCurrent());

        self::assertStringContainsString('biuro', $texts);
        self::assertStringContainsString('anna@example.com', $texts);
        self::assertStringContainsString('jan@192.168.1.10:2222', $texts);
        self::assertStringContainsString('module.ssh.column.target', $texts);
    }

    /**
     * **Sedno kroku dla hosta znanego**: `Enter` łączy, okno postępu prowadzi
     * pracę i zamyka się samo, a pasek górny mówi, z kim stoi sesja.
     */
    public function testEnterConnectsAndTheHeaderSaysWithWhom(): void
    {
        $this->openHosts();
        $this->press(KeyPress::special(Key::Enter, "\r"));

        self::assertSame('progress', $this->app->state->overlays()->current()?->id(), 'praca ma swoje okno');
        self::assertSame([['host' => 'biuro', 'password' => null]], $this->sessions->connects);

        $this->sessions->settleConnected($this->profile('biuro'));
        $this->advanceWork();

        self::assertNull($this->app->state->overlays()->current(), 'okno zamknęło się samo');
        self::assertTrue($this->sessions->state()->isConnected());

        // **Od kroku 49 ekran ma dwie postacie**: po połączeniu spis hostów
        // ustępuje zdalnemu katalogowi, więc górny pas mówi o katalogu, a nie
        // o etapie sesji. `F2` zagląda z powrotem do spisu — i tam zdanie „z kim
        // stoi sesja", będące miarą kroku 48, jest nadal na swoim miejscu.
        self::assertStringContainsString('module.ssh.remote.header', implode(' ', $this->headerTexts()));

        $this->press(KeyPress::special(Key::F3, "\e[13~"));

        self::assertStringContainsString('module.ssh.stage.connected', implode(' ', $this->headerTexts()));
    }

    /**
     * **Sedno kroku dla hosta nieznanego**: praca zatrzymuje się pytaniem,
     * a połączenie rusza dalej dopiero po zgodzie.
     */
    public function testAnUnknownHostStopsTheConnectionWithAQuestion(): void
    {
        $this->openHosts();
        $this->press(KeyPress::special(Key::Enter, "\r"));

        $this->sessions->settleAwaitingApproval($this->profile('biuro'));
        $this->advanceWork();

        $overlay = $this->app->state->overlays()->current();
        self::assertSame('confirm', $overlay?->id(), 'pytanie o odcisk stanęło na miejscu okna postępu');
        self::assertSame(0, $this->sessions->approvals, 'nikt jeszcze nie zatwierdził');

        $texts = implode(' ', $this->overlayTexts());
        self::assertStringContainsString('SHA256:', $texts, 'odcisk widać w pytaniu');

        // Ognisko startuje na odmowie (krok 28), więc „tak" wymaga strzałki.
        $this->press(KeyPress::special(Key::ArrowLeft, "\e[D"));
        $this->press(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(1, $this->sessions->approvals);
        self::assertSame('progress', $this->app->state->overlays()->current()?->id(), 'po zgodzie wraca praca');
    }

    /** Odmowa zostawia port bez pracy — inaczej etap „czekam na człowieka” trwałby w nieskończoność. */
    public function testRefusingTheFingerprintEndsTheWork(): void
    {
        $this->openHosts();
        $this->press(KeyPress::special(Key::Enter, "\r"));
        $this->sessions->settleAwaitingApproval($this->profile('biuro'));
        $this->advanceWork();

        $this->press(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(0, $this->sessions->approvals);
        self::assertSame(1, $this->sessions->disconnects);
        self::assertNull($this->app->state->overlays()->current());
    }

    /** Nieudane połączenie kończy się **zdaniem z powodem**, a nie ciszą. */
    public function testAFailedConnectionSaysWhy(): void
    {
        $this->openHosts();
        $this->press(KeyPress::special(Key::Enter, "\r"));

        $this->sessions->settleFailed($this->profile('biuro'), 'module.ssh.problem.refused');
        $this->advanceWork();

        self::assertNull($this->app->state->overlays()->current());
        self::assertStringContainsString(
            'module.ssh.problem.refused',
            $this->message(),
        );
    }

    /**
     * Hasło pyta **oknem maskowanym** i dociera do portu — a w spisie hostów nie
     * zostaje po nim ślad.
     */
    public function testThePasswordMethodAsksBeforeConnecting(): void
    {
        // Sposób uwierzytelnienia jest od kroku 60 **własnością modułu**, nie
        // wpisem książki: książka mówi, gdzie host stoi, a nie czym się do niego
        // przedstawiamy (11w).
        $this->sshState = new StubSshState(['aaaaaaa1' => new HostCredentials(AuthMethod::Password)]);
        $this->app = $this->fixture();

        $this->openHosts();
        $this->press(KeyPress::special(Key::Enter, "\r"));

        self::assertSame('prompt', $this->app->state->overlays()->current()?->id());
        self::assertSame([], $this->sessions->connects, 'bez hasła nikt się nie łączy');

        // Hasła w rysunku okna nie ma — jest maska tej samej długości.
        foreach (mb_str_split('tajne') as $character) {
            $this->press(KeyPress::character($character));
        }

        $drawn = implode(' ', $this->overlayTexts());
        self::assertStringNotContainsString('tajne', $drawn);
        self::assertStringContainsString('•••••', $drawn);

        $this->press(KeyPress::special(Key::Enter, "\r"));

        self::assertSame([['host' => 'biuro', 'password' => 'tajne']], $this->sessions->connects);
        self::assertSame('progress', $this->app->state->overlays()->current()?->id());
    }

    /**
     * `Enter` na wpisie, z którym stoi sesja, **rozłącza** zamiast łączyć drugi
     * raz.
     *
     * Od kroku 49 droga do tego wpisu wiedzie przez `F2`: po połączeniu widać
     * zdalny katalog, a spis hostów jest tym, do czego się **zagląda**.
     */
    public function testEnterOnTheConnectedHostDisconnects(): void
    {
        $this->openHosts();
        $this->press(KeyPress::special(Key::Enter, "\r"));
        $this->sessions->settleConnected($this->profile('biuro'));
        $this->advanceWork();
        $this->press(KeyPress::special(Key::F3, "\e[13~"));

        $this->press(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(1, $this->sessions->disconnects);
        self::assertNull($this->app->state->overlays()->current(), 'rozłączenie nie potrzebuje okna');
    }

    /** `F4` przestawia sposób uwierzytelnienia — bo inaczej dałoby się to zrobić tylko w pliku. */
    public function testTheAuthenticationMethodCanBeChangedFromTheScreen(): void
    {
        $this->openHosts();
        $this->press(KeyPress::special(Key::F4, "\e[14~"));

        self::assertSame('prompt', $this->app->state->overlays()->current()?->id(), 'klucz pyta o ścieżkę');

        foreach (mb_str_split('/home/anna/.ssh/id_ed25519') as $character) {
            $this->press(KeyPress::character($character));
        }

        $this->press(KeyPress::special(Key::Enter, "\r"));

        // Poświadczenie zapisuje **moduł u siebie**, a książka adresowa nie
        // dowiaduje się o nim niczego (krok 60, 11w).
        $changed = $this->sshState->credentials('aaaaaaa1');
        self::assertSame(AuthMethod::Key, $changed->auth);
        self::assertSame('/home/anna/.ssh/id_ed25519', $changed->keyPath);
        self::assertSame(0, $this->hosts->saves, 'książka nie zapisuje poświadczeń');
    }

    /**
     * `F5` pyta o stan **tylko wtedy, gdy jest o co pytać**.
     *
     * Bez sesji zostaje zdanie, a nie proces potomny — bo port tłowy prowadzi
     * jedną pracę naraz i pytanie o nic zabiłoby cudzą (D87 nr 9).
     */
    public function testRefreshingWithoutASessionStartsNothing(): void
    {
        $this->openHosts();
        $this->press(KeyPress::special(Key::F5, "\e[15~"));

        self::assertSame(0, $this->sessions->refreshes);
        self::assertStringContainsString(
            'module.ssh.message.nothing',
            $this->message(),
        );
    }

    /**
     * **Sedno kroku 50**: `F5` na wpisie zdalnym pyta o katalog, a `Enter`
     * zaczyna pracę i otwiera okno postępu.
     *
     * Kontekst lokalny podaje się tą samą metodą, którą przed rysowaniem woła
     * `FrameComposer` (`ReadsContext`) — bo to on jest jedyną drogą, którą ekran
     * zdalny poznaje katalog przeglądarki (D89 nr 8).
     */
    public function testDownloadingAsksForTheTargetAndRunsWithAWindow(): void
    {
        $this->connect();
        $this->publishLocal(new ModuleContext('/home/anna/pobrane'));

        // Kursor startuje na katalogu, a katalogów ten krok nie przesyła
        // (D89 nr 5) — strzałka staje na pliku.
        $this->press(KeyPress::special(Key::ArrowDown, "\e[B"));
        $this->press(KeyPress::special(Key::F5, "\e[15~"));

        self::assertSame('prompt', $this->app->state->overlays()->current()?->id());
        self::assertStringContainsString('/home/anna/pobrane', implode(' ', $this->overlayTexts()));

        $this->press(KeyPress::special(Key::Enter, "\r"));

        self::assertSame('progress', $this->app->state->overlays()->current()?->id(), 'praca ma swoje okno');
        self::assertCount(1, $this->app->remoteTransfers->started);

        [$items, $target, $direction] = $this->app->remoteTransfers->started[0];

        self::assertSame('/home/anna/list.txt', $items[0]->path);
        self::assertSame('/home/anna/pobrane', $target);
        self::assertSame(TransferDirection::Download, $direction);

        $this->app->remoteTransfers->willStep(RemoteTransferState::idle()->withFinished(0)->done());
        $this->advanceWork();

        self::assertNull($this->app->state->overlays()->current(), 'okno zamknęło się samo');
        self::assertStringContainsString('module.ssh.transfer.download.done', $this->message());
    }

    /** `F6` wysyła plik zaznaczony **w przeglądarce** — źródło bierze się z kontekstu. */
    public function testUploadingTakesTheSourceFromTheBrowserContext(): void
    {
        $this->connect();
        $this->publishLocal(new ModuleContext(
            '/home/anna',
            'raport.pdf',
            ContextEntryKind::File,
            selectionBytes: 900,
        ));

        $this->press(KeyPress::special(Key::F6, "\e[17~"));
        $this->press(KeyPress::special(Key::Enter, "\r"));

        [$items, $target, $direction] = $this->app->remoteTransfers->started[0];

        self::assertSame('/home/anna/raport.pdf', $items[0]->path);
        self::assertSame(900, $items[0]->sizeInBytes);
        self::assertSame('/home/anna', $target, 'celem jest katalog otwarty w panelu');
        self::assertSame(TransferDirection::Upload, $direction);
    }

    /**
     * Odświeżanie listy przeprowadziło się z `F5` na `Ctrl`+`R` (D89 nr 4).
     *
     * Sprawdzamy **obie** połowy tej zmiany: nowy klawisz zamawia nowy obieg,
     * a stary nie zamawia go już wcale — bo klawisz, który po przeprowadzce
     * robiłby jedno i drugie, byłby przeoczeniem, nie zgodnością wstecz.
     */
    public function testTheListingRefreshMovedFromFiveToControlR(): void
    {
        $this->connect();
        $before = $this->app->remote->reads;

        $this->press(KeyPress::ctrl('r'));

        self::assertSame($before + 1, $this->app->remote->reads);

        $this->press(KeyPress::special(Key::F5, "\e[15~"));

        self::assertSame($before + 1, $this->app->remote->reads, 'F5 nie czyta katalogu, tylko pobiera plik');
    }

    /** Takt posuwa pracę także wtedy, gdy spisu hostów nie widać. */
    public function testTheTickAdvancesTheWorkFromAnyScreen(): void
    {
        $this->openHosts();
        $this->press(KeyPress::special(Key::Enter, "\r"));
        $this->press(KeyPress::special(Key::Escape, "\e"));

        $this->sessions->settleConnected($this->profile('biuro'));
        $this->app->ticker->tick($this->app->state, self::NOW);

        self::assertSame(SessionStage::Connected, $this->sessions->state()->stage);
    }

    /**
     * Zdanie z paska stanu — puste, gdy żadnego nie ma.
     *
     * Rozpisane na dwie linie zamiast `?->text ?? ''`, bo `message()` **jest**
     * nullowalne, a skrót z `->` wywróciłby przebieg dokładnie wtedy, gdy
     * aplikacja milczy — czyli w przypadku, którego test ma pilnować.
     */
    private function message(): string
    {
        $message = $this->app->state->message();

        return $message === null ? '' : $message->text;
    }

    /**
     * Kontekst przeglądarki podany ekranowi zdalnemu **tą samą drogą, którą
     * podaje go aplikacja**: `FrameComposer` woła `ReadsContext::useContext()`
     * przed rysowaniem. Sprawdzenie zdolności jest tu częścią przebiegu — bez
     * niej ekran nie miałby jak poznać katalogu, w którym stoi użytkownik.
     */
    private function publishLocal(ModuleContext $context): void
    {
        $screen = $this->app->sshScreen;

        self::assertInstanceOf(ReadsContext::class, $screen);

        $screen->useContext($context);
    }

    /** Sesja stojąca i zdalny katalog na ekranie — punkt wyjścia przebiegów przesyłu. */
    private function connect(): void
    {
        $this->openHosts();
        $this->press(KeyPress::special(Key::Enter, "\r"));
        $this->sessions->settleConnected($this->profile('biuro'));
        $this->advanceWork();
    }

    private function openHosts(): void
    {
        $this->press(KeyPress::ctrl('s'));
    }

    private function press(KeyPress $key): void
    {
        $this->app->input->handle($key, $this->app->state, self::NOW);
    }

    /**
     * Jedna klatka fazy „aktualizuj stan”, tak jak układa ją `GameLoop`.
     *
     * **Dwa kroki, nie jeden, i to jest istotne**: najpierw takt modułu posuwa
     * pracę w porcie (`NeedsTick`), potem okno ogląda jej stan i rozstrzyga, czy
     * się zamknąć (`RunsWork`). Test wołający wyłącznie drugie sprawdzałby okno
     * patrzące na pracę, której nikt nie posunął — czyli nie tę aplikację, którą
     * uruchamia użytkownik.
     */
    private function advanceWork(): void
    {
        $this->app->ticker->tick($this->app->state, self::NOW);
        $this->app->input->advanceWork($this->app->state, self::NOW);
    }

    /**
     * Cel o tej nazwie — **złożony tak, jak składa go moduł** (krok 60): wiersz
     * książki adresowej plus poświadczenie z sekcji modułu. Dwa źródła, jedno
     * miejsce ich spotkania — także w teście.
     */
    private function profile(string $name): HostProfile
    {
        $entry = $this->hosts->load()->book->findByName($name);
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

    /** @return list<string> */
    private function drawCurrent(): array
    {
        return self::textsOf($this->app->screens->current()->draw(new Rect(0, 0, self::ROWS, self::COLUMNS)));
    }

    /** @return list<string> */
    private function overlayTexts(): array
    {
        $overlay = $this->app->state->overlays()->current();

        if ($overlay === null) {
            return [];
        }

        return self::textsOf($overlay->draw($overlay->bounds(self::ROWS, self::COLUMNS)));
    }

    /** @return list<string> */
    private function headerTexts(): array
    {
        $zone = $this->app->screens->current()->header();

        return $zone === null ? [] : self::textsOf($zone->content->draw(new Rect(0, 0, 1, self::COLUMNS)));
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
                $texts[] = $primitive->text;
            }
        }

        return $texts;
    }

    private function fixture(): ScreenFixture
    {
        $directories = (new InMemoryDirectoryRepository())->add('/', [Entry::file('plik.txt', 10)]);

        // Zdalny katalog ma **treść**, bo od kroku 50 przebieg sięga po wpis pod
        // kursorem: pobranie bez pliku do pobrania sprawdzałoby wyłącznie odmowę.
        return new ScreenFixture(
            $directories->get(new DirectoryPath('/'), false),
            $directories,
            sessions: $this->sessions,
            sshState: $this->sshState,
            addressBook: $this->hosts,
            remote: new StubRemoteDirectory([
                '/home/anna' => [
                    new RemoteEntry('dokumenty', RemoteEntryType::Directory),
                    new RemoteEntry('list.txt', RemoteEntryType::File, 120),
                ],
            ]),
        );
    }
}
