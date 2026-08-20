<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Module\Docker\Application\Registry\RegistryStage;
use LightManager\Module\Docker\Infrastructure\RegistryConversation;
use PHPUnit\Framework\TestCase;

/**
 * Maszyna stanu rozmowy z rejestrem — **trzy obiegi bez ani jednego bajtu
 * przez sieć** (krok 61, etap 2).
 *
 * Test istnieje, bo tej drogi **nie da się wywołać rejestrem lokalnym**:
 * `registry:2` z celu `make registry-start` nie uwierzytelnia, więc odpowiada
 * `200` w pierwszym obiegu i pozostałe dwa nigdy nie padają. Ścieżka
 * jednoobiegowa jest sprawdzona na żywym rejestrze i zapisana w dzienniku;
 * ścieżka trzyobiegowa ma dowód tutaj — i to jest zapisana granica, a nie
 * przeoczenie.
 *
 * `RegistryConversation` daje się sprawdzić bez `curl`a, bo dostaje uchwyt jako
 * `null` i posuwa się **kodami stanu**, nie transferem.
 */
final class RegistryConversationTest extends TestCase
{
    private const CHALLENGE = 'WWW-Authenticate: Bearer realm="https://auth.example.com/token",'
        . 'service="reg",scope="repository:a:pull"';

    /** Rejestr bez uwierzytelnienia: jeden obieg i koniec. */
    public function testAnOpenRegistryFinishesInOneLeg(): void
    {
        $conversation = $this->conversation();

        self::assertFalse($conversation->finish(200, 0), 'nie ma po co robić drugiego obiegu');
        self::assertSame(RegistryStage::Done, $conversation->stage());
        self::assertTrue($conversation->result()->isSuccessful());
    }

    /** `401` z wyzwaniem zamawia obieg drugi i mówi, dokąd. */
    public function testAChallengeAsksForASecondLeg(): void
    {
        $conversation = $this->conversation();
        $conversation->collectHeader(self::CHALLENGE);

        self::assertTrue($conversation->finish(401, 0), 'trzeba pytać o token');

        $challenge = $conversation->challenge();

        self::assertNotNull($challenge);
        self::assertSame('https://auth.example.com/token', $challenge->realm);
    }

    /**
     * **`401` po tokenie znaczy złe poświadczenia i ma własne zdanie.**
     *
     * Ponawianie w kółko byłoby pytaniem o to samo trzydzieści razy na sekundę
     * — tym, przed czym broni się krok 59 przy wersjach serwera.
     */
    public function testASecondRefusalIsDeniedInsteadOfRetriedForever(): void
    {
        $conversation = $this->conversation(RegistryStage::Retrying);
        $conversation->collectHeader(self::CHALLENGE);

        self::assertFalse($conversation->finish(401, 0), 'czwartego obiegu nie ma');
        self::assertSame(RegistryStage::Failed, $conversation->stage());
        self::assertSame('module.docker.registry.denied', $conversation->result()->problemKey);
    }

    /** `401` **bez** wyzwania jest odmową, a nie zaproszeniem. */
    public function testARefusalWithoutAChallengeIsDenied(): void
    {
        $conversation = $this->conversation();

        self::assertFalse($conversation->finish(401, 0));
        self::assertSame('module.docker.registry.denied', $conversation->result()->problemKey);
    }

    /** Zerwane połączenie mówi „nieosiągalny", a nie „odmowa". */
    public function testATransportFailureSaysUnreachable(): void
    {
        $conversation = $this->conversation();

        self::assertFalse($conversation->finish(0, 7));
        self::assertSame(RegistryStage::Failed, $conversation->stage());
        self::assertSame('module.docker.registry.unreachable', $conversation->result()->problemKey);
    }

    /**
     * `404` **nie jest awarią** — to odpowiedź „tego tu nie ma", a przy
     * `/v2/_catalog` znaczy „ten rejestr katalogu nie wystawia".
     */
    public function testAMissingResourceIsAnAnswerNotAFailure(): void
    {
        $conversation = $this->conversation();

        self::assertFalse($conversation->finish(404, 0));

        $result = $conversation->result();

        self::assertSame(RegistryStage::Done, $result->stage);
        self::assertTrue($result->isMissing());
        self::assertNull($result->problemKey, 'brak zasobu nie ma powodu niepowodzenia');
    }

    /** Bufory kolejnego obiegu są **czyste** — inaczej treść by się skleiła. */
    public function testEachLegStartsWithEmptyBuffers(): void
    {
        $conversation = $this->conversation();
        $conversation->collect('{"token":"pierwszy"}');
        $conversation->collectHeader(self::CHALLENGE);
        $conversation->finish(401, 0);

        self::assertSame('{"token":"pierwszy"}', $conversation->bodyNow());
    }

    private function conversation(RegistryStage $stage = RegistryStage::Asking): RegistryConversation
    {
        return new RegistryConversation(null, '/v2/_catalog', 1024, $stage);
    }
}
