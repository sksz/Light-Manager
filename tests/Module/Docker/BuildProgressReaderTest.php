<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Module\Docker\Application\BuildMessageKind;
use LightManager\Module\Docker\Infrastructure\BuildProgressReader;
use PHPUnit\Framework\TestCase;

/**
 * Rozbieranie strumienia postępu budowy — **na próbkach, nigdy przez budowę**
 * (krok 51).
 *
 * Test pilnuje pułapki, którą plan kroku nazwał wprost: **niepowodzenie budowy
 * przychodzi w treści, a nie w kodzie odpowiedzi**. Odpowiedź HTTP nieudanej
 * budowy ma kod 200, więc gdyby ten czytnik przeoczył obiekt `error`, aplikacja
 * meldowałaby sukces po każdej porażce.
 */
final class BuildProgressReaderTest extends TestCase
{
    public function testStreamObjectsBecomeSteps(): void
    {
        $reader = new BuildProgressReader();

        $messages = $reader->push(
            '{"stream":"Step 1/3 : FROM alpine"}' . "\n"
            . '{"stream":"Step 2/3 : RUN echo"}' . "\n",
        );

        self::assertCount(2, $messages);
        self::assertSame(BuildMessageKind::Step, $messages[0]->kind);
        self::assertSame('Step 1/3 : FROM alpine', $messages[0]->text);
        self::assertSame('Step 2/3 : RUN echo', $messages[1]->text);
    }

    /** Skrót zbudowanego obrazu jest tym, po co cała budowa była. */
    public function testAuxCarriesTheBuiltImage(): void
    {
        $reader = new BuildProgressReader();

        $messages = $reader->push('{"aux":{"ID":"sha256:abc123"}}' . "\n");

        self::assertCount(1, $messages);
        self::assertSame(BuildMessageKind::Built, $messages[0]->kind);
        self::assertSame('sha256:abc123', $messages[0]->text);
    }

    public function testErrorObjectBecomesFailure(): void
    {
        $reader = new BuildProgressReader();

        $messages = $reader->push(
            '{"errorDetail":{"message":"pull access denied"},"error":"pull access denied"}' . "\n",
        );

        self::assertCount(1, $messages);
        self::assertSame(BuildMessageKind::Failure, $messages[0]->kind);
        self::assertSame('pull access denied', $messages[0]->text);
    }

    /** Obiekt przecięty w połowie czeka na resztę — jak ramka logu. */
    public function testAnObjectCutInHalfWaitsForTheRest(): void
    {
        $reader = new BuildProgressReader();

        self::assertSame([], $reader->push('{"stream":"Step 1'));

        $messages = $reader->push('/3 : FROM alpine"}' . "\n");

        self::assertCount(1, $messages);
        self::assertSame('Step 1/3 : FROM alpine', $messages[0]->text);
    }

    /**
     * Wiersz, którego nie da się rozczytać, **nie jest błędem budowy**.
     *
     * Demon ma prawo dołożyć pole, którego dziś nie znamy, a „nieznany komunikat”
     * pokazany w połowie udanej budowy wyglądałby jak awaria aplikacji.
     */
    public function testUnknownObjectsAreSkippedInSilence(): void
    {
        $reader = new BuildProgressReader();

        self::assertSame([], $reader->push('{"progressDetail":{}}' . "\n" . 'to nie jest JSON' . "\n"));
    }

    /** Pobieranie warstwy obrazu bazowego też jest krokiem, o którym warto powiedzieć. */
    public function testStatusObjectsBecomeSteps(): void
    {
        $reader = new BuildProgressReader();

        $messages = $reader->push('{"status":"Pulling from library/alpine"}' . "\n");

        self::assertCount(1, $messages);
        self::assertSame('Pulling from library/alpine', $messages[0]->text);
    }
}
