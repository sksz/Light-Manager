<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Ssh;

use LightManager\Module\Ssh\Domain\Exception\InvalidHostProfileException;
use LightManager\Module\Ssh\Domain\ValueObject\AuthMethod;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use PHPUnit\Framework\TestCase;

/**
 * Cel połączenia (krok 48; **od kroku 60 nie jest wpisem książki**).
 *
 * Zmieniły się dwie rzeczy i obie są tu treścią: **tożsamością jest
 * identyfikator wpisu**, a nie nazwa własna, oraz profil powstaje **z wiersza
 * kwerendy** — czyli z napisów, których nikt po drodze nie typuje.
 *
 * Samowalidacja jest tu warstwą bezpieczeństwa, nie porządkiem: trzy pola
 * trafiają do wiersza polecenia, więc wzorce są wąskie z założenia, a wartość
 * zaczynająca się od `-` odpada niezależnie od cytowania powłoki.
 */
final class HostProfileTest extends TestCase
{
    private const ID = 'a1b2c3d4e5f6';

    public function testTargetJoinsUserAndHost(): void
    {
        $profile = new HostProfile(self::ID, 'biuro', 'example.com', 22, 'anna');

        self::assertSame('anna@example.com', $profile->target());
        self::assertSame('anna@example.com', $profile->label());
    }

    public function testLabelShowsOnlyAnUnusualPort(): void
    {
        self::assertSame('example.com:2222', (new HostProfile(self::ID, 'biuro', 'example.com', 2222))->label());
    }

    public function testTargetWithoutUserIsJustTheHost(): void
    {
        self::assertSame('example.com', (new HostProfile(self::ID, 'biuro', 'example.com'))->target());
    }

    /**
     * Wartość zaczynająca się od myślnika byłaby dla `ssh` **opcją**, a nie
     * hostem — i żadne cytowanie powłoki przed tym nie chroni.
     */
    public function testHostCannotStartWithADash(): void
    {
        $this->expectException(InvalidHostProfileException::class);

        new HostProfile(self::ID, 'biuro', '-oProxyCommand=cokolwiek');
    }

    public function testUserCannotStartWithADash(): void
    {
        $this->expectException(InvalidHostProfileException::class);

        new HostProfile(self::ID, 'biuro', 'example.com', 22, '-oProxyCommand=x');
    }

    public function testHostRefusesShellCharacters(): void
    {
        $this->expectException(InvalidHostProfileException::class);

        new HostProfile(self::ID, 'biuro', 'example.com; rm -rf /');
    }

    public function testPortMustFitInTheAllowedRange(): void
    {
        $this->expectException(InvalidHostProfileException::class);

        new HostProfile(self::ID, 'biuro', 'example.com', 70000);
    }

    /**
     * **Nazwa wolno pusta** i to jest zmiana z kroku 60: tożsamość niesie
     * identyfikator, a książka dopuszcza wpisy bez nazwy.
     */
    public function testNameMayBeEmptyBecauseIdentityIsTheIdentifier(): void
    {
        $profile = new HostProfile(self::ID, '', 'example.com');

        self::assertSame('', $profile->name);
        self::assertSame(self::ID, $profile->id);
    }

    public function testKeyPathMustBeAbsolute(): void
    {
        $this->expectException(InvalidHostProfileException::class);

        new HostProfile(self::ID, 'biuro', 'example.com', 22, 'anna', AuthMethod::Key, 'klucz.pem');
    }

    public function testAddressesInSixthVersionAreAllowed(): void
    {
        self::assertSame('::1', (new HostProfile(self::ID, 'lokalny', '::1'))->host);
    }

    /** Tożsamością jest **identyfikator wpisu**, a nie nazwa i nie adres. */
    public function testIdentityIsTheEntryIdentifier(): void
    {
        $profile = new HostProfile(self::ID, 'biuro', 'example.com');

        self::assertTrue($profile->equals(new HostProfile(self::ID, 'zupełnie inna nazwa', '10.0.0.1')));
        self::assertFalse($profile->equals(new HostProfile('f6e5d4c3b2a1', 'biuro', 'example.com')));
    }

    public function testAuthChangeKeepsEverythingElse(): void
    {
        $changed = (new HostProfile(self::ID, 'biuro', 'example.com', 2222, 'anna'))
            ->withAuth(AuthMethod::Key, '/home/anna/.ssh/id_ed25519');

        self::assertSame(AuthMethod::Key, $changed->auth);
        self::assertSame('/home/anna/.ssh/id_ed25519', $changed->keyPath);
        self::assertSame(self::ID, $changed->id);
        self::assertSame('biuro', $changed->name);
        self::assertSame(2222, $changed->port);
        self::assertSame('anna', $changed->user);
    }

    /**
     * Profil z **wiersza kwerendy** — jedyna droga, którą powstaje od kroku 60,
     * i idzie wyłącznie przez napisy (15g).
     *
     * **Ścieżki klucza w wierszu nie ma** i wiersz jej nie niesie: pole jest
     * maskowane, więc stoi w nim `set`/`unset`. Wzięta stąd wprost wywracałaby
     * samowalidację i wycinała ze spisu każdy wpis z kluczem — wartość dokłada
     * `SshQueries::entry()` osobnym pytaniem, w chwili łączenia.
     */
    public function testProfileIsBuiltFromAQueryRow(): void
    {
        $profile = HostProfile::fromRow([
            'id' => self::ID,
            'name' => 'biuro',
            'host' => 'example.com',
            'port' => 2222,
            'user' => 'anna',
            'auth' => 'key',
            'keyPath' => 'set',
        ]);

        self::assertNotNull($profile);
        self::assertSame(self::ID, $profile->id);
        self::assertSame(2222, $profile->port);
        self::assertSame(AuthMethod::Key, $profile->auth);
        self::assertNull($profile->keyPath, 'znacznik pola maskowanego nie jest ścieżką');
    }

    /** Port bywa napisem, bo wiersz kwerendy niesie to, co zapisano. */
    public function testPortArrivesAsTextAndIsStillANumber(): void
    {
        self::assertSame(2222, HostProfile::fromRow([
            'id' => self::ID,
            'host' => 'example.com',
            'port' => '2222',
        ])?->port);
    }

    /**
     * **Wpis bez adresu nie jest błędem** — książka jest wspólna, więc wpis
     * niosący wyłącznie pola cudzego rozdziału po prostu nie jest hostem.
     */
    public function testEntryWithoutAnAddressIsNotAHost(): void
    {
        self::assertNull(HostProfile::fromRow(['id' => self::ID, 'name' => 'baza danych']));
    }

    /** Wiersz nie do przyjęcia wypada; reszta spisu ma zostać. */
    public function testUnusableRowFallsOutInsteadOfThrowing(): void
    {
        self::assertNull(HostProfile::fromRow(['id' => self::ID, 'host' => '-oProxyCommand=x']));
        self::assertNull(HostProfile::fromRow(['id' => '', 'host' => 'example.com']));
    }

    public function testUnknownAuthMethodFallsBackToTheAgent(): void
    {
        self::assertSame(AuthMethod::Agent, HostProfile::fromRow([
            'id' => self::ID,
            'host' => 'example.com',
            'auth' => 'zupełnie nieznany',
        ])?->auth);
    }
}
