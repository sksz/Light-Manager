<?php

declare(strict_types=1);

namespace LightManager\Tests\Application\Event;

use LightManager\Application\Event\AppEvent;
use LightManager\Application\Event\EventDeclaration;
use LightManager\Application\Event\EventRegistry;
use LightManager\Application\Module\DeclaresEvents;
use LightManager\Application\Module\ListensToEvents;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Domain\ValueObject\MessageTone;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Zamknięty słownik zdarzeń i trzy reguły publikacji (krok 46).
 *
 * Test pilnuje rzeczy, których w kodzie **nie widać z jednego miejsca**:
 * publikacja przy zerze odbiorców, odbiorca rzucający wyjątkiem i odbiorca
 * próbujący publikować w trakcie odbioru. Każda z nich zepsuta objawiłaby się
 * dopiero w działającej aplikacji — pierwsza ciszą, druga przerwaną czynnością
 * na plikach, trzecia zapętloną pętlą główną.
 */
final class EventRegistryTest extends TestCase
{
    /** Rejestr rodzi się ze słownikiem rdzenia — ten istnieje zawsze. */
    public function testTheCoreDictionaryIsThereFromTheStart(): void
    {
        $registry = new EventRegistry();
        $names = array_map(
            static fn (EventDeclaration $declaration): string => $declaration->name,
            $registry->all(),
        );

        self::assertSame(
            ['core.message.info', 'core.message.warning', 'core.message.error',
                'core.overlay.opened', 'core.command.executed'],
            $names,
        );
        self::assertTrue($registry->has('core.message.error'));
        self::assertFalse($registry->has('core.message.whatever'));
    }

    /** Ton komunikatu ma swoje zdarzenie — i to jedyne tłumaczenie w projekcie. */
    public function testEachMessageToneHasItsEvent(): void
    {
        self::assertSame(AppEvent::MessageInfo, AppEvent::ofTone(MessageTone::Info));
        self::assertSame(AppEvent::MessageWarning, AppEvent::ofTone(MessageTone::Warning));
        self::assertSame(AppEvent::MessageError, AppEvent::ofTone(MessageTone::Error));
    }

    /** Publikacja przy zerze odbiorców wygląda tak samo jak każda inna — czyli nijak. */
    public function testPublishingWithoutListenersDoesNothing(): void
    {
        $registry = new EventRegistry();
        $registry->publish(AppEvent::MessageError->value);

        self::assertTrue($registry->isEmpty());
    }

    public function testListenerHearsTheEvent(): void
    {
        $registry = new EventRegistry();
        $listener = new RecordingModule('one');
        $registry->useModules([$listener]);

        $registry->publish(AppEvent::CommandExecuted->value);

        self::assertSame(['core.command.executed'], $listener->heard);
    }

    /**
     * Odbiorca rzucający wyjątkiem **nie przerywa publikacji** i nie wypuszcza
     * wyjątku wyżej — bo wyżej jest cudza czynność, w środku której zdarzenie
     * padło.
     */
    public function testThrowingListenerNeitherStopsTheOthersNorEscapes(): void
    {
        $registry = new EventRegistry();
        $second = new RecordingModule('second');
        $registry->useModules([new ThrowingModule(), $second]);

        $registry->publish(AppEvent::MessageInfo->value);

        self::assertSame(['core.message.info'], $second->heard);
    }

    /** Zdarzenie nie rodzi zdarzenia — inaczej łańcuch zapętliłby pętlę główną. */
    public function testAnEventPublishedWhileListeningIsIgnored(): void
    {
        $registry = new EventRegistry();
        $listener = new RepublishingModule($registry);
        $registry->useModules([$listener]);

        $registry->publish(AppEvent::MessageError->value);

        self::assertSame(['core.message.error'], $listener->heard);
    }

    /** Co nie jest zadeklarowane, **nie jest publikowane** — obietnica zamkniętego zbioru. */
    public function testUndeclaredNamesNeverReachListeners(): void
    {
        $registry = new EventRegistry();
        $listener = new RecordingModule('one');
        $registry->useModules([$listener]);

        $registry->publish('core.message.whatever');

        self::assertSame([], $listener->heard);
    }

    /** Moduł wnosi zdarzenia **wyłącznie w swojej przestrzeni nazw**. */
    public function testModuleEventsOutsideItsNamespaceAreDropped(): void
    {
        $registry = new EventRegistry();
        $registry->useModules([new DeclaringModule('demo', [
            new EventDeclaration('demo.thing.done', 'module.demo.event.thing.done'),
            new EventDeclaration('core.message.error', 'module.demo.event.stolen'),
            new EventDeclaration('inny.thing.done', 'module.demo.event.foreign'),
            new EventDeclaration('demo.', 'module.demo.event.empty'),
        ])]);

        self::assertTrue($registry->has('demo.thing.done'));
        self::assertFalse($registry->has('inny.thing.done'));
        self::assertFalse($registry->has('demo.'));
        // Nazwa rdzenia zostaje **rdzeniowa**: moduł jej nie przejmuje, bo
        // deklaracja już istnieje.
        self::assertSame(
            'event.core.message.error',
            self::declarationOf($registry, 'core.message.error')->labelKey,
        );
    }

    private static function declarationOf(EventRegistry $registry, string $name): EventDeclaration
    {
        foreach ($registry->all() as $declaration) {
            if ($declaration->name === $name) {
                return $declaration;
            }
        }

        self::fail('Brak deklaracji ' . $name);
    }
}

/** Moduł-atrapa: tyle kontraktu, ile rejestr zdarzeń naprawdę ogląda. */
abstract class EventModule implements ModuleInterface
{
    public function __construct(
        private readonly string $id = 'demo',
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function nameKey(): string
    {
        return 'module.' . $this->id . '.name';
    }

    public function descriptionKey(): string
    {
        return 'module.' . $this->id . '.description';
    }

    public function shortcut(): ?ModuleShortcut
    {
        return null;
    }

    public function translations(): ?string
    {
        return null;
    }
}

final class RecordingModule extends EventModule implements ListensToEvents
{
    /** @var list<string> */
    public array $heard = [];

    public function onEvent(string $event): void
    {
        $this->heard[] = $event;
    }
}

final class ThrowingModule extends EventModule implements ListensToEvents
{
    public function onEvent(string $event): void
    {
        throw new RuntimeException('odbiorca zepsuty');
    }
}

final class RepublishingModule extends EventModule implements ListensToEvents
{
    /** @var list<string> */
    public array $heard = [];

    public function __construct(
        private readonly EventRegistry $registry,
    ) {
        parent::__construct('demo');
    }

    public function onEvent(string $event): void
    {
        $this->heard[] = $event;
        $this->registry->publish(AppEvent::MessageWarning->value);
    }
}

final class DeclaringModule extends EventModule implements DeclaresEvents
{
    /** @param list<EventDeclaration> $declarations */
    public function __construct(
        string $id,
        private readonly array $declarations,
    ) {
        parent::__construct($id);
    }

    public function events(): array
    {
        return $this->declarations;
    }
}
