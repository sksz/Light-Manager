<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Kubernetes;

use LightManager\Application\Dto\OutputShape;
use LightManager\Module\Kubernetes\Application\KubectlCall;
use LightManager\Module\Kubernetes\Domain\ValueObject\ContextName;
use LightManager\Module\Kubernetes\Domain\ValueObject\NamespaceName;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceKind;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceRef;
use LightManager\Module\Kubernetes\Infrastructure\KubectlService;
use LightManager\Tests\Support\ResetsSingletons;
use LightManager\Tests\Support\StubBackgroundProcess;
use PHPUnit\Framework\TestCase;

/**
 * Wiersz polecenia składany dla `kubectl` (krok 52).
 *
 * **Żaden test nie uruchamia klienta** — port pracy tłowej jest podstawiony
 * atrapą, więc sprawdzamy dokładnie to, co usługa robi: składa argumenty, cytuje
 * je i dokłada limity.
 *
 * Trzy sprawdzenia są tu ważniejsze od pozostałych, bo każde pilnuje reguły,
 * której złamanie objawia się dopiero na żywym kliencie: **limit żądania
 * w każdym wywołaniu prócz strumienia**, **brak scalania strumieni** (reguła 15f)
 * i **cytowanie wszystkiego**.
 */
final class KubectlServiceTest extends TestCase
{
    use ResetsSingletons;

    private StubBackgroundProcess $processes;

    private KubectlService $kubectl;

    protected function setUp(): void
    {
        $this->resetSingleton(KubectlService::class);
        $this->processes = new StubBackgroundProcess();
        $this->kubectl = KubectlService::getInstance();
        $this->kubectl->useSeam($this->processes);
    }

    protected function tearDown(): void
    {
        $this->resetSingleton(KubectlService::class);
    }

    public function testEveryCallCarriesItsRequestTimeout(): void
    {
        $this->kubectl->start(KubectlCall::list(self::pods(), NamespaceName::fallback(), null), 7);

        self::assertStringContainsString("'--request-timeout=7s'", $this->command());
        self::assertSame([7], $this->processes->timeouts, 'limit procesu idzie do rdzenia osobno');
    }

    /**
     * **Strumień nie dostaje limitu żądania** — to jedyny wyjątek od reguły
     * „limit w każdym wywołaniu”.
     *
     * `kubectl logs -f --request-timeout=5s` zamyka strumień po pięciu sekundach,
     * czyli limit zabiłby dokładnie tę pracę, która ma trwać.
     */
    public function testStreamingCallHasNoRequestTimeout(): void
    {
        $this->kubectl->start(KubectlCall::logs(self::reference(), null, 100, null), 3600);

        self::assertStringNotContainsString('--request-timeout', $this->command());
        self::assertSame([OutputShape::Stream], $this->processes->shapes, 'logi zamawia się jako strumień');
    }

    public function testResultCallsAskForTheDefaultShape(): void
    {
        $this->kubectl->start(KubectlCall::contexts(), 5);

        self::assertSame([OutputShape::Result], $this->processes->shapes);
    }

    /**
     * **Strumieni nie scalamy** (reguła 15f).
     *
     * Wyjściem `get -o json` jest treść, a klient pisze na strumieniu błędów
     * ostrzeżenia o wersji i o przełączonym kontekście. `2>&1` dałoby JSON, który
     * da się rozczytać wyłącznie wtedy, gdy klaster akurat o niczym nie ostrzegał.
     */
    public function testStreamsAreNeverMerged(): void
    {
        $this->kubectl->start(KubectlCall::list(self::pods(), NamespaceName::fallback(), null), 5);

        self::assertStringNotContainsString('2>&1', $this->command());
    }

    public function testEveryArgumentIsQuoted(): void
    {
        // Nazwa kontekstu pochodzi od narzędzia, które go zakładało, i bywa
        // taka — podkreślenia, kropki, myślniki. Przestrzeń nazw jest za to
        // etykietą DNS-1123 i szerszej postaci mieć nie może.
        $this->kubectl->start(
            KubectlCall::list(
                self::pods(),
                NamespaceName::of('moja-przestrzen'),
                ContextName::of('gke_projekt_europe-west1_klaster'),
            ),
            5,
        );

        $command = $this->command();

        self::assertStringStartsWith("kubectl 'get' 'pods'", $command);
        self::assertStringContainsString("'-n' 'moja-przestrzen'", $command);
        self::assertStringContainsString("'--context' 'gke_projekt_europe-west1_klaster'", $command);
    }

    /** Zasób klastrowy **nie dostaje `-n`** — pytanie o węzeł w przestrzeni nazw nie ma sensu. */
    public function testClusterScopedKindGetsNoNamespace(): void
    {
        $nodes = ResourceKind::of('nodes', 'Node', ResourceKind::CORE_GROUP, namespaced: false);
        $this->kubectl->start(KubectlCall::list($nodes, NamespaceName::fallback(), null), 5);

        self::assertStringNotContainsString("'-n'", $this->command());
    }

    /** Zmiana sekretu idzie **argumentem**, bo potomek nie dostaje wejścia. */
    public function testSecretPatchTravelsAsAnArgument(): void
    {
        $this->kubectl->start(KubectlCall::patch(self::reference(), '{"data":{"k":null}}', null), 5);

        $command = $this->command();

        self::assertStringContainsString("'patch'", $command);
        self::assertStringContainsString("'--type=merge'", $command);
        self::assertStringContainsString('\'{"data":{"k":null}}\'', $command);
    }

    /** Rodzaj wskazuje się adresem z grupą — `events` istnieje w dwóch grupach naraz. */
    public function testKindIsAddressedWithItsGroup(): void
    {
        $events = ResourceKind::of('events', 'Event', 'events.k8s.io');
        $this->kubectl->start(KubectlCall::list($events, NamespaceName::fallback(), null), 5);

        self::assertStringContainsString("'events.events.k8s.io'", $this->command());
    }

    private function command(): string
    {
        return $this->processes->startedCommands[0] ?? '';
    }

    private static function pods(): ResourceKind
    {
        return ResourceKind::of('pods', 'Pod');
    }

    private static function reference(): ResourceRef
    {
        return ResourceRef::of(self::pods(), NamespaceName::fallback(), 'web');
    }
}
