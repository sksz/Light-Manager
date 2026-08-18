<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\BackgroundState;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubKubectl;
use PHPUnit\Framework\TestCase;

/**
 * Moduł klastra całą drogą użytkownika (krok 52).
 *
 * Przebieg sprawdza **zdanie-miarę kroku**: `Ctrl`+`K` pokazuje zasoby wybranego
 * kontekstu, drzewo rozwija się do podów, a niedostępny klaster kończy się
 * zdaniem, nie zawieszeniem.
 *
 * **Klastra nie ma tu ani przez chwilę i `kubectl` nie powstaje jako proces** —
 * to jest kryterium ukończenia kroku, nie wygoda testu. Odpowiedzi udaje
 * `StubKubectl`, a każda z nich jest wypisem zapisanym w tym pliku.
 *
 * Odpowiedzi przychodzą **taktem**, a nie klawiszem, więc każdy krok przebiegu
 * przechodzi przez `ticker` — tę samą drogę, którą w aplikacji prowadzi
 * `GameLoop`.
 *
 * **`HOME` idzie na katalog tymczasowy z pustym `~/.kube/config`** (krok 59):
 * miejsce ma odtąd dwie współrzędne, a brak pliku rozstrzyga się `is_file()`,
 * nie odpowiedzią klienta — bez podstawienia przebieg zależałby od tego, czy
 * maszyna testująca ma własny `kubeconfig`.
 */
final class ClusterFlowTest extends TestCase
{
    private const NOW = 100.0;

    private const COLUMNS = 100;

    private const ROWS = 24;

    private const CONFIG = '{"contexts":[{"name":"minikube","context":{"cluster":"minikube",'
        . '"namespace":"default"}}],"current-context":"minikube"}';

    private const VERSIONS = '{"clientVersion":{"gitVersion":"v1.25.2"},'
        . '"serverVersion":{"gitVersion":"v1.25.3"}}';

    private const RESOURCES = "pods  po v1 true Pod [create delete get list patch update watch] all\n"
        . 'secrets     v1 true Secret [create delete get list patch update watch]';

    private const PODS = '{"apiVersion":"v1","kind":"List","items":[{"metadata":{"name":"web-1",'
        . '"namespace":"default","creationTimestamp":"2026-08-16T07:00:00Z"},"spec":{"nodeName":"node-1"},'
        . '"status":{"phase":"Running","containerStatuses":[{"ready":true,"restartCount":0,'
        . '"state":{"running":{}}}]}}]}';

    private StubKubectl $kubectl;

    private ScreenFixture $app;

    private string $home = '';

    private string|false $previousHome = false;

    protected function setUp(): void
    {
        $this->previousHome = getenv('HOME');
        $this->home = sys_get_temp_dir() . '/lm-cluster-flow-' . getmypid() . '-' . random_int(1000, 9999);

        mkdir($this->home . '/.kube', 0o700, true);
        touch($this->home . '/.kube/config');
        putenv('HOME=' . $this->home);

        $this->kubectl = new StubKubectl();
        $this->app = $this->fixture();
    }

    protected function tearDown(): void
    {
        unlink($this->home . '/.kube/config');
        rmdir($this->home . '/.kube');
        rmdir($this->home);
        putenv($this->previousHome === false ? 'HOME' : 'HOME=' . $this->previousHome);
    }

    /** **Sedno kroku**: skrót otwiera klaster, a takt przynosi spis rodzajów. */
    public function testTheShortcutShowsTheResourceTree(): void
    {
        $this->openCluster();

        // Wiersze drzewa niosą prowadnice i znacznik gałęzi (`└─▶ core`), więc
        // porównujemy zawieranie, a nie równość — tak samo, jak przy drzewie
        // katalogów z kroku 31.
        self::assertStringContainsString('core', $this->joined(), 'grupa rdzenna stoi w korzeniu drzewa');
    }

    /**
     * **Rodzaje pochodzą z klastra**, więc rozwinięcie grupy pokazuje to, co
     * klaster podał — bez ani jednej nazwy wpisanej w kod aplikacji.
     */
    public function testExpandingTheGroupShowsKindsFromTheCluster(): void
    {
        $this->openCluster();
        $this->press(KeyPress::special(Key::Enter, "\r"));
        $this->tick();

        $texts = $this->joined();

        self::assertStringContainsString('pods', $texts);
        self::assertStringContainsString('secrets', $texts);
    }

    /**
     * Rozwinięcie rodzaju **pyta klaster o listę** — i dopiero wtedy, bo każde
     * takie pytanie to proces potomny.
     */
    public function testExpandingAKindAsksForItsResources(): void
    {
        $this->openCluster();
        $callsBefore = count($this->kubectl->calls);

        $this->press(KeyPress::special(Key::Enter, "\r"));
        $this->tick();

        self::assertSame($callsBefore, count($this->kubectl->calls), 'rozwinięcie grupy nie pyta o nic');

        $this->kubectl->willReturn(self::PODS);
        $this->press(KeyPress::special(Key::ArrowDown, "\eOB"));
        $this->press(KeyPress::special(Key::Enter, "\r"));
        $this->tick();

        self::assertStringContainsString('get pods', $this->kubectl->lastArguments());
        self::assertStringContainsString('web-1', $this->joined(), 'pod z klastra stanął w drzewie');
    }

    /**
     * **Niedostępny klaster kończy się zdaniem, a nie pustym drzewem** — to jest
     * osobne wymaganie planu kroku i osobny stan ekranu.
     */
    public function testUnreachableClusterSaysWhatIsWrong(): void
    {
        $this->kubectl
            ->willReturn(self::CONFIG)
            ->willAnswer(BackgroundState::done(
                '{"clientVersion":{"gitVersion":"v1.25.2"}}',
                1,
                'The connection to the server localhost:8080 was refused',
            ));

        $this->press(KeyPress::ctrl('k'));
        $this->tick();
        $this->tick();
        $this->tick();
        $this->tick();

        $texts = $this->joined();

        self::assertStringContainsString('was refused', $texts, 'powód pochodzi od klienta, nie z domysłu');
        self::assertStringNotContainsString('core', $texts, 'drzewa nie ma, bo nie ma czego pytać');
    }

    /**
     * **Brak bieżącego kontekstu jest stanem z podpowiedzią**, a nie awarią.
     *
     * Tak wygląda maszyna projektu: jeden kontekst w pliku, żaden bieżący.
     */
    public function testMissingContextAsksForAChoice(): void
    {
        $this->kubectl->willReturn('{"contexts":[{"name":"ca-dev","context":{}}],"current-context":""}');

        $this->press(KeyPress::ctrl('k'));
        $this->tick();
        $this->tick();

        $texts = $this->joined();

        self::assertStringContainsString('module.k8s.stage.noContext', $texts);
        self::assertSame(1, count($this->kubectl->calls), 'bez klastra nie pytamy o nic ponad sam plik');
    }

    /**
     * **Zdanie o braku klastra stoi raz i we wnętrzu panelu** (poprawka z 2026-08-16).
     *
     * Do tej poprawki wypisywały je **dwa** miejsca naraz — górny pas i treść
     * ekranu — a treść rysowała się od pierwszego wiersza strefy, czyli dokładnie
     * na obwódce, którą ekran rysuje sobie sam (`DrawsOwnFrame`).
     */
    public function testTheMissingClusterSentenceStandsOnceAndInsideTheFrame(): void
    {
        $this->kubectl->willReturn('{"contexts":[{"name":"ca-dev","context":{}}],"current-context":""}');

        $this->press(KeyPress::ctrl('k'));
        $this->tick();
        $this->tick();

        $screen = $this->app->screens->current();
        $bounds = new Rect(0, 0, self::ROWS, self::COLUMNS);
        $runs = self::runsOf($screen->draw($bounds));

        self::assertNotSame([], $runs, 'stan bez klastra mówi zdaniem');

        foreach ($runs as $run) {
            self::assertGreaterThan($bounds->row, $run->row, 'zdanie nie leży na górnej obwódce');
            self::assertGreaterThanOrEqual(
                $bounds->column + Panel::CONTENT_COLUMN,
                $run->column,
                'zdanie nie leży na lewej obwódce',
            );
        }

        $header = $screen->header();

        self::assertNotNull($header, 'ekran klastra ma górny pas');
        self::assertStringNotContainsString(
            'module.k8s.stage.',
            implode("\n", self::textsOf($header->content->draw(new Rect(0, 0, 1, self::COLUMNS)))),
            'pas nie powtarza zdania, które stoi w treści',
        );
    }

    /** Ekran zna oba miejsca ogniska, więc `Tab` przenosi je na treść. */
    public function testTabMovesTheFocusToTheContentPane(): void
    {
        $this->openCluster();
        $this->press(KeyPress::special(Key::Tab, "\t"));
        $this->tick();

        self::assertNotSame([], $this->drawCurrent(), 'ekran rysuje się dalej po zmianie ogniska');
    }

    private function openCluster(): void
    {
        $this->kubectl
            ->willReturn(self::CONFIG)
            ->willReturn(self::VERSIONS)
            ->willReturn(self::RESOURCES);

        $this->press(KeyPress::ctrl('k'));

        // Cztery takty, a nie trzy: od kroku 59 pierwszy zamawia odczyt pliku
        // `kubeconfig`, bo miejsce ma dwie współrzędne i pierwsza z nich jest
        // pytaniem do dysku.
        $this->tick();
        $this->tick();
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

    /** Cała klatka jednym napisem — wiersze drzewa niosą prowadnice, więc równość tu nie działa. */
    private function joined(): string
    {
        return implode("\n", $this->drawCurrent());
    }

    /**
     * Same napisy klatki, wraz z ich położeniem — bo poprawka z 2026-08-16
     * dotyczy tego, **gdzie** wiersz stanął, a nie co w nim pisze.
     *
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
            kubectl: $this->kubectl,
        );
    }
}
