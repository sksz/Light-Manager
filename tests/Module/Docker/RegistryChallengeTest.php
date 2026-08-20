<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Module\Docker\Application\Registry\RegistryChallenge;
use PHPUnit\Framework\TestCase;

/**
 * Rozbiór nagłówka `WWW-Authenticate` (krok 61, etap 2).
 *
 * Test jednostkowy, bo rozbiór jest **czystym rachunkiem na napisie** i celowo
 * mieszka w `Application`, z dala od `curl`a — ta sama zasada, którą
 * `GlfwKeyMapper` odsuwa mapowanie klawiszy od okna. Rejestru tu nie ma
 * i **żaden przebieg nie rozmawia z prawdziwym**.
 */
final class RegistryChallengeTest extends TestCase
{
    /** Postać, którą odsyła GHCR — trzy parametry, cudzysłowy, jeden wiersz. */
    public function testItReadsRealmServiceAndScope(): void
    {
        $challenge = RegistryChallenge::fromHeaders([
            "HTTP/1.1 401 Unauthorized\r\n",
            'Www-Authenticate: Bearer realm="https://ghcr.io/token",service="ghcr.io",'
                . 'scope="repository:sksz/lm:pull"' . "\r\n",
        ]);

        self::assertNotNull($challenge);
        self::assertSame('https://ghcr.io/token', $challenge->realm);
        self::assertSame('ghcr.io', $challenge->service);
        self::assertSame('repository:sksz/lm:pull', $challenge->scope);
    }

    /**
     * **Zakres bywa listą i przecinek w nim nie jest granicą parametru.**
     *
     * To jest powód, dla którego rozbiór idzie wyrażeniem, a nie podziałem po
     * przecinkach: `repository:a:pull,push` rozerwałoby się w połowie, a token
     * przyszedłby na węższe uprawnienie, niż poproszono — czyli wypchnięcie
     * kończyłoby się odmową, której nie widać w kodzie.
     */
    public function testACommaInsideScopeDoesNotSplitTheHeader(): void
    {
        $challenge = RegistryChallenge::fromHeaders([
            'WWW-Authenticate: Bearer realm="https://auth.example.com/token",service="reg",'
                . 'scope="repository:zespol/api:pull,push"',
        ]);

        self::assertNotNull($challenge);
        self::assertSame('repository:zespol/api:pull,push', $challenge->scope);
    }

    /** Adres drugiego obiegu skleja się z `realm` i tego, o co pytamy. */
    public function testTheTokenUrlCarriesServiceAndScope(): void
    {
        $challenge = RegistryChallenge::fromHeaders([
            'WWW-Authenticate: Bearer realm="https://auth.example.com/token",service="reg",scope="repository:a:pull"',
        ]);

        self::assertNotNull($challenge);
        self::assertSame(
            'https://auth.example.com/token?service=reg&scope=repository%3Aa%3Apull',
            $challenge->tokenUrl(),
        );
    }

    /** Samo `realm` wystarcza — rejestr, który o nic więcej nie prosi. */
    public function testRealmAloneIsEnough(): void
    {
        $challenge = RegistryChallenge::fromHeaders(['WWW-Authenticate: Bearer realm="https://a.example/token"']);

        self::assertNotNull($challenge);
        self::assertSame('https://a.example/token', $challenge->tokenUrl());
    }

    /**
     * **Rejestr bez uwierzytelnienia nie stawia wyzwania i to nie jest błąd.**
     *
     * Taki jest `registry:2` z celu `make registry-start`: odpowiada `200` już
     * w pierwszym obiegu, więc drugiego nie ma po co robić.
     */
    public function testNoChallengeMeansNoSecondLeg(): void
    {
        self::assertNull(RegistryChallenge::fromHeaders(["HTTP/1.1 200 OK\r\n", "Content-Type: application/json\r\n"]));
    }

    /** `Basic` nie jest wyzwaniem tego rodzaju — tokenu nie ma skąd wziąć. */
    public function testBasicChallengeIsNotABearerChallenge(): void
    {
        self::assertNull(RegistryChallenge::fromHeaders(['WWW-Authenticate: Basic realm="registry"']));
    }

    /** Wyzwanie `Bearer` bez `realm` jest bezużyteczne, więc nie powstaje. */
    public function testBearerWithoutRealmIsRejected(): void
    {
        self::assertNull(RegistryChallenge::fromHeaders(['WWW-Authenticate: Bearer service="reg"']));
    }
}
