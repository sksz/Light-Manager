<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Ssh\Application\HostBook;
use LightManager\Module\Ssh\Application\SessionStage;
use LightManager\Module\Ssh\Domain\ValueObject\AuthMethod;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubHostBook;
use LightManager\Tests\Support\StubSshSession;
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

    private StubHostBook $hosts;

    protected function setUp(): void
    {
        $this->sessions = new StubSshSession();
        $this->hosts = new StubHostBook(new HostBook([
            new HostProfile('biuro', 'example.com', 22, 'anna'),
            new HostProfile('dom', '192.168.1.10', 2222, 'jan'),
        ]));
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
        $this->hosts = new StubHostBook(new HostBook([
            new HostProfile('biuro', 'example.com', 22, 'anna', AuthMethod::Password),
        ]));
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

    /** `F7` dopisuje wpis, a ten **przeżywa zapis książki**. */
    public function testAHostAddedInTheWindowIsPersisted(): void
    {
        $this->openHosts();
        $this->press(KeyPress::special(Key::F7, "\e[18~"));

        foreach (mb_str_split('ola@nowy.example.com:2200') as $character) {
            $this->press(KeyPress::character($character));
        }

        $this->press(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(1, $this->hosts->saves);

        $book = $this->hosts->load()->book;
        self::assertSame(3, $book->count());

        $added = $book->find('ola@nowy.example.com:2200');
        self::assertNotNull($added);
        self::assertSame('nowy.example.com', $added->host);
        self::assertSame(2200, $added->port);
        self::assertSame('ola', $added->user);
    }

    /** Wpis nie do przyjęcia kończy się **zdaniem**, a nie śladem stosu. */
    public function testAnImpossibleHostIsRefusedWithASentence(): void
    {
        $this->openHosts();
        $this->press(KeyPress::special(Key::F7, "\e[18~"));

        foreach (mb_str_split('-oProxyCommand=x') as $character) {
            $this->press(KeyPress::character($character));
        }

        $this->press(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(0, $this->hosts->saves);
        self::assertStringContainsString(
            'module.ssh.profile.host.invalid',
            $this->message(),
        );
    }

    /** `F8` pyta, zanim usunie — i usuwa dopiero po „tak”. */
    public function testRemovingAHostAsksFirst(): void
    {
        $this->openHosts();
        $this->press(KeyPress::special(Key::F8, "\e[19~"));

        self::assertSame('confirm', $this->app->state->overlays()->current()?->id());
        self::assertSame(0, $this->hosts->saves);

        $this->press(KeyPress::special(Key::ArrowLeft, "\e[D"));
        $this->press(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(1, $this->hosts->saves);
        self::assertSame(['dom'], $this->hosts->load()->book->names());
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

        $changed = $this->hosts->load()->book->find('biuro');
        self::assertNotNull($changed);
        self::assertSame(AuthMethod::Key, $changed->auth);
        self::assertSame('/home/anna/.ssh/id_ed25519', $changed->keyPath);
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

    private function profile(string $name): HostProfile
    {
        $profile = $this->hosts->load()->book->find($name);
        self::assertNotNull($profile);

        return $profile;
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

        return new ScreenFixture(
            $directories->get(new DirectoryPath('/'), false),
            $directories,
            sessions: $this->sessions,
            hosts: $this->hosts,
        );
    }
}
