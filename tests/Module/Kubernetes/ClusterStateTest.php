<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Kubernetes;

use LightManager\Application\Dto\BackgroundState;
use LightManager\Module\Kubernetes\Application\ClusterSession;
use LightManager\Module\Kubernetes\Application\ClusterStage;
use LightManager\Module\Kubernetes\Application\ClusterState;
use LightManager\Tests\Support\StubKubectl;
use PHPUnit\Framework\TestCase;

/**
 * Gdzie jesteśmy i czy klaster odpowiada (krok 52).
 *
 * **Najważniejszy jest tu stan „nie ma bieżącego kontekstu”** — plan kroku żąda
 * wprost, żeby to on, a nie widok pełen podów, był pierwszym narysowanym
 * poprawnie. Taki jest stan maszyny projektu: `kubeconfig` ma jeden kontekst
 * i żaden nie jest bieżący.
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

    /**
     * **Brak bieżącego kontekstu jest stanem, nie awarią** — i ma własną nazwę,
     * po której ekran pozna, że ma poprosić o wybór.
     */
    public function testNoCurrentContextIsItsOwnState(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_WITHOUT_CURRENT);
        $cluster = new ClusterState($kubectl, new ClusterSession());

        $cluster->begin();
        $cluster->advance();

        self::assertSame(ClusterStage::NoContext, $cluster->stage());
        self::assertCount(1, $cluster->contexts(), 'kontekst z pliku ma być na liście, choć nie jest bieżący');
        self::assertNull($cluster->current());
        self::assertSame(1, count($kubectl->calls), 'bez kontekstu nie ma o co pytać klastra');
    }

    /** Bieżący kontekst z pliku wchodzi sam i pociąga za sobą pytanie o wersje. */
    public function testCurrentContextFromTheFileIsTaken(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_WITH_CURRENT)->willReturn(self::VERSIONS);
        $session = new ClusterSession();
        $cluster = new ClusterState($kubectl, $session);

        $cluster->begin();
        $cluster->advance();
        $cluster->advance();

        self::assertSame('minikube', $cluster->current()?->value);
        self::assertSame(ClusterStage::Ready, $cluster->stage());
        self::assertSame('minikube', $session->context()?->value);
    }

    /**
     * **Zapamiętany wybór wygrywa z bieżącym z pliku.**
     *
     * Zapamiętany jest wyborem zrobionym w tej aplikacji, bieżący — zrobionym
     * gdzie indziej; przy sprzeczności wygrywa ten, który padł tutaj.
     */
    public function testRememberedContextWinsOverTheCurrentOne(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_WITH_CURRENT)->willReturn(self::VERSIONS);
        $cluster = new ClusterState($kubectl, new ClusterSession());

        $cluster->remember('ca-dev');
        $cluster->begin();
        $cluster->advance();

        self::assertSame('ca-dev', $cluster->current()?->value);
    }

    /** Zapamiętany kontekst, którego w pliku już nie ma, ustępuje bieżącemu. */
    public function testForgottenContextFallsBackToTheCurrentOne(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_WITH_CURRENT)->willReturn(self::VERSIONS);
        $cluster = new ClusterState($kubectl, new ClusterSession());

        $cluster->remember('klaster-którego-nie-ma');
        $cluster->begin();
        $cluster->advance();

        self::assertSame('minikube', $cluster->current()?->value);
    }

    /** Przestrzeń nazw zapisana przy kontekście wchodzi razem z nim. */
    public function testNamespaceComesFromTheContextEntry(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_WITH_CURRENT)->willReturn(self::VERSIONS);
        $session = new ClusterSession();
        $cluster = new ClusterState($kubectl, $session);

        $cluster->remember('ca-dev');
        $cluster->begin();
        $cluster->advance();

        self::assertSame('produkcja', $session->namespace()?->value);
    }

    /** Kontekst bez przestrzeni w pliku dostaje `default` — tak przyjmuje Kubernetes. */
    public function testContextWithoutNamespaceFallsBackToDefault(): void
    {
        $kubectl = (new StubKubectl())->willReturn(self::CONFIG_WITH_CURRENT)->willReturn(self::VERSIONS);
        $session = new ClusterSession();
        $cluster = new ClusterState($kubectl, $session);

        $cluster->begin();
        $cluster->advance();

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

        $cluster = new ClusterState($kubectl, new ClusterSession());
        $cluster->begin();
        $cluster->advance();
        $cluster->advance();

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
        $cluster = new ClusterState($kubectl, new ClusterSession());

        $cluster->begin();
        $cluster->advance();
        $cluster->advance();

        self::assertTrue($cluster->versions()?->isSkewed(), 'v1.25 wobec v1.30 to pięć wydań różnicy');
        self::assertSame(ClusterStage::Ready, $cluster->stage(), 'różnica wersji niczego nie blokuje');
    }

    /** Pusty plik konfiguracyjny to też „nie ma czym pytać”, a nie awaria. */
    public function testEmptyConfigurationIsNoContext(): void
    {
        $kubectl = (new StubKubectl())->willReturn('{"contexts":null,"current-context":""}');
        $cluster = new ClusterState($kubectl, new ClusterSession());

        $cluster->begin();
        $cluster->advance();

        self::assertSame(ClusterStage::NoContext, $cluster->stage());
    }
}
