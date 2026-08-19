<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\AddressBook;

use LightManager\Module\AddressBook\Domain\Exception\InvalidAddressEntryException;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;
use PHPUnit\Framework\TestCase;

/**
 * Wpis książki adresowej (krok 60).
 *
 * Trzy rzeczy są tu treścią, a nie ceremonią: **tożsamością jest
 * identyfikator** (nazwa wolno pusta, powtórzona i zmienna), **wartości
 * rozdziałów są nieprzezroczyste** (wpis nie wie, co znaczą), a walidacja
 * pilnuje **higieny, nie dziedziny** — bo regułę „co wolno wpisać w adres" zna
 * ten, kto adres czyta (D105 nr 3).
 */
final class AddressEntryTest extends TestCase
{
    public function testNewIdIsTwelveHexCharacters(): void
    {
        $id = AddressEntry::newId();

        self::assertSame(AddressEntry::ID_LENGTH, strlen($id));
        self::assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $id);
    }

    public function testTwoIdentifiersDiffer(): void
    {
        self::assertNotSame(AddressEntry::newId(), AddressEntry::newId());
    }

    public function testNameMayBeEmptyAndLabelFallsBackToId(): void
    {
        $entry = new AddressEntry('a1b2c3d4e5f6');

        self::assertSame('', $entry->name);
        self::assertSame('a1b2c3d4e5f6', $entry->label());
    }

    public function testIdentityIsTheIdentifierNotTheName(): void
    {
        $entry = new AddressEntry('a1b2c3d4e5f6', 'biuro');
        $renamed = $entry->withName('biuro-nowe');

        self::assertTrue($entry->equals($renamed));
        self::assertFalse($entry->equals(new AddressEntry('f6e5d4c3b2a1', 'biuro')));
    }

    public function testValuesLiveUnderChapterAndField(): void
    {
        $entry = (new AddressEntry('a1b2c3d4e5f6', 'biuro'))
            ->withValue('ssh', 'host', '10.0.0.5')
            ->withValue('ssh', 'port', 2222)
            ->withValue('docker', 'kind', 'tunnel');

        self::assertSame('10.0.0.5', $entry->value('ssh', 'host'));
        self::assertSame(2222, $entry->value('ssh', 'port'));
        self::assertSame(['ssh', 'docker'], $entry->chapters());
        self::assertSame(['host' => '10.0.0.5', 'port' => 2222], $entry->valuesOf('ssh'));
        self::assertNull($entry->value('k8s', 'context'));
    }

    public function testEmptiedChapterDisappearsInsteadOfPretendingToBeThere(): void
    {
        $entry = (new AddressEntry('a1b2c3d4e5f6'))->withValue('ssh', 'host', '10.0.0.5');

        self::assertFalse($entry->withoutValue('ssh', 'host')->hasChapter('ssh'));
        self::assertSame([], $entry->withoutValue('ssh', 'host')->chapters());
    }

    public function testWholeChapterGoesAtOnce(): void
    {
        $entry = (new AddressEntry('a1b2c3d4e5f6'))
            ->withValue('ssh', 'host', '10.0.0.5')
            ->withValue('ssh', 'user', 'michal')
            ->withValue('docker', 'kind', 'tunnel');

        self::assertSame(['docker'], $entry->withoutChapter('ssh')->chapters());
    }

    public function testIdentifierMustLookLikeOne(): void
    {
        $this->expectException(InvalidAddressEntryException::class);

        new AddressEntry('ZZZ');
    }

    public function testControlCharactersInNameAreRefused(): void
    {
        $this->expectException(InvalidAddressEntryException::class);

        new AddressEntry('a1b2c3d4e5f6', "biuro\n");
    }

    public function testChapterNameMustMatchTheShapeOfAModuleIdentifier(): void
    {
        $this->expectException(InvalidAddressEntryException::class);

        new AddressEntry('a1b2c3d4e5f6', '', ['Ssh' => ['host' => '10.0.0.5']]);
    }

    /**
     * Reguła dziedzinowa **nie jest** sprawą wpisu: adres bez sensu przechodzi,
     * bo o tym, czy `nie-adres` jest adresem, rozstrzyga ten, kto go czyta.
     */
    public function testDomainNonsensePassesBecauseItIsNotTheBooksBusiness(): void
    {
        $entry = new AddressEntry('a1b2c3d4e5f6', 'biuro', ['ssh' => ['host' => 'zupełnie nie adres']]);

        self::assertSame('zupełnie nie adres', $entry->value('ssh', 'host'));
    }
}
