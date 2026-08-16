<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Kubernetes;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\BackgroundState;
use LightManager\Module\Kubernetes\Application\ClusterSession;
use LightManager\Module\Kubernetes\Application\LogStream;
use LightManager\Module\Kubernetes\Domain\ValueObject\NamespaceName;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceKind;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceRef;
use LightManager\Tests\Support\StubKubectl;
use PHPUnit\Framework\TestCase;

/**
 * Logi płynące z pracy, która się nie kończy (krok 52).
 *
 * **To dla tej klasy rozbudowano rdzeń** (D91 nr 12), więc to ona ma pokazać, że
 * rozbudowa się opłaciła. Sprawdzane są trzy rzeczy, których nie widać z zewnątrz
 * i które psują się cicho: doklejanie **tylko nowych** bajtów, czekanie na koniec
 * niepełnego wiersza i rachunek bajtów, które wypadły z przesuwającego się
 * bufora.
 *
 * Żaden test nie uruchamia `kubectl` — porcje wypisu podaje atrapa portu.
 */
final class LogStreamTest extends TestCase
{
    public function testLinesArriveInPortionsWithoutRepeating(): void
    {
        $kubectl = new StubKubectl();
        $logs = $this->openedStream($kubectl);

        $this->feed($kubectl, "pierwszy\ndrugi\n");
        $logs->advance();

        self::assertSame(['pierwszy', 'drugi'], $logs->lines());

        // Druga porcja: bufor rdzenia niesie **całość**, a strumień ma dokleić
        // wyłącznie to, czego jeszcze nie widział. Powtórzenie oznaczałoby log,
        // w którym każdy wiersz mnoży się co klatkę.
        $this->feed($kubectl, "pierwszy\ndrugi\ntrzeci\n");
        $logs->advance();

        self::assertSame(['pierwszy', 'drugi', 'trzeci'], $logs->lines());
    }

    /**
     * Wiersz bez znaku końca **czeka na swoją resztę**.
     *
     * Potomek pisze porcjami, które nie układają się w wiersze — bez tego co
     * trzydziesta linia byłaby przecięta w losowym miejscu.
     */
    public function testIncompleteLineWaitsForItsEnding(): void
    {
        $kubectl = new StubKubectl();
        $logs = $this->openedStream($kubectl);

        $this->feed($kubectl, "gotowy\nniedokoń");
        $logs->advance();

        self::assertSame(['gotowy'], $logs->lines(), 'urwany wiersz nie ma prawa pokazać się w połowie');

        $this->feed($kubectl, "gotowy\nniedokończony\n");
        $logs->advance();

        self::assertSame(['gotowy', 'niedokończony'], $logs->lines());
    }

    /**
     * Bajty, które wypadły z bufora, **mówią o sobie liczbą**.
     *
     * Bufor strumienia w rdzeniu przesuwa się po przekroczeniu granicy, więc
     * czytający musi poznać dziurę — log ucięty po cichu wygląda tak samo, jak
     * log, w którym nic się nie działo.
     */
    public function testDroppedBytesAreCountedAsLost(): void
    {
        $kubectl = new StubKubectl();
        $logs = $this->openedStream($kubectl);

        $this->feed($kubectl, "pierwszy\n");
        $logs->advance();

        // Rdzeń wyrzucił początek: bufor zaczyna się teraz 100 bajtów dalej,
        // a my zdążyliśmy przeczytać dziewięć.
        $this->feed($kubectl, "dużo później\n", droppedBytes: 100);
        $logs->advance();

        self::assertSame(100 - strlen("pierwszy\n"), $logs->lostBytes());
        self::assertSame(['pierwszy', 'dużo później'], $logs->lines());
    }

    public function testLimitKeepsTheNewestLines(): void
    {
        $kubectl = new StubKubectl();
        $logs = $this->openedStream($kubectl);
        $logs->useLimit(2);

        $this->feed($kubectl, "a\nb\nc\nd\n");
        $logs->advance();

        self::assertSame(['c', 'd'], $logs->lines(), 'przy granicy wypadają najstarsze wiersze');
    }

    /** Zamknięcie zdejmuje pracę i zapomina wszystko — strumień jest jeden. */
    public function testClosingStopsTheWorkAndForgets(): void
    {
        $kubectl = new StubKubectl();
        $logs = $this->openedStream($kubectl);

        $this->feed($kubectl, "coś\n");
        $logs->advance();
        $logs->close();

        self::assertSame(1, $kubectl->stopCount);
        self::assertSame([], $logs->lines());
        self::assertFalse($logs->isOpen());
        self::assertNull($logs->reference());
    }

    /** Drugi pod **zastępuje** pierwszy, a nie mnoży prac (kryterium ukończenia kroku). */
    public function testOpeningASecondPodReplacesTheFirst(): void
    {
        $kubectl = new StubKubectl();
        $logs = $this->openedStream($kubectl);

        $logs->open(self::reference('drugi'), null, 100, new ClusterSession());

        self::assertSame(1, $kubectl->stopCount, 'poprzedni strumień miał zostać zatrzymany');
        self::assertSame('drugi', $logs->reference()?->name);
    }

    public function testStreamIsAskedForWithFollowAndTail(): void
    {
        $kubectl = new StubKubectl();
        $this->openedStream($kubectl);

        self::assertStringContainsString('-f', $kubectl->lastArguments());
        self::assertStringContainsString('--tail=100', $kubectl->lastArguments());
        self::assertTrue($kubectl->calls[0]->isStreaming(), 'logi są strumieniem, nie wynikiem');
    }

    private function openedStream(StubKubectl $kubectl): LogStream
    {
        $logs = new LogStream($kubectl);
        $kubectl->willAnswer(BackgroundState::running());
        $logs->open(self::reference('web'), null, 100, new ClusterSession());

        return $logs;
    }

    /** Podaje strumieniowi kolejną porcję — praca **zostaje trwająca**. */
    private function feed(StubKubectl $kubectl, string $buffer, int $droppedBytes = 0): void
    {
        $kubectl->feed(new BackgroundHandle(1), BackgroundState::running($buffer, '', $droppedBytes));
    }

    private static function reference(string $name): ResourceRef
    {
        return ResourceRef::of(ResourceKind::of('pods', 'Pod'), NamespaceName::fallback(), $name);
    }
}
