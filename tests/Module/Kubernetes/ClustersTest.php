<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Kubernetes;

use LightManager\Module\Kubernetes\Application\ClusterBook;
use LightManager\Module\Kubernetes\Application\ClusterOrigin;
use LightManager\Module\Kubernetes\Application\Clusters;
use LightManager\Module\Kubernetes\Application\ClusterSession;
use LightManager\Module\Kubernetes\Application\ConfigCatalog;
use LightManager\Module\Kubernetes\Domain\ValueObject\ClusterProfile;
use LightManager\Tests\Support\StubClusterBook;
use LightManager\Tests\Support\StubKubectl;
use PHPUnit\Framework\TestCase;

/**
 * Spis klastrów z dwóch źródeł (krok 59, D96 nr 3 i 4).
 *
 * Sprawdza to, co jest **miarą drugą planu kroku**: dwa wpisy o kontekstach tej
 * samej nazwy w dwóch plikach są dwoma różnymi miejscami — widać to w spisie
 * i w kluczu stanu. Sprawdza też trzy reguły dwóch źródeł: pochodzenie jest
 * widoczne, wpis własny wygrywa przy zbieżnej nazwie, a wpisu czytanego nie da
 * się skasować.
 *
 * Pliki są prawdziwe (puste, w katalogu tymczasowym), bo brak pliku rozstrzyga
 * się `is_file()`, a nie odpowiedzią klienta. Treść podaje atrapa portu; żaden
 * test nie wywołuje `kubectl`.
 */
final class ClustersTest extends TestCase
{
    private const CONFIG_DEFAULT = '{"contexts":[{"name":"default","context":{}},{"name":"ca-dev","context":{}}],'
        . '"current-context":"ca-dev"}';

    private const CONFIG_CLIENT = '{"contexts":[{"name":"default","context":{}}],"current-context":"default"}';

    private string $home = '';

    private string|false $previousHome = false;

    private string|false $previousKubeconfig = false;

    protected function setUp(): void
    {
        $this->previousHome = getenv('HOME');
        $this->previousKubeconfig = getenv('KUBECONFIG');
        $this->home = sys_get_temp_dir() . '/lm-clusters-' . getmypid() . '-' . random_int(1000, 9999);

        mkdir($this->home . '/.kube', 0o700, true);
        touch($this->home . '/.kube/config');
        touch($this->home . '/klient.yaml');
        putenv('HOME=' . $this->home);
        putenv('KUBECONFIG');
    }

    protected function tearDown(): void
    {
        foreach ([$this->home . '/.kube/config', $this->home . '/klient.yaml'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir($this->home . '/.kube');
        rmdir($this->home);
        putenv($this->previousHome === false ? 'HOME' : 'HOME=' . $this->previousHome);
        putenv($this->previousKubeconfig === false ? 'KUBECONFIG' : 'KUBECONFIG=' . $this->previousKubeconfig);
    }

    /**
     * **Miara druga planu kroku**: dwa konteksty `default` z dwóch plików stoją
     * na jednej liście jako **dwa różne miejsca**.
     */
    public function testTwoContextsOfTheSameNameInTwoFilesAreTwoPlaces(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_DEFAULT)->willReturn(self::CONFIG_CLIENT);
        putenv('KUBECONFIG=' . $this->home . '/klient.yaml');
        [$clusters] = $this->build($kubectl);

        $clusters->refresh();
        $this->pump($clusters, 4);

        $names = array_map(static fn ($row): string => $row->name, $clusters->rows());

        self::assertContains('default', $names);
        self::assertContains('default (klient.yaml)', $names, 'druga nazwa bierze przyrostek z nazwy pliku');
        self::assertCount(3, $names, 'dwa konteksty pliku domyślnego plus jeden z KUBECONFIG');

        $first = $clusters->row('default');
        $second = $clusters->row('default (klient.yaml)');

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertNotSame($first->kubeconfig, $second->kubeconfig, 'to są dwa pliki, więc dwa miejsca');
        self::assertSame('default', $second->context, 'nazwa kontekstu w pliku zostaje ta sama');
    }

    /** Przełączenie między nimi zmienia **klucz stanu**, a nie tylko kontekst. */
    public function testSwitchingBetweenThemChangesTheStateKey(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_DEFAULT)->willReturn(self::CONFIG_CLIENT);
        putenv('KUBECONFIG=' . $this->home . '/klient.yaml');
        [$clusters, $session] = $this->build($kubectl);

        $clusters->refresh();
        $this->pump($clusters, 4);

        self::assertNull($clusters->select('default'));
        $firstKey = $session->key();
        $firstGeneration = $session->generation();

        self::assertNull($clusters->select('default (klient.yaml)'));

        self::assertNotSame($firstKey, $session->key(), 'tożsamością miejsca jest nazwa wiersza');
        self::assertGreaterThan($firstGeneration, $session->generation(), 'pokolenie unieważnia to, co przyszło');
        self::assertSame($this->home . '/klient.yaml', $session->place()?->kubeconfig);
    }

    /** Pochodzenie wiersza jest widoczne — to pierwsza z trzech reguł dwóch źródeł. */
    public function testOriginIsVisible(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_DEFAULT);
        $book = new StubClusterBook(self::bookWith(
            ClusterProfile::of('praca', $this->home . '/klient.yaml', 'default'),
        ));
        [$clusters] = $this->build($kubectl, $book);

        $clusters->refresh();
        $this->pump($clusters, 6);

        self::assertSame(ClusterOrigin::Own, $clusters->row('praca')?->origin);
        self::assertSame(ClusterOrigin::DefaultConfig, $clusters->row('ca-dev')?->origin);
    }

    /** Wpis własny **wygrywa przy zbieżnej nazwie**, a przysłonięty zostaje widoczny. */
    public function testAnOwnEntryShadowsTheReadOne(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_DEFAULT);
        $book = new StubClusterBook(self::bookWith(
            ClusterProfile::of('ca-dev', $this->home . '/klient.yaml', 'default'),
        ));
        [$clusters] = $this->build($kubectl, $book);

        $clusters->refresh();
        $this->pump($clusters, 6);

        $rows = array_values(array_filter($clusters->rows(), static fn ($row): bool => $row->name === 'ca-dev'));

        self::assertCount(2, $rows, 'kolizja zostaje w spisie jako wiersz przysłonięty, nie znika po cichu');
        self::assertFalse($rows[0]->shadowed, 'wpis własny stoi pierwszy i wygrywa');
        self::assertTrue($rows[1]->shadowed);
        self::assertSame($this->home . '/klient.yaml', $clusters->row('ca-dev')?->kubeconfig);
    }

    /** Wpisu czytanego **nie da się skasować** — moduł do `kubeconfig` nie pisze. */
    public function testAReadEntryCannotBeRemoved(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_DEFAULT);
        [$clusters] = $this->build($kubectl);

        $clusters->refresh();
        $this->pump($clusters, 4);

        self::assertFalse($clusters->remove('ca-dev'), 'wiersza czytanego nie ma w książce, więc nie ma czego usuwać');
        self::assertNotNull($clusters->row('ca-dev'), 'i nie znika ze spisu');
    }

    /**
     * **Migracja z ustawień nie gubi zapamiętanego miejsca** (plan, punkt 7).
     */
    public function testMigrationMovesTheRememberedPlaceIntoTheBook(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_DEFAULT);
        $book = new StubClusterBook(new ClusterBook(), null, fresh: true);
        [$clusters] = $this->build($kubectl, $book);

        self::assertTrue($clusters->isFresh());
        $clusters->migrate('ca-dev', 'produkcja');

        $entry = $clusters->find('ca-dev');

        self::assertNotNull($entry, 'zapamiętany kontekst stał się wpisem książki');
        self::assertSame(Clusters::defaultConfigPath(), $entry->kubeconfig, 'wskazuje plik domyślny');
        self::assertSame('produkcja', $entry->namespace, 'przestrzeń nazw też przeżyła');
        self::assertSame('ca-dev', $clusters->currentName(), 'i pozostaje wyborem bieżącym');
        self::assertSame(1, $book->saveCount, 'migracja zapisuje książkę raz');
    }

    /** Migracja bez zapamiętanego miejsca **nic nie robi** — nie zakłada pustego wpisu. */
    public function testMigrationWithNothingRememberedWritesNothing(): void
    {
        $book = new StubClusterBook(new ClusterBook(), null, fresh: true);
        [$clusters] = $this->build(new StubKubectl(), $book);

        $clusters->migrate('', '');

        self::assertSame(0, $book->saveCount);
    }

    /** Ścieżki z `KUBECONFIG` wchodzą do spisu plików — standard narzędzia. */
    public function testEnvironmentPathsAreRead(): void
    {
        putenv('KUBECONFIG=' . $this->home . '/klient.yaml:' . $this->home . '/nie-ma.yaml');
        [$clusters] = $this->build(new StubKubectl());

        $paths = $clusters->paths();

        self::assertContains($this->home . '/klient.yaml', $paths);
        self::assertContains($this->home . '/nie-ma.yaml', $paths, 'plik nieobecny też jest zamówiony — powie o tym stan');
        self::assertSame(Clusters::defaultConfigPath(), $paths[0], 'plik domyślny stoi pierwszy');
    }

    private function pump(Clusters $clusters, int $times): void
    {
        for ($i = 0; $i < $times; ++$i) {
            $clusters->tick();
        }
    }

    /** @return array{Clusters, ClusterSession} */
    private function build(StubKubectl $kubectl, ?StubClusterBook $book = null): array
    {
        $session = new ClusterSession();
        $clusters = new Clusters(
            $book ?? new StubClusterBook(),
            new ConfigCatalog($kubectl, $session),
            $session,
        );

        return [$clusters, $session];
    }

    private static function bookWith(ClusterProfile $entry): ClusterBook
    {
        $book = new ClusterBook();
        $book->add($entry);

        return $book;
    }
}
