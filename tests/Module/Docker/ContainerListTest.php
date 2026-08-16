<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Module\Docker\Application\ContainerList;
use LightManager\Module\Docker\Application\DockerAction;
use LightManager\Module\Docker\Application\DockerResult;
use LightManager\Module\Docker\Infrastructure\DockerJsonReader;
use LightManager\Tests\Support\StubDockerApi;
use PHPUnit\Framework\TestCase;

/**
 * Stan listy kontenerów — **na atrapie portu, nigdy na demonie** (krok 51).
 *
 * Sprawdza to, co naprawdę jest do sprawdzenia: że zegar odświeżania chodzi
 * wyłącznie przy widocznym ekranie (D90 nr 7), że czynność nie czeka na własny
 * skutek i że odpowiedzi demona, które **nie są niepowodzeniem** — `304` przy
 * uruchamianiu, `404` przy usuwaniu — nie kończą się komunikatem o błędzie.
 */
final class ContainerListTest extends TestCase
{
    private const JSON = '[{"Id":"aaaaaaaaaaaa","Names":["/pierwszy"],"Image":"alpine","State":"running",'
        . '"Status":"Up 2 hours","Created":1,"Ports":[],"Labels":{"com.docker.compose.project":"dev"}},'
        . '{"Id":"bbbbbbbbbbbb","Names":["/drugi"],"Image":"nginx","State":"exited",'
        . '"Status":"Exited (0)","Created":2,"Ports":[],"Labels":{}}]';

    public function testListArrivesAndTheCursorStandsOnTheFirstEntry(): void
    {
        $api = (new StubDockerApi())->willReturn(self::JSON);
        $list = $this->listWith($api);

        $list->tick(0.0, visible: true);
        $list->tick(0.1, visible: true);

        self::assertCount(2, $list->entries());
        self::assertSame('pierwszy', $list->selected()?->name);
        self::assertSame('/containers/json?all=1', $api->paths[0]);
    }

    /**
     * **Zegar chodzi wyłącznie przy widocznym ekranie** (D90 nr 7).
     *
     * Bez tego warunku aplikacja pytałaby demona dwanaście razy na minutę przez
     * cały czas działania — także wtedy, gdy modułu nikt nie ogląda.
     */
    public function testTheClockStandsStillWhenNobodyIsLooking(): void
    {
        $api = (new StubDockerApi())->willReturn(self::JSON);
        $list = $this->listWith($api);

        $list->tick(0.0, visible: true);
        $list->tick(0.1, visible: true);
        $asked = count($api->paths);

        for ($second = 1; $second <= 60; ++$second) {
            $list->tick((float) $second, visible: false);
        }

        self::assertCount($asked, $api->paths, 'ekran niewidoczny nie zamawia ani jednego pytania');
    }

    /** Widoczny ekran odświeża się z zegara — ale nie częściej niż co pięć sekund. */
    public function testTheClockAsksAgainAfterFiveSeconds(): void
    {
        $api = (new StubDockerApi())->willReturn(self::JSON)->willReturn(self::JSON);
        $list = $this->listWith($api);

        $list->tick(0.0, visible: true);
        $list->tick(0.1, visible: true);
        $list->tick(4.9, visible: true);

        self::assertCount(1, $api->paths, 'przed upływem pięciu sekund nie pytamy ponownie');

        $list->tick(5.2, visible: true);

        self::assertCount(2, $api->paths);
    }

    /** Zawężenie do projektu compose nie kosztuje ani jednego pytania więcej. */
    public function testNarrowingToAComposeProjectCostsNoRequest(): void
    {
        $api = (new StubDockerApi())->willReturn(self::JSON);
        $list = $this->listWith($api);
        $list->tick(0.0, visible: true);
        $list->tick(0.1, visible: true);
        $asked = count($api->paths);

        $list->narrowTo('dev');

        self::assertSame(['dev'], $list->projects());
        self::assertCount(1, $list->entries());
        self::assertSame('pierwszy', $list->entries()[0]->name);
        self::assertCount($asked, $api->paths);
    }

    public function testActionGoesOutAndTheListRefreshesAfterIt(): void
    {
        $api = (new StubDockerApi())->willReturn(self::JSON)->willAnswer(DockerResult::done('', 204));
        $list = $this->listWith($api);
        $list->tick(0.0, visible: true);
        $list->tick(0.1, visible: true);

        $container = $list->selected();
        self::assertNotNull($container);
        $list->begin(DockerAction::Stop, $container);

        self::assertTrue($list->isWorking());
        self::assertSame(['POST /containers/aaaaaaaaaaaa/stop'], $api->changes);

        $list->tick(0.2, visible: true);
        $outcome = $list->takeOutcome();

        self::assertNotNull($outcome);
        self::assertTrue($outcome->successful);
        self::assertSame('pierwszy', $outcome->subject);
        self::assertNull($list->takeOutcome(), 'wynik odbiera się raz');
        self::assertContains('/containers/json?all=1', array_slice($api->paths, -1));
    }

    /**
     * **`304` przy zatrzymywaniu nie jest niepowodzeniem** — znaczy „już nie
     * działa”, czyli dokładnie ten stan, o który prosił użytkownik.
     */
    public function testNotModifiedCountsAsSuccess(): void
    {
        $api = (new StubDockerApi())->willReturn(self::JSON)->willAnswer(DockerResult::done('', 304));
        $list = $this->listWith($api);
        $list->tick(0.0, visible: true);
        $list->tick(0.1, visible: true);

        $container = $list->selected();
        self::assertNotNull($container);
        $list->begin(DockerAction::Stop, $container);
        $list->tick(0.2, visible: true);

        self::assertTrue($list->takeOutcome()?->successful);
    }

    /** Odmowa demona idzie do użytkownika **jego zdaniem**, a nie naszym domysłem. */
    public function testTheDaemonsRefusalCarriesItsOwnSentence(): void
    {
        $api = (new StubDockerApi())
            ->willReturn(self::JSON)
            ->willAnswer(DockerResult::done('{"message":"container is marked for removal"}', 409));
        $list = $this->listWith($api);
        $list->tick(0.0, visible: true);
        $list->tick(0.1, visible: true);

        $container = $list->selected();
        self::assertNotNull($container);
        $list->begin(DockerAction::Start, $container);
        $list->tick(0.2, visible: true);

        $outcome = $list->takeOutcome();

        self::assertNotNull($outcome);
        self::assertFalse($outcome->successful);
        self::assertSame('module.docker.action.rejected', $outcome->problemKey);
        self::assertSame('container is marked for removal', $outcome->problemParameters['reason'] ?? null);
    }

    /** Demon, który nie odpowiada, zostawia listę pustą **wraz z powodem**. */
    public function testAnUnreachableDaemonLeavesAReason(): void
    {
        $api = (new StubDockerApi())->willAnswer(DockerResult::failed('module.docker.daemon.unreachable'));
        $list = $this->listWith($api);

        $list->tick(0.0, visible: true);
        $list->tick(0.1, visible: true);

        self::assertSame([], $list->entries());
        self::assertSame('module.docker.daemon.unreachable', $list->problemKey());
    }

    private function listWith(StubDockerApi $api): ContainerList
    {
        return new ContainerList($api, new DockerJsonReader());
    }
}
