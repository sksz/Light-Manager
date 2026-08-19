<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Ssh;

use LightManager\Module\Ssh\Domain\Exception\InvalidHostProfileException;
use LightManager\Module\Ssh\Domain\ValueObject\AuthMethod;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Domain\ValueObject\HostTarget;
use PHPUnit\Framework\TestCase;

/**
 * Profil hosta jako **obiekt wartości pilnujący się sam** (krok 48).
 *
 * Zwykle taki test sprawdzałby porządek. Tutaj sprawdza **bezpieczeństwo**: te
 * pola trafiają do wiersza polecenia uruchamianego przez powłokę, więc każdy
 * przypadek odmowy jest tu po to, żeby nie dało się ich tam wstawić.
 */
final class HostProfileTest extends TestCase
{
    public function testTargetJoinsUserAndHost(): void
    {
        $profile = new HostProfile('biuro', 'example.com', 22, 'anna');

        self::assertSame('anna@example.com', $profile->target());
        self::assertSame('anna@example.com', $profile->label());
    }

    /** Port niedomyślny widać w spisie — domyślnego nie ma po co pokazywać. */
    public function testLabelShowsOnlyAnUnusualPort(): void
    {
        self::assertSame('anna@example.com:2222', (new HostProfile('b', 'example.com', 2222, 'anna'))->label());
    }

    /** Bez loginu zostaje sam host: `ssh` weźmie wtedy użytkownika bieżącego. */
    public function testTargetWithoutUserIsJustTheHost(): void
    {
        self::assertSame('example.com', (new HostProfile('b', 'example.com'))->target());
    }

    /**
     * **Najważniejszy przypadek w tym pliku.** Wartość zaczynająca się od `-`
     * byłaby dla `ssh` opcją, a nie hostem — i żadne cytowanie powłoki przed tym
     * nie chroni, bo powłoka nie jest tu problemem.
     */
    public function testHostCannotStartWithADash(): void
    {
        $this->expectException(InvalidHostProfileException::class);

        new HostProfile('podstęp', '-oProxyCommand=touch /tmp/ups');
    }

    public function testUserCannotStartWithADash(): void
    {
        $this->expectException(InvalidHostProfileException::class);

        new HostProfile('podstęp', 'example.com', 22, '-oProxyCommand=x');
    }

    /** Znaki powłoki odpadają, choć `escapeshellarg()` i tak by je unieszkodliwił. */
    public function testHostRefusesShellCharacters(): void
    {
        $this->expectException(InvalidHostProfileException::class);

        new HostProfile('podstęp', 'example.com; rm -rf /');
    }

    public function testPortMustFitInTheAllowedRange(): void
    {
        $this->expectException(InvalidHostProfileException::class);

        new HostProfile('biuro', 'example.com', 70000);
    }

    public function testNameCannotBeEmpty(): void
    {
        $this->expectException(InvalidHostProfileException::class);

        new HostProfile('   ', 'example.com');
    }

    /** Ścieżka klucza czyta się przy starcie łączenia — katalog roboczy nie jest wtedy niczym pewnym. */
    public function testKeyPathMustBeAbsolute(): void
    {
        $this->expectException(InvalidHostProfileException::class);

        new HostProfile('biuro', 'example.com', 22, 'anna', AuthMethod::Key, '.ssh/id_ed25519');
    }

    public function testAddressesInSixthVersionAreAllowed(): void
    {
        self::assertSame('::1', (new HostProfile('lokalny', '::1'))->host);
    }

    /** Tożsamością wpisu jest nazwa własna, a nie adres — dwa konta na jednym hoście to dwa wpisy. */
    public function testIdentityIsTheOwnName(): void
    {
        $first = new HostProfile('biuro', 'example.com', 22, 'anna');
        $second = new HostProfile('biuro', 'inny.example.com', 2222, 'jan');

        self::assertTrue($first->equals($second));
        self::assertFalse($first->equals(new HostProfile('dom', 'example.com', 22, 'anna')));
    }

    public function testAuthChangeKeepsEverythingElse(): void
    {
        $profile = (new HostProfile('biuro', 'example.com', 2222, 'anna'))
            ->withAuth(AuthMethod::Key, '/home/anna/.ssh/id_ed25519');

        self::assertSame(AuthMethod::Key, $profile->auth);
        self::assertSame('/home/anna/.ssh/id_ed25519', $profile->keyPath);
        self::assertSame(2222, $profile->port);
        self::assertSame('anna', $profile->user);
    }

    /** Postać `użytkownik@host:port` jest tą, którą użytkownik zna z `ssh`. */
    public function testTargetIsParsedFromOneLine(): void
    {
        $profile = HostTarget::parse('anna@example.com:2222');

        self::assertSame('anna', $profile->user);
        self::assertSame('example.com', $profile->host);
        self::assertSame(2222, $profile->port);
        self::assertSame('anna@example.com:2222', $profile->name);
    }

    public function testBareHostGetsTheDefaultPort(): void
    {
        $profile = HostTarget::parse('example.com');

        self::assertSame('', $profile->user);
        self::assertSame(22, $profile->port);
    }

    /**
     * **Adres IPv6 bez nawiasów czyta się w całości jako host.** „Ostatni
     * dwukropek oddziela port" byłoby regułą fałszywą — i tak samo czyta to `ssh`.
     */
    public function testUnbracketedSixthVersionAddressIsNotSplitOnAColon(): void
    {
        $profile = HostTarget::parse('fe80::1');

        self::assertSame('fe80::1', $profile->host);
        self::assertSame(22, $profile->port);
    }

    public function testBracketedSixthVersionAddressKeepsItsPort(): void
    {
        $profile = HostTarget::parse('anna@[fe80::1]:2222');

        self::assertSame('fe80::1', $profile->host);
        self::assertSame(2222, $profile->port);
        self::assertSame('anna', $profile->user);
    }

    public function testPortThatIsNotANumberIsRefused(): void
    {
        $this->expectException(InvalidHostProfileException::class);

        HostTarget::parse('example.com:abc');
    }
}
