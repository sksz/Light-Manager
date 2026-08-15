<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Browser;

use LightManager\Application\Event\EventDeclaration;
use LightManager\Application\Event\EventRegistry;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserEvent;
use LightManager\Module\Browser\Application\BrowserEvents;
use LightManager\Module\Browser\Application\BrowserSettings;
use PHPUnit\Framework\TestCase;

/**
 * Słownik przeglądarki i reguła, którą dobiera się zdarzenie skutku (krok 46).
 *
 * Test pilnuje dwóch rzeczy naraz, obu niewidocznych z jednego miejsca w kodzie:
 * **deklaracja zgadza się z publikacją** (jedno i drugie idzie z enumu, więc
 * rozjazd byłby możliwy dopiero przez pomyłkę w nazwie) oraz **o skutku
 * rozstrzyga ton zdania**, a nie drugi rachunek prowadzony obok.
 */
final class BrowserEventsTest extends TestCase
{
    /** Siedemnaście zdarzeń: trzy ruchu i siedem czynności w dwóch postaciach. */
    public function testTheDictionaryHasSeventeenNamesAllInTheModuleNamespace(): void
    {
        $declarations = BrowserEvent::declarations();

        self::assertCount(17, $declarations);

        foreach ($declarations as $declaration) {
            self::assertStringStartsWith(BrowserSettings::ID . '.', $declaration->name);
            self::assertStringStartsWith('module.' . BrowserSettings::ID . '.event.', $declaration->labelKey);
        }
    }

    /** Każda z siedmiu czynności ma parę „udana / nieudana" — i żadna nie została sama. */
    public function testEveryOperationHasBothOutcomes(): void
    {
        $names = array_map(
            static fn (EventDeclaration $declaration): string => $declaration->name,
            BrowserEvent::declarations(),
        );

        foreach (['rename', 'mkdir', 'copy', 'move', 'trash', 'delete', 'undo'] as $operation) {
            self::assertContains('browser.' . $operation . '.done', $names);
            self::assertContains('browser.' . $operation . '.failed', $names);
        }
    }

    /** Wszystkie wchodzą do rejestru — czyli żadna nazwa nie wypada na przestrzeni nazw. */
    public function testTheRegistryTakesThemAll(): void
    {
        $registry = new EventRegistry();
        $registry->declare(BrowserSettings::ID, BrowserEvent::declarations());

        foreach (BrowserEvent::cases() as $event) {
            self::assertTrue($registry->has($event->value), $event->value . ' nie weszło do słownika');
        }
    }

    /** Ton błędu znaczy niepowodzenie, każdy inny — powodzenie. */
    public function testTheToneOfTheSentenceDecidesTheOutcome(): void
    {
        $registry = new EventRegistry();
        $registry->declare(BrowserSettings::ID, BrowserEvent::declarations());
        $events = new BrowserEvents($registry);

        self::assertSame(
            'skopiowano',
            $events->outcome(BrowserEvent::CopyDone, BrowserEvent::CopyFailed, Message::info('skopiowano'))?->text,
        );
        self::assertSame(
            'nie skopiowano',
            $events->outcome(
                BrowserEvent::CopyDone,
                BrowserEvent::CopyFailed,
                Message::error('nie skopiowano'),
            )?->text,
        );
    }

    /** Czynność, która nic nie powiedziała, niczego nie zmieniła — i nic nie ogłasza. */
    public function testNoSentenceMeansNoEvent(): void
    {
        $registry = new EventRegistry();
        $registry->declare(BrowserSettings::ID, BrowserEvent::declarations());

        self::assertNull(
            (new BrowserEvents($registry))->outcome(BrowserEvent::RenameDone, BrowserEvent::RenameFailed, null),
        );
    }
}
