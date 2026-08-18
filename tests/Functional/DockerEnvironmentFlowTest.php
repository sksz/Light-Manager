<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Docker\Application\ContextEntry;
use LightManager\Module\Docker\Application\EnvironmentBook;
use LightManager\Module\Docker\Domain\ValueObject\DockerEnvironment;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubContextCatalog;
use LightManager\Tests\Support\StubDockerApi;
use LightManager\Tests\Support\StubEnvironmentBook;
use LightManager\Tests\Support\StubTunnel;
use PHPUnit\Framework\TestCase;

/**
 * Środowiska Dockera całą drogą użytkownika (krok 58).
 *
 * Przebieg sprawdza kryteria ukończenia kroku, które widać z zewnątrz:
 * przełączenie środowiska unieważnia listy i sięga do compose przedrostkiem,
 * tunel, który nie wstał, ma własne zdanie, a wpis czytany od klienta nie daje
 * się ani zmienić, ani skasować.
 *
 * **Żaden test nie uruchamia ani `ssh`, ani `docker`** — trzy porty środowisk
 * przychodzą atrapami, jak gniazdo demona i wtyczka compose od kroku 51.
 */
final class DockerEnvironmentFlowTest extends TestCase
{
    private const NOW = 100.0;

    private const COLUMNS = 100;

    private const ROWS = 24;

    private const CONTAINERS = '[{"Id":"aaaaaaaaaaaa","Names":["/sklep-api"],"Image":"sklep:1.2",'
        . '"State":"running","Status":"Up 2 hours","Created":1,"Ports":[],"Labels":{}}]';

    private ScreenFixture $app;

    private StubDockerApi $docker;

    private StubEnvironmentBook $book;

    private StubTunnel $tunnel;

    private StubContextCatalog $contexts;

    protected function setUp(): void
    {
        $this->docker = new StubDockerApi();
        $this->book = new StubEnvironmentBook(new EnvironmentBook([
            DockerEnvironment::localSocket('praca', '/run/praca.sock'),
            DockerEnvironment::sshTunnel('serwer', 'anna@example.com', 2222),
        ]));
        $this->tunnel = new StubTunnel(problemKey: 'module.docker.tunnel.rejected');
        $this->contexts = new StubContextCatalog([
            new ContextEntry('default', 'unix:///var/run/docker.sock', true),
        ]);
        $this->app = $this->fixture();
    }

    /** Litera `e` otwiera spis: wpisy własne i kontekst klienta w jednej tabeli. */
    public function testTheLetterOpensTheListWithBothSources(): void
    {
        $this->openEnvironments();

        $texts = $this->drawCurrent();

        self::assertContains('praca', $texts, 'wpis własny');
        self::assertContains('serwer', $texts, 'wpis tunelowy');
        self::assertContains('default', $texts, 'kontekst czytany od klienta');
        self::assertContains('unix:///var/run/docker.sock', $texts, 'adres kontekstu');
    }

    /**
     * **Sedno kroku**: wybór środowiska unieważnia listę, sięga do gniazda
     * nowym punktem końcowym i do compose przedrostkiem — a wybór przeżywa
     * uruchomienie, bo trafia do książki.
     */
    public function testChoosingAnEnvironmentInvalidatesTheListsAndReachesEveryDoor(): void
    {
        $this->openContainers();
        self::assertContains('sklep-api', $this->drawCurrent(), 'lista stoi przed przełączeniem');

        $this->openEnvironments();
        $this->press(KeyPress::special(Key::Enter, "\r"));
        $this->tick();

        self::assertSame(
            'module.docker.env.switched(name=praca)',
            $this->app->state->message()?->text,
        );
        self::assertSame('/run/praca.sock', $this->docker->endpoint?->socketPath, 'gniazdo z wybranego wpisu');
        self::assertSame("DOCKER_HOST='unix:///run/praca.sock' ", $this->app->compose->prefix, 'compose dostaje przedrostek');
        self::assertSame('praca', $this->book->saved?->current(), 'wybór zapisany w książce');

        $this->press(KeyPress::special(Key::Escape, "\e"));
        $texts = $this->drawCurrent();

        self::assertNotContains('sklep-api', $texts, 'żaden wiersz poprzedniego demona nie przeżywa zmiany');
    }

    /** Tunel, który nie wstał, ma własne zdanie — w wierszu spisu i w punkcie końcowym. */
    public function testATunnelThatDidNotRiseSaysSoInsteadOfShowingAnEmptyList(): void
    {
        $this->openEnvironments();
        $this->press(KeyPress::special(Key::ArrowDown, "\e[B"));
        $this->press(KeyPress::special(Key::Enter, "\r"));

        // Wybór wpisu tunelowego pyta wpierw o sposób uwierzytelnienia (D102
        // nr 4); odpowiedź pierwsza — klucz albo agent — jest domyślna.
        self::assertSame('choice', $this->app->state->overlays()->current()?->id());
        $this->press(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(
            'module.docker.env.switching(name=serwer)',
            $this->app->state->message()?->text,
            'wybór tunelu mówi, że tunel wstaje',
        );
        self::assertSame(['serwer anna@example.com 2222 /var/run/docker.sock'], $this->tunnel->opened);
        self::assertSame([false], $this->tunnel->passwords, 'puste pole nie udaje hasła');

        $this->tick();
        $this->tick();

        self::assertSame('module.docker.tunnel.rejected', $this->docker->endpoint?->problemKey);

        // Stan tunelu w odpowiedzi kwerendy — kolumna spisu pokazuje to samo,
        // ale jej szerokość ucina klucz atrapy tłumacza (ta sama okoliczność,
        // co przy stanach kontenera w `DockerFlowTest`).
        $rows = $this->app->state->queries()->ask('docker.environments')->rows();
        $serwer = array_values(array_filter($rows, static fn (array $row): bool => ($row['name'] ?? '') === 'serwer'));

        self::assertCount(1, $serwer);
        self::assertSame('failed', $serwer[0]['tunnel'] ?? null, 'tunel, który nie wstał, ma własne zdanie');
    }

    /**
     * Droga hasłowa tunelu (D102 nr 4): odpowiedź „hasłem" otwiera pole
     * maskowane, a hasło dociera do portu — bez zapisywania go gdziekolwiek.
     */
    public function testTheTunnelCanAuthenticateWithAPassword(): void
    {
        $this->openEnvironments();
        $this->press(KeyPress::special(Key::ArrowDown, "\e[B"));
        $this->press(KeyPress::special(Key::Enter, "\r"));

        self::assertSame('choice', $this->app->state->overlays()->current()?->id());
        $this->press(KeyPress::special(Key::ArrowDown, "\e[B"));
        $this->press(KeyPress::special(Key::Enter, "\r"));

        self::assertSame('prompt', $this->app->state->overlays()->current()?->id());

        foreach (str_split('tajne') as $letter) {
            $this->press(KeyPress::character($letter));
        }

        $this->press(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(['serwer anna@example.com 2222 /var/run/docker.sock'], $this->tunnel->opened);
        self::assertSame([true], $this->tunnel->passwords, 'port dostał hasło');
        self::assertSame(
            'module.docker.env.switching(name=serwer)',
            $this->app->state->message()?->text,
        );
    }

    /** Wpis czytany od klienta nie daje się skasować (D96 nr 3) — odmowa jest zdaniem. */
    public function testAClientContextRefusesRemovalWithASentence(): void
    {
        $this->openEnvironments();
        $this->press(KeyPress::special(Key::End, "\e[F"));
        $this->press(KeyPress::special(Key::F8, "\e[19~"));

        self::assertNull($this->app->state->overlays()->current(), 'okna pytania nie ma — nie ma czego pytać');
        self::assertSame(
            'module.docker.env.clientEntry(name=default)',
            $this->app->state->message()?->text,
        );
        self::assertSame(0, $this->book->saveCount, 'książka nietknięta');
    }

    /** `F8` na wpisie własnym pyta oknem, a zgoda zdejmuje wpis ze spisu. */
    public function testAnOwnEntryIsRemovedAfterTheQuestion(): void
    {
        $this->openEnvironments();
        $this->press(KeyPress::special(Key::F8, "\e[19~"));

        self::assertSame('confirm', $this->app->state->overlays()->current()?->id());

        // Ognisko pytania startuje na odmowie (reguła 10) — zgoda wymaga kroku
        // w bok, dokładnie tak, jak przy usuwaniu pliku.
        $this->press(KeyPress::special(Key::ArrowLeft, "\e[D"));
        $this->press(KeyPress::special(Key::Enter, "\r"));
        $this->tick();

        self::assertSame(
            'module.docker.env.removed(name=praca)',
            $this->app->state->message()?->text,
        );
        self::assertNotContains('praca', $this->drawCurrent());
    }

    private function openContainers(): void
    {
        $this->docker->willReturn(self::CONTAINERS);
        $this->press(KeyPress::ctrl('o'));
        $this->tick();
        $this->tick();
    }

    private function openEnvironments(): void
    {
        if ($this->app->screens->current()->id() !== 'docker') {
            $this->openContainers();
        }

        $this->press(KeyPress::character('e'));
        $this->tick();
        $this->tick();
    }

    private function press(KeyPress $key): void
    {
        $this->app->input->handle($key, $this->app->state, self::NOW);
    }

    private function tick(): void
    {
        $this->app->ticker->tick($this->app->state, self::NOW);
        $this->app->input->advanceWork($this->app->state, self::NOW);
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
            docker: $this->docker,
            environmentBook: $this->book,
            contexts: $this->contexts,
            tunnel: $this->tunnel,
        );
    }
}
