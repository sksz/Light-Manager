<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Docker\Application\ContextEntry;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubAddressBook;
use LightManager\Tests\Support\StubContextCatalog;
use LightManager\Tests\Support\StubDockerApi;
use LightManager\Tests\Support\StubDockerState;
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

    /** Wpisy wspólnej książki — od kroku 60 to stąd biorą się środowiska. */
    private StubAddressBook $book;

    private StubDockerState $dockerState;

    private StubTunnel $tunnel;

    private const HOST = 'aaaaaaaaaaaa';

    private const PRACA = 'bbbbbbbbbbbb';

    private const SERWER = 'cccccccccccc';

    private StubContextCatalog $contexts;

    protected function setUp(): void
    {
        $this->docker = new StubDockerApi();
        // Środowiska są **wpisami wspólnej książki** z wartościami rozdziału
        // `docker` (krok 60); cel tunelu wskazuje wpis rozdziału `ssh`.
        $this->book = new StubAddressBook([
            new AddressEntry(self::HOST, 'biuro', [
                'ssh' => ['host' => 'example.com', 'port' => 2222, 'user' => 'anna'],
            ]),
            new AddressEntry(self::PRACA, 'praca', [
                'docker' => ['kind' => 'local', 'socket' => '/run/praca.sock'],
            ]),
            new AddressEntry(self::SERWER, 'serwer', [
                'docker' => ['kind' => 'tunnel', 'target' => self::HOST, 'port' => 2222],
            ]),
        ]);
        $this->dockerState = new StubDockerState();
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
        // Wybór zapisuje się **w sekcji modułu, identyfikatorem wpisu** (krok
        // 60): książka trzyma wpisy, a to, który z nich jest bieżący, jest
        // pamięcią tego modułu.
        self::assertSame(self::PRACA, $this->dockerState->current, 'wybór przeżywa uruchomienie');

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

    /**
     * **Usuwanie i dopisywanie zeszły do książki** (krok 60), więc na tym
     * ekranie nie ma po nich klawisza — a wpis, który zniknął z książki, wypada
     * ze spisu przy najbliższym takcie.
     */
    public function testAnEntryRemovedInTheBookLeavesTheList(): void
    {
        $this->openEnvironments();

        self::assertContains('praca', $this->drawCurrent());

        $command = $this->app->commandRegistry->find('address-book.remove');
        self::assertInstanceOf(\LightManager\Presentation\Ui\Command\OpensOverlay::class, $command);

        $outcome = $command->overlayFor(new CommandInput(['entry' => self::PRACA]));
        self::assertNotNull($outcome?->next, 'usunięcie wpisu pyta oknem');

        $this->app->state->overlays()->open($outcome->next);
        $this->press(KeyPress::special(Key::ArrowLeft, "\e[D"));
        $this->press(KeyPress::special(Key::Enter, "\r"));
        $this->tick();

        self::assertNotContains('praca', $this->drawCurrent());
    }

    /**
     * **Stary spis środowisk przenosi się sam, a cel tunelu staje się
     * odniesieniem** (krok 60, etap 2).
     *
     * Wpis tunelowy wskazywał host **nazwą**; po migracji wskazuje wpis
     * książki identyfikatorem — i to jest cała różnica, dla której rodzaj
     * `entry` w ogóle powstał.
     */
    public function testTheOldEnvironmentBookMigratesAndTheTargetBecomesAReference(): void
    {
        $book = new StubAddressBook([
            new AddressEntry(self::HOST, 'biuro', [
                'ssh' => ['host' => 'example.com', 'port' => 2222, 'user' => 'anna'],
            ]),
        ]);
        $legacy = new StubDockerState([
            ['name' => 'serwer', 'kind' => 'tunnel', 'target' => 'biuro', 'port' => 2222, 'socket' => '/run/d.sock'],
        ]);
        $legacy->current = 'serwer';

        $app = $this->fixtureWith($book, $legacy);
        $app->ticker->tick($app->state, self::NOW);

        $rows = $app->state->queries()->ask(
            'address-book.entries',
            new CommandInput(['chapter' => 'docker']),
        )->rows();
        $migrated = null;

        foreach ($rows as $row) {
            if (($row['name'] ?? null) === 'serwer') {
                $migrated = $row;
            }
        }

        self::assertNotNull($migrated);
        self::assertSame('tunnel', $migrated['kind'] ?? null);
        self::assertSame(self::HOST, $migrated['target'] ?? null, 'nazwa hosta zamieniona na odniesienie');
        self::assertSame(2222, $migrated['port'] ?? null);
        self::assertTrue($legacy->isMigrated());
        self::assertSame($migrated['id'] ?? null, $legacy->current, 'wskaźnik bieżącego przeliczony');
    }

    /**
     * **Zmiana nazwy hosta nie psuje tunelu** — zdanie-miara etapu drugiego.
     *
     * Przed krokiem 60 wpis tunelowy trzymał nazwę i po jej zmianie wskazywał
     * donikąd; odniesienie tego nie zauważa.
     */
    public function testRenamingTheHostLeavesTheTunnelPointingAtIt(): void
    {
        $this->openEnvironments();

        $command = $this->app->commandRegistry->find('address-book.rename');
        self::assertNotNull($command);
        $command->execute(new CommandInput(['entry' => self::HOST, 'name' => 'biuro-nowe']));
        $this->tick();

        $rows = $this->app->state->queries()->ask(
            'address-book.entry',
            new CommandInput(['entry' => self::SERWER, 'chapter' => 'docker']),
        )->rows();

        self::assertSame(self::HOST, $rows[0]['target'] ?? null, 'odniesienie nie zauważa zmiany nazwy');
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
        return $this->fixtureWith($this->book, $this->dockerState);
    }

    private function fixtureWith(StubAddressBook $book, StubDockerState $state): ScreenFixture
    {
        $directories = (new InMemoryDirectoryRepository())->add('/', [Entry::file('plik.txt', 10)]);

        return new ScreenFixture(
            $directories->get(new DirectoryPath('/'), false),
            $directories,
            docker: $this->docker,
            dockerState: $state,
            contexts: $this->contexts,
            tunnel: $this->tunnel,
            addressBook: $book,
        );
    }
}
