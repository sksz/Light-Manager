<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubDockerApi;
use PHPUnit\Framework\TestCase;

/**
 * `k8s.deploy-image` — **czynność przechodząca przez dwa moduły** (krok 54).
 *
 * Przebieg sprawdza zdanie-miarę kroku: *pokazuje listę obrazów, które zna moduł
 * Dockera… a przy wyłączonym module Dockera ta sama czynność mówi, czego brakuje,
 * zamiast się wywrócić.*
 *
 * **Ani demona, ani klastra nie ma tu przez chwilę** — to jest kryterium
 * ukończenia kroku, nie wygoda testu: odpowiedzi udaje `StubDockerApi`,
 * a `kubectl` nie powstaje jako proces ani razu.
 */
final class DeployImageFlowTest extends TestCase
{
    private const NOW = 100.0;

    private const COLUMNS = 100;

    private const ROWS = 24;

    /** Dwa obrazy: jeden z etykietą i jeden **osierocony**, którego nie da się wdrożyć. */
    private const IMAGES = '[{"Id":"sha256:cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc",'
        . '"RepoTags":["sklep:1.2"],"Size":10485760,"Created":1},'
        . '{"Id":"sha256:dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd",'
        . '"RepoTags":[],"Size":2097152,"Created":2}]';

    private StubDockerApi $docker;

    private ScreenFixture $app;

    protected function setUp(): void
    {
        $this->docker = new StubDockerApi();
        $this->app = $this->fixture();
    }

    /**
     * **Sedno kroku**: moduł Kubernetesa pokazuje obrazy, które zna moduł Dockera
     * — i nie zna przy tym ani jednego jego typu.
     */
    public function testTheActionShowsImagesKnownToTheDockerModule(): void
    {
        $this->openImages();

        $this->deployImage();

        $texts = $this->overlayTexts();

        self::assertContains('sklep:1.2', $texts, 'obraz z cudzego modułu stoi na liście wyboru');
        self::assertContains(
            'module.k8s.deploy.buildNew',
            $texts,
            'pozycja „zbuduj nowy" prowadzi do cudzej komendy',
        );
    }

    /**
     * **Obraz osierocony nie wchodzi na listę.**
     *
     * Skrótu treści nie da się ani wypchnąć do rejestru, ani wpisać we wdrożenie,
     * więc pozycja obiecywałaby czynność, która skończy się odmową — a lista, na
     * której połowa pozycji nie działa, jest gorsza od krótszej.
     */
    public function testADanglingImageIsNotOffered(): void
    {
        $this->openImages();

        $this->deployImage();

        $texts = implode(' ', $this->overlayTexts());

        self::assertStringNotContainsString('dddddddddddd', $texts, 'obraz bez etykiety nie stoi na liście');
    }

    /**
     * **Kryterium ukończenia kroku: wyłączony moduł Dockera nie wywraca niczego.**
     *
     * Rejestr kwerend oddaje wtedy wynik z powodem, a nie `null` do obsłużenia
     * w każdym miejscu z osobna (15g) — więc czynność kończy się **zdaniem**.
     * Sprawdzamy to najostrzej, jak się da: pytając rejestr, w którym modułu
     * Dockera nie ma w ogóle.
     */
    public function testWithoutTheDockerModuleTheActionSaysWhatIsMissing(): void
    {
        $registry = $this->app->state->queries();

        self::assertTrue($registry->ask('nosuchmodule.images')->hasProblem(), 'brak wykonawcy jest zwykłym stanem');

        $outcome = $this->deployWithoutDocker();

        self::assertNotNull($outcome, 'czynność odpowiada nawet bez modułu Dockera');
        self::assertStringContainsString(
            'module.k8s.deploy.noDocker',
            (string) $outcome,
            'zdanie mówi, czego brakuje',
        );
    }

    /**
     * Czynność wywołana bez modułu Dockera — z **własnym**, pustym rejestrem.
     *
     * Zestaw testowy składa wszystkie sześć modułów, więc „bez Dockera” trzeba
     * zbudować wprost: `DeployImageFlow` bierze rejestry w konstruktorze, więc
     * puste znaczą dokładnie to samo, co moduł wyłączony w ustawieniach.
     */
    private function deployWithoutDocker(): ?string
    {
        $flow = new \LightManager\Module\Kubernetes\Presentation\DeployImageFlow(
            new \LightManager\Application\Query\QueryRegistry(),
            new \LightManager\Application\Command\CommandRegistry(),
            new \LightManager\Module\Kubernetes\Presentation\KubernetesQueries(
                new \LightManager\Application\Query\QueryRegistry(),
            ),
            new \LightManager\Module\Kubernetes\Application\ClusterActions(
                $this->app->kubectl,
                new \LightManager\Module\Kubernetes\Application\ClusterSession(),
            ),
            new \LightManager\Tests\Support\StubTranslator(),
            $this->app->settingsStore,
        );

        return $flow->begin()->message?->text;
    }

    /**
     * Otwarcie listy obrazów w module Dockera — **konieczne, i to jest fakt
     * o aplikacji, nie o teście**.
     *
     * Moduł Dockera pyta demona o obrazy dopiero wtedy, gdy ktoś na nie patrzy
     * (D90 nr 7): uruchomienie aplikacji nie kosztuje ani jednego bajtu na
     * gnieździe. Czynność `k8s.deploy-image` zastaje przez to listę pustą,
     * dopóki użytkownik tam nie zajrzy — i mówi o tym wprost, zamiast udawać
     * maszynę bez obrazów (`deploy.imagesNotRead`).
     */
    private function openImages(): void
    {
        // Kolejka atrapy oddaje **jedną odpowiedź na wywołanie**, a otwarcie
        // ekranu pyta najpierw o kontenery: bez pustej listy w kolejce odpowiedź
        // z obrazami poszłaby do tamtego pytania.
        $this->docker->willReturn('[]');
        $this->press(KeyPress::ctrl('o'));
        $this->tick();
        $this->tick();

        $this->docker->willReturn(self::IMAGES);
        $this->press(KeyPress::special(Key::F3, "\eOR"));
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

    /** Uruchomienie czynności tą samą drogą, którą prowadzi komenda i menu `F9`. */
    private function deployImage(): void
    {
        $command = $this->app->commandRegistry->find('k8s.deploy-image');

        self::assertInstanceOf(OpensOverlay::class, $command, 'czynność otwiera okno');

        $outcome = $command->overlayFor(new CommandInput());
        $overlay = $outcome?->next;

        self::assertNotNull($overlay, 'pierwsze ogniwo łańcucha to okno wyboru obrazu');

        $this->app->state->overlays()->open($overlay);
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

    /**
     * @param  list<Primitive> $primitives
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
        );
    }
}
