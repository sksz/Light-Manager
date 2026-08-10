<?php

declare(strict_types=1);

namespace LightManager\Tests\Application\Command;

use LightManager\Application\Command\CommandRegistry;
use LightManager\Tests\Support\FakeCommand;
use PHPUnit\Framework\TestCase;

final class CommandRegistryTest extends TestCase
{
    public function testAcceptsOnlyNamesFromTheOwnersNamespace(): void
    {
        $registry = new CommandRegistry();
        $registry->add('file-info', [
            new FakeCommand('file-info.jump'),
            new FakeCommand('core.quit'),
            new FakeCommand('jump'),
        ]);

        self::assertNotNull($registry->find('file-info.jump'));
        self::assertNull($registry->find('core.quit'), 'moduł nie wchodzi w cudzą przestrzeń nazw');
        self::assertNull($registry->find('jump'), 'nazwa bez przestrzeni też jest cudza');
        self::assertCount(2, $registry->rejections());
        self::assertSame('command.rejected.namespace', $registry->rejections()[0]->reasonKey);
    }

    public function testSameNameTwiceIsRejectedWithItsOwnReason(): void
    {
        $registry = new CommandRegistry();
        $registry->add('core', [new FakeCommand('core.help'), new FakeCommand('core.help')]);

        self::assertCount(1, $registry->all());
        self::assertCount(1, $registry->rejections());
        self::assertSame('command.rejected.duplicate', $registry->rejections()[0]->reasonKey);
    }

    /** Sama przestrzeń bez nazwy nie jest nazwą. */
    public function testBareNamespaceIsRejected(): void
    {
        $registry = new CommandRegistry();
        $registry->add('core', [new FakeCommand('core.')]);

        self::assertSame([], $registry->all());
    }

    public function testListsCommandsAlphabetically(): void
    {
        $registry = new CommandRegistry();
        $registry->add('core', [
            new FakeCommand('core.quit'),
            new FakeCommand('core.help'),
            new FakeCommand('core.settings'),
        ]);

        self::assertSame(
            ['core.help', 'core.quit', 'core.settings'],
            array_map(static fn ($command): string => $command->name(), $registry->all()),
        );
    }

    public function testFindsCommandsByPrefix(): void
    {
        $registry = $this->core();

        self::assertCount(2, $registry->matching('core.s'));
        self::assertCount(5, $registry->matching(''), 'pusty przedrostek pasuje do wszystkiego');
        self::assertSame([], $registry->matching('nic.'));
    }

    public function testCommonPrefixIsWhatTabWouldAdd(): void
    {
        $registry = $this->core();

        self::assertSame('core.s', $registry->commonPrefix('core.s'), 'dwie pasujące, wspólne jest tyle, ile wpisano');
        self::assertSame('core.help', $registry->commonPrefix('core.h'), 'jedna pasująca — dopisuje się cała');
        self::assertSame('core.', $registry->commonPrefix(''), 'wszystkie mają wspólną przestrzeń');
    }

    /** Uzupełnianie nie ma prawa odebrać użytkownikowi tego, co napisał. */
    public function testCommonPrefixOfNothingLeavesTheTypedTextAlone(): void
    {
        self::assertSame('xyz', $this->core()->commonPrefix('xyz'));
    }

    private function core(): CommandRegistry
    {
        $registry = new CommandRegistry();
        $registry->add('core', [
            new FakeCommand('core.help'),
            new FakeCommand('core.settings'),
            new FakeCommand('core.saved'),
            new FakeCommand('core.quit'),
            new FakeCommand('core.theme'),
        ]);

        return $registry;
    }
}
