<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Kubernetes;

use LightManager\Application\Dto\BackgroundState;
use LightManager\Module\Kubernetes\Application\Clusters;
use LightManager\Module\Kubernetes\Application\ClusterSession;
use LightManager\Module\Kubernetes\Application\ClusterStage;
use LightManager\Module\Kubernetes\Application\ClusterState;
use LightManager\Module\Kubernetes\Application\ConfigCatalog;
use LightManager\Module\Kubernetes\Domain\ValueObject\ClusterProfile;
use LightManager\Tests\Support\StubKubectl;
use LightManager\Tests\Support\StubKubernetesState;
use PHPUnit\Framework\TestCase;

/**
 * Gdzie jesteśmy i czy klaster odpowiada (krok 52; miejsce dwuwspółrzędne
 * i dwa nowe stany — krok 59).
 *
 * **Najważniejszy jest tu stan „nie ma klastra”** — plan kroku 52 żądał wprost,
 * żeby to on, a nie widok pełen podów, był pierwszym narysowanym poprawnie.
 * Taki jest stan maszyny projektu: `kubeconfig` ma jeden kontekst i żaden nie
 * jest bieżący.
 *
 * Test podstawia `HOME` na katalog tymczasowy i **zakłada tam prawdziwe pliki
 * `kubeconfig`** — puste, bo treść podaje atrapa portu. Bez plików na dysku nie
 * dałoby się sprawdzić rzeczy, którą krok 59 dokłada: że **brak pliku
 * rozstrzyga się bez procesu potomnego**, więc jest odróżnialny od pliku bez
 * kontekstów.
 *
 * Żaden test nie wywołuje `kubectl` — odpowiedzi podaje atrapa portu.
 */
final class ClusterStateTest extends TestCase
{
    /** Wypis `kubectl config view -o json` z jednym kontekstem i **bez bieżącego**. */
    private const CONFIG_WITHOUT_CURRENT = '{"contexts":[{"name":"ca-dev","context":{"cluster":"ca-dev"}}],'
        . '"current-context":""}';

    private const CONFIG_WITH_CURRENT = '{"contexts":[{"name":"ca-dev","context":{"cluster":"ca-dev",'
        . '"namespace":"produkcja"}},{"name":"minikube","context":{"cluster":"minikube"}}],'
        . '"current-context":"minikube"}';

    private const VERSIONS = '{"clientVersion":{"gitVersion":"v1.25.2"},"serverVersion":{"gitVersion":"v1.30.1"}}';

    private string $home = '';

    private string|false $previousHome = false;

    protected function setUp(): void
    {
        $this->previousHome = getenv('HOME');
        $this->home = sys_get_temp_dir() . '/lm-k8s-' . getmypid() . '-' . random_int(1000, 9999);

        mkdir($this->home . '/.kube', 0o700, true);
        touch($this->home . '/.kube/config');
        putenv('HOME=' . $this->home);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->home . '/.kube/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->home . '/.kube');
        rmdir($this->home);
        putenv($this->previousHome === false ? 'HOME' : 'HOME=' . $this->previousHome);
    }

    /**
     * **Brak bieżącego kontekstu jest stanem, nie awarią** — i ma własną nazwę,
     * po której ekran pozna, że ma poprosić o wybór.
     */
    public function testNoCurrentContextIsItsOwnState(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_WITHOUT_CURRENT);
        [$cluster, $clusters] = $this->build($kubectl);

        $cluster->begin();
        $this->pump($cluster, $clusters, times: 4);

        self::assertSame(ClusterStage::NoContext, $cluster->stage());
        self::assertCount(1, $cluster->contexts(), 'kontekst z pliku ma być na liście, choć nie jest bieżący');
        self::assertNull($cluster->current());
        self::assertSame(1, count($kubectl->calls), 'bez miejsca nie ma o co pytać klastra');
    }

    /** Bieżący kontekst z pliku wchodzi sam i pociąga za sobą pytanie o wersje. */
    public function testCurrentContextFromTheFileIsTaken(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_WITH_CURRENT)->willReturn(self::VERSIONS);
        $session = new ClusterSession();
        [$cluster, $clusters] = $this->build($kubectl, session: $session);

        $cluster->begin();
        $this->pump($cluster, $clusters, times: 4);

        self::assertSame('minikube', $cluster->current()?->value);
        self::assertSame(ClusterStage::Ready, $cluster->stage());
        self::assertSame('minikube', $session->context()?->value);
    }

    /**
     * **Zapamiętany wybór wygrywa z bieżącym z pliku.**
     *
     * Zapamiętany jest wyborem zrobionym w tej aplikacji, bieżący — zrobionym
     * gdzie indziej; przy sprzeczności wygrywa ten, który padł tutaj. Od kroku
     * 59 zapamiętanym jest **nazwa wpisu książki**, a nie pozycja ustawień.
     */
    public function testRememberedClusterWinsOverTheCurrentContext(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_WITH_CURRENT)->willReturn(self::VERSIONS);
        [$cluster, $clusters] = $this->build($kubectl, entries: $this->bookWith('ca-dev'));

        $cluster->begin();
        $this->pump($cluster, $clusters, times: 4);

        self::assertSame('ca-dev', $cluster->current()?->value);
    }

    /** Zapamiętany klaster, którego w pliku już nie ma, daje **własny stan**. */
    public function testAForgottenContextGetsItsOwnState(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_WITH_CURRENT);
        [$cluster, $clusters] = $this->build($kubectl, entries: $this->bookWith('klaster-którego-nie-ma'));

        $cluster->begin();
        $this->pump($cluster, $clusters, times: 4);

        self::assertSame(
            ClusterStage::UnknownContext,
            $cluster->stage(),
            'kontekst nieobecny w pliku to inna rada niż „klaster nie odpowiada"',
        );
    }

    /**
     * **Wpis wskazujący nieistniejący plik ma własny stan** i rozstrzyga się on
     * bez procesu potomnego — `kubectl` oddałby wtedy pustą konfigurację
     * z kodem zero, czyli odpowiedź nie do odróżnienia od pliku bez kontekstów.
     */
    public function testAMissingFileGetsItsOwnStateWithoutRunningTheClient(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_WITH_CURRENT);
        $book = self::bookOf('zdalny', $this->home . '/nie-ma-mnie.yaml', 'ca-dev');
        [$cluster, $clusters] = $this->build($kubectl, entries: $book);

        $cluster->begin();
        $this->pump($cluster, $clusters, times: 4);

        self::assertSame(ClusterStage::MissingFile, $cluster->stage());
        self::assertSame(
            $this->home . '/nie-ma-mnie.yaml',
            $cluster->problemParameters()['path'] ?? null,
            'zdanie ma powiedzieć, której ścieżki nie ma',
        );

        foreach ($kubectl->calls as $call) {
            self::assertNotSame(
                $this->home . '/nie-ma-mnie.yaml',
                $call->place->kubeconfig ?? null,
                'pliku, którego nie ma, nie pytamy procesem potomnym',
            );
        }
    }

    /** Przestrzeń nazw wpisu wchodzi razem z miejscem. */
    public function testNamespaceComesFromTheEntry(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_WITH_CURRENT)->willReturn(self::VERSIONS);
        $session = new ClusterSession();
        $book = self::bookOf('praca', $this->home . '/.kube/config', 'ca-dev', 'produkcja');
        [$cluster, $clusters] = $this->build($kubectl, $session, $book);

        $cluster->begin();
        $this->pump($cluster, $clusters, times: 4);

        self::assertSame('produkcja', $session->namespace()?->value);
    }

    /** Wpis bez przestrzeni dostaje `default` — tak przyjmuje Kubernetes. */
    public function testAnEntryWithoutNamespaceFallsBackToDefault(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_WITH_CURRENT)->willReturn(self::VERSIONS);
        $session = new ClusterSession();
        [$cluster, $clusters] = $this->build($kubectl, $session);

        $cluster->begin();
        $this->pump($cluster, $clusters, times: 4);

        self::assertSame('default', $session->namespace()?->value);
    }

    /**
     * **Wersja klienta przychodzi także wtedy, gdy klastra nie ma** — dlatego
     * czytamy wyjście, a nie kod wyjścia.
     */
    public function testUnreachableClusterStillGivesTheClientVersion(): void
    {
        $kubectl = (new StubKubectl())
            ->willReturn(self::CONFIG_WITH_CURRENT)
            ->willAnswer(BackgroundState::done(
                '{"clientVersion":{"gitVersion":"v1.25.2"}}',
                1,
                'The connection to the server localhost:8080 was refused',
            ));

        [$cluster, $clusters] = $this->build($kubectl);
        $cluster->begin();
        $this->pump($cluster, $clusters, times: 4);

        $versions = $cluster->versions();

        self::assertSame(ClusterStage::Unreachable, $cluster->stage());
        self::assertNotNull($versions, 'wersja klienta przychodzi mimo kodu niezerowego');
        self::assertSame('v1.25.2', $versions->client);
        self::assertFalse($versions->knowsServer());
        self::assertSame(
            'The connection to the server localhost:8080 was refused',
            $cluster->problemParameters()['reason'] ?? null,
            'powód pochodzi ze strumienia błędów klienta, a nie z domysłu',
        );
    }

    /** Wersje różniące się o więcej niż jedno wydanie są **ostrzeżeniem, nie odmową**. */
    public function testSkewedVersionsDoNotStopAnything(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_WITH_CURRENT)->willReturn(self::VERSIONS);
        [$cluster, $clusters] = $this->build($kubectl);

        $cluster->begin();
        $this->pump($cluster, $clusters, times: 4);

        self::assertTrue($cluster->versions()?->isSkewed(), 'v1.25 wobec v1.30 to pięć wydań różnicy');
        self::assertSame(ClusterStage::Ready, $cluster->stage(), 'różnica wersji niczego nie blokuje');
    }

    /** Pusty plik konfiguracyjny to też „nie ma czym pytać”, a nie awaria. */
    public function testEmptyConfigurationIsNoContext(): void
    {
        $kubectl = (new StubKubectl())->willReturn('{"contexts":null,"current-context":""}');
        [$cluster, $clusters] = $this->build($kubectl);

        $cluster->begin();
        $this->pump($cluster, $clusters, times: 4);

        self::assertSame(ClusterStage::NoContext, $cluster->stage());
    }

    /**
     * Takt modułu w miniaturze: koordynator posuwa odczyt plików, stan klastra
     * — pytanie o wersje. Kolejność jest ta sama, co w `ClusterScreen::tick()`.
     */
    private function pump(ClusterState $cluster, Clusters $clusters, int $times = 4): void
    {
        for ($i = 0; $i < $times; ++$i) {
            $clusters->tick();
            $cluster->advance();
        }
    }

    /** @return array{ClusterState, Clusters} */
    /**
     * @param list<ClusterProfile> $entries
     *
     * @return array{ClusterState, Clusters}
     */
    private function build(
        StubKubectl $kubectl,
        ?ClusterSession $session = null,
        array $entries = [],
    ): array {
        $session ??= new ClusterSession();
        $state = new StubKubernetesState();

        if ($entries !== []) {
            $state->makeCurrent($entries[0]->id);
        }

        $clusters = new Clusters($state, new ConfigCatalog($kubectl, $session), $session);
        $clusters->useEntries($entries);

        return [new ClusterState($kubectl, $session, $clusters), $clusters];
    }

    /** @return list<ClusterProfile> */
    private function bookWith(string $context): array
    {
        return self::bookOf($context, $this->home . '/.kube/config', $context);
    }

    /** @return list<ClusterProfile> */
    private static function bookOf(
        string $name,
        string $kubeconfig,
        string $context,
        string $namespace = '',
    ): array {
        return [ClusterProfile::of($name, $kubeconfig, $context, $namespace, id: 'a1b2c3d4e5f6')];
    }
}
