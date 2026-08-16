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
use LightManager\Module\Docker\Application\DockerResult;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubCompose;
use LightManager\Tests\Support\StubDockerApi;
use PHPUnit\Framework\TestCase;

/**
 * Moduł Dockera całą drogą użytkownika (krok 51).
 *
 * Przebieg sprawdza **zdanie-miarę kroku**: `Ctrl`+`O` pokazuje kontenery
 * i obrazy, `Enter` otwiera logi, a czynność idzie do demona i wraca zdaniem.
 *
 * **Demona nie ma tu ani przez chwilę** i jest to warunek, nie wygoda: test
 * rozmawiający z prawdziwym demonem zatrzymywałby cudze kontenery na maszynie,
 * na której akurat biegnie. Odpowiedzi udaje `StubDockerApi`, a wtyczkę compose
 * — `StubCompose`; obie atrapy udają jedyne, o co w tym kroku chodzi: że
 * odpowiedź właśnie doszła i co w niej stoi.
 *
 * Listy przychodzą **taktem**, a nie klawiszem, więc każdy krok przebiegu
 * przechodzi przez `ticker` — tę samą drogę, którą w aplikacji prowadzi
 * `GameLoop`.
 */
final class DockerFlowTest extends TestCase
{
    private const NOW = 100.0;

    private const COLUMNS = 100;

    private const ROWS = 24;

    private const CONTAINERS = '[{"Id":"aaaaaaaaaaaa","Names":["/sklep-api"],"Image":"sklep:1.2",'
        . '"State":"running","Status":"Up 2 hours","Created":1,"Ports":[{"PrivatePort":80,"PublicPort":8080,"Type":"tcp"}],'
        . '"Labels":{"com.docker.compose.project":"sklep"}},'
        . '{"Id":"bbbbbbbbbbbb","Names":["/sklep-baza"],"Image":"postgres:16","State":"exited",'
        . '"Status":"Exited (0) 3 days ago","Created":2,"Ports":[],"Labels":{}}]';

    private const IMAGES = '[{"Id":"sha256:cccccccccccc","RepoTags":["sklep:1.2"],"Size":104857600,'
        . '"Created":1,"Containers":1}]';

    private ScreenFixture $app;

    private StubDockerApi $docker;

    protected function setUp(): void
    {
        $this->docker = new StubDockerApi();
        $this->app = $this->fixture();
    }

    /** **Sedno kroku**: skrót otwiera ekran, a takt przynosi listę z demona. */
    public function testTheShortcutShowsContainersFromTheDaemon(): void
    {
        $this->openContainers();

        $texts = $this->drawCurrent();

        self::assertContains('sklep-api', $texts);
        self::assertContains('sklep-baza', $texts);

        // Panel opisu pokazuje wybrany kontener w całości. Sprawdzamy go, a nie
        // kolumny listy, z powodu **testowego, nie interfejsowego**: tłumacz jest
        // tu atrapą oddającą klucze, a `module.docker.state.running` nie mieści
        // się w kolumnie szerokiej na jedenaście znaków. Ta sama okoliczność, co
        // w `InputHandlerTest` przy zakładkach ustawień.
        self::assertContains('Up 2 hours', $texts, 'zdanie demona o stanie');
        self::assertContains('8080->80/tcp', $texts, 'porty w opisie');
        self::assertContains('sklep', $texts, 'projekt compose z etykiety kontenera');
    }

    /** `F3` przechodzi do obrazów — druga postać tego samego ekranu. */
    public function testF3SwitchesToImages(): void
    {
        $this->openContainers();
        $this->docker->willReturn(self::IMAGES);

        $this->press(KeyPress::special(Key::F3, "\eOR"));
        $this->tick();

        $texts = $this->drawCurrent();

        self::assertContains('sklep:1.2', $texts, 'nazwa obrazu');
        self::assertContains('cccccccccccc', $texts, 'skrót treści w opisie');
        self::assertNotContains('sklep-api', $texts, 'kontenerów już nie widać');
    }

    /**
     * `Enter` otwiera logi, a te **przychodzą bez śmieci z ramek
     * multipleksowania** — czyli pułapka nazwana w planie kroku nie zadziałała.
     */
    public function testEnterOpensLogsWithoutMultiplexingGarbage(): void
    {
        $this->openContainers();
        $this->docker->willAnswer(DockerResult::running(
            self::frame("pierwszy wiersz\n") . self::frame("drugi wiersz\n"),
        ));

        $this->press(KeyPress::special(Key::Enter, "\r"));
        $this->tick();

        $texts = $this->drawCurrent();

        self::assertContains('pierwszy wiersz', $texts);
        self::assertContains('drugi wiersz', $texts);
    }

    /** `Esc` wraca z logów do listy — i zamyka strumień, żeby nie płynął w tle. */
    public function testEscapeLeavesTheLogsAndClosesTheStream(): void
    {
        $this->openContainers();
        $this->press(KeyPress::special(Key::Enter, "\r"));
        $this->tick();
        $stopped = $this->docker->stopped;

        $this->press(KeyPress::special(Key::Escape, "\e"));
        $this->tick();

        self::assertContains('sklep-api', $this->drawCurrent(), 'znowu widać listę kontenerów');
        self::assertGreaterThan($stopped, $this->docker->stopped, 'strumień logu został zamknięty');
    }

    /**
     * `F4` na działającym kontenerze **zatrzymuje go**, a zdanie o skutku trafia
     * do paska stanu.
     */
    public function testF4StopsTheRunningContainerAndSaysSo(): void
    {
        $this->openContainers();
        $this->docker->willAnswer(DockerResult::done('', 204))->willReturn(self::CONTAINERS);

        $this->press(KeyPress::special(Key::F4, "\eOS"));
        $this->tick();

        self::assertContains('POST /containers/aaaaaaaaaaaa/stop', $this->docker->changes);
        self::assertSame(
            'module.docker.action.done.stop(name=sklep-api)',
            $this->app->state->message()?->text,
        );
    }

    /**
     * Usunięcie **pyta oknem w wariancie groźnym** i bez zgody nie wysyła
     * niczego.
     */
    public function testRemovalAsksFirstAndSendsNothingWhenRefused(): void
    {
        $this->openContainers();
        $changes = $this->docker->changes;

        $this->press(KeyPress::special(Key::F8, "\e[19~"));

        self::assertSame('confirm', $this->app->state->overlays()->current()?->id());

        $this->press(KeyPress::special(Key::Escape, "\e"));
        $this->tick();

        self::assertSame($changes, $this->docker->changes, 'odmowa nie wysyła żądania');
    }

    /** `F5` zawęża listę do projektu compose — bez ani jednego pytania do demona. */
    public function testF5NarrowsToTheComposeProject(): void
    {
        $this->openContainers();
        $asked = count($this->docker->paths);

        $this->press(KeyPress::special(Key::F5, "\e[15~"));

        $texts = $this->drawCurrent();

        self::assertContains('sklep-api', $texts);
        self::assertNotContains('sklep-baza', $texts, 'kontener spoza projektu wypada z listy');
        self::assertCount($asked, $this->docker->paths, 'zawężenie nie kosztuje pytania');
    }

    private function openContainers(): void
    {
        $this->docker->willReturn(self::CONTAINERS);
        $this->press(KeyPress::ctrl('o'));
        $this->tick();
        $this->tick();
    }

    private function press(KeyPress $key): void
    {
        $this->app->input->handle($key, $this->app->state, self::NOW);
    }

    /** Takt modułów i posunięcie pracy okna — to samo, co robi `GameLoop`. */
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

    /** Ramka multipleksera — ta sama, którą przysyła demon dla kontenera bez TTY. */
    private static function frame(string $payload): string
    {
        return pack('CCCCN', 1, 0, 0, 0, strlen($payload)) . $payload;
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
            compose: new StubCompose(),
        );
    }
}
