<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Module\Docker\Application\PushProgress;
use LightManager\Module\Docker\Application\PushStage;
use LightManager\Module\Docker\Application\PushWork;
use LightManager\Module\Docker\Infrastructure\BuildProgressReader;
use LightManager\Module\Docker\Infrastructure\RegistryAuth;
use LightManager\Tests\Support\StubDockerApi;
use PHPUnit\Framework\TestCase;

/**
 * Wypchnięcie obrazu do rejestru — **na atrapie portu, nigdy na rejestrze**
 * (krok 54).
 *
 * Sprawdza to, co naprawdę jest do sprawdzenia, i wszystkie trzy rzeczy wyszły
 * z **próby na żywym demonie**, a nie z projektu:
 *
 * 1. **`push` żąda, żeby obraz nosił już nazwę docelową** — odmawia zdaniem
 *    „an image does not exist locally with the tag", i to z kodem HTTP 200. Praca
 *    ma przez to dwa etapy, a nie jeden.
 * 2. **Praca trwa w obu etapach.** Migawka, która by o tym nie wiedziała, kończy
 *    czekanie po ułamku sekundy — dokładnie ten defekt zdarzył się przy odbiorze.
 * 3. **Nazwa i etykieta idą osobno**, a rozdziela je dwukropek stojący **po
 *    ostatnim ukośniku** — inaczej port rejestru wygląda jak etykieta.
 */
final class PushWorkTest extends TestCase
{
    private const AUTH = 'poswiadczenia';

    /** **Oznaczenie pada przed wypchnięciem** — inaczej rejestr odmawia. */
    public function testTheImageIsTaggedBeforeItIsPushed(): void
    {
        $api = (new StubDockerApi())->willReturn('')->willReturn("{\"status\":\"Pushed\"}\n");
        $work = new PushWork($api, new BuildProgressReader());

        $work->begin('lm/proba:1', 'ghcr.io/kto/lm/proba:1', self::AUTH);

        self::assertSame(PushStage::Tagging, $work->stage(), 'praca zaczyna się od oznaczenia');
        self::assertStringContainsString('/images/lm%2Fproba%3A1/tag', $api->paths[0]);
        self::assertStringContainsString('repo=ghcr.io%2Fkto%2Flm%2Fproba', $api->paths[0]);
        self::assertStringContainsString('tag=1', $api->paths[0]);

        $work->tick();

        self::assertSame(PushStage::Pushing, $work->stage(), 'po oznaczeniu rusza wypychanie');
        self::assertStringContainsString('/images/ghcr.io%2Fkto%2Flm%2Fproba/push?tag=1', $api->paths[1]);
        self::assertSame(self::AUTH, $api->registryAuth, 'poświadczenia idą nagłówkiem');
    }

    /**
     * **Praca trwa w obu etapach** — to jest test defektu, który wyszedł
     * dopiero na żywym demonie.
     */
    public function testTheWorkCountsAsRunningInBothStages(): void
    {
        $api = (new StubDockerApi())->willReturn('')->willReturn("{\"status\":\"Pushed\"}\n");
        $work = new PushWork($api, new BuildProgressReader());

        $work->begin('lm/proba:1', 'ghcr.io/kto/lm/proba:1', self::AUTH);

        self::assertTrue($work->isWorking(), 'oznaczanie jest pracą');
        self::assertTrue(self::snapshotOf($work)->isWorking(), 'migawka też o tym wie');

        $work->tick();

        self::assertTrue($work->isWorking(), 'wypychanie jest pracą');
        self::assertTrue(self::snapshotOf($work)->isWorking(), 'migawka też o tym wie');
    }

    /**
     * **Port rejestru nie jest etykietą.**
     *
     * `localhost:5000/lm/proba` ma dwukropek w części adresowej i etykiety nie ma
     * wcale, więc podział po ostatnim dwukropku dałby nazwę `localhost`.
     */
    public function testARegistryPortIsNotMistakenForATag(): void
    {
        $api = (new StubDockerApi())->willReturn('')->willReturn('');
        $work = new PushWork($api, new BuildProgressReader());

        $work->begin('lm/proba:1', 'localhost:5000/lm/proba', self::AUTH);
        $work->tick();

        self::assertStringContainsString('/images/localhost%3A5000%2Flm%2Fproba/push?tag=latest', $api->paths[1]);
    }

    /** Nieudane oznaczenie **nie próbuje wypychać** — nie ma czego. */
    public function testAFailedTagStopsBeforeThePush(): void
    {
        $api = (new StubDockerApi())->willReturn('brak obrazu', 404);
        $work = new PushWork($api, new BuildProgressReader());

        $work->begin('lm/nie-ma:1', 'ghcr.io/kto/lm/nie-ma:1', self::AUTH);
        $work->tick();

        self::assertSame(PushStage::Failed, $work->stage());
        self::assertCount(1, $api->paths, 'drugie wywołanie nie pada');
        self::assertSame('module.docker.push.notTagged', $work->problemKey());
    }

    /**
     * Nagłówek poświadczeń: **base64 wedle URL i bez dopełnienia**.
     *
     * Zwykły `base64_encode()` daje napis, który demon odrzuca z `401` — a to
     * jest pomyłka nie do odróżnienia od złego tokena.
     */
    public function testTheAuthHeaderIsUrlSafeBase64WithoutPadding(): void
    {
        $header = RegistryAuth::header('ghcr.io', 'kto', 'sekret');

        self::assertStringNotContainsString('+', $header);
        self::assertStringNotContainsString('/', $header);
        self::assertStringNotContainsString('=', $header);

        $decoded = json_decode((string) base64_decode(strtr($header, '-_', '+/'), true), true);

        self::assertIsArray($decoded);
        self::assertSame('kto', $decoded['username'] ?? null);
        self::assertSame('sekret', $decoded['password'] ?? null);
        self::assertSame('ghcr.io', $decoded['serveraddress'] ?? null, 'pole jest jednym słowem, małymi literami');
    }

    /** Bez poświadczeń nagłówka nie ma — rejestr publiczny czyta bez logowania. */
    public function testWithoutCredentialsThereIsNoHeader(): void
    {
        self::assertSame('', RegistryAuth::header('ghcr.io', '', 'sekret'));
        self::assertSame('', RegistryAuth::header('ghcr.io', 'kto', ''));
    }

    private static function snapshotOf(PushWork $work): PushProgress
    {
        return new PushProgress(
            $work->stage(),
            $work->target(),
            $work->note(),
            $work->problemKey(),
            $work->problemParameters(),
        );
    }
}
