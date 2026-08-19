<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Ssh;

use LightManager\Application\Event\EventRegistry;
use LightManager\Application\Module\ListensToEvents;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Module\Ssh\Application\HostBook;
use LightManager\Module\Ssh\Application\SessionStage;
use LightManager\Module\Ssh\Application\SshEvent;
use LightManager\Module\Ssh\Application\SshSession;
use LightManager\Module\Ssh\Domain\ValueObject\AuthMethod;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Presentation\SshModule;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\StubHostBook;
use LightManager\Tests\Support\StubSshSession;
use PHPUnit\Framework\TestCase;

/**
 * Koordynator modułu: książka, ustawienia i zdarzenia (krok 48).
 *
 * **Ani jeden z tych testów nie otwiera połączenia** — port jest atrapą, a plik
 * książki mieszka w pamięci. To nie jest ostrożność testu, tylko reguła całej
 * Fazy XVII (D84, D87): test sięgający poza maszynę jest zakładem o cudzy
 * serwer, a nie testem.
 */
final class SshSessionTest extends TestCase
{
    public function testBookIsReadLazilyAndOnlyOnce(): void
    {
        $storage = new StubHostBook(new HostBook([new HostProfile('biuro', 'example.com')]));
        $session = $this->sessionWith(new StubSshSession(), $storage);

        self::assertSame(1, $session->book()->count());
        self::assertSame('biuro', $session->book()->names()[0]);
    }

    /** Dopisanie wpisu **zapisuje plik od razu** — spis ma przeżyć ponowne uruchomienie. */
    public function testAddingAHostPersistsTheBook(): void
    {
        $storage = new StubHostBook();
        $session = $this->sessionWith(new StubSshSession(), $storage);

        $session->add(new HostProfile('biuro', 'example.com'));

        self::assertSame(1, $storage->saves);
        self::assertSame(1, $session->book()->count());
    }

    public function testRemovingAHostThatIsNotThereChangesNothing(): void
    {
        $storage = new StubHostBook();
        $session = $this->sessionWith(new StubSshSession(), $storage);

        self::assertFalse($session->remove('nie ma'));
        self::assertSame(0, $storage->saves);
    }

    /** Ustawienia idą do portu **przy każdym łączeniu**, a nie raz przy składaniu. */
    public function testSettingsReachThePortOnEveryConnection(): void
    {
        $port = new StubSshSession();
        $session = $this->sessionWith($port, new StubHostBook());

        $session->connect(new HostProfile('biuro', 'example.com'));

        self::assertCount(1, $port->options);
        self::assertSame(10, $port->options[0]['timeout']);
        self::assertTrue($port->options[0]['remembers']);
    }

    public function testOnlyThePasswordMethodAsksForOne(): void
    {
        $session = $this->sessionWith(new StubSshSession(), new StubHostBook());

        self::assertFalse($session->needsPassword(new HostProfile('a', 'example.com')));
        self::assertFalse($session->needsPassword(
            new HostProfile('b', 'example.com', 22, '', AuthMethod::Key, '/klucz'),
        ));
        self::assertTrue($session->needsPassword(
            new HostProfile('c', 'example.com', 22, '', AuthMethod::Password),
        ));
    }

    /** Zdarzenie „połączono” pada **raz**, w takcie, z porównania etapów. */
    public function testConnectingAnnouncesItselfOnce(): void
    {
        $port = new StubSshSession();
        $listener = new RecordingListener();
        $session = $this->sessionWith($port, new StubHostBook(), $listener);
        $profile = new HostProfile('biuro', 'example.com');

        $session->connect($profile);
        $session->tick();
        self::assertSame([], $listener->events, 'łączenie jeszcze trwa');

        $port->settleConnected($profile);
        $session->tick();
        $session->tick();

        self::assertSame([SshEvent::Connected->value], $listener->events);
    }

    public function testFailureAnnouncesItself(): void
    {
        $port = new StubSshSession();
        $listener = new RecordingListener();
        $session = $this->sessionWith($port, new StubHostBook(), $listener);
        $profile = new HostProfile('biuro', 'example.com');

        $session->connect($profile);
        $port->settleFailed($profile);
        $session->tick();

        self::assertSame([SshEvent::Failed->value], $listener->events);
    }

    /**
     * Rozłączenie rozpoznaje się po **poprzednim** etapie.
     *
     * `Idle` jest zarazem stanem początkowym aplikacji, więc ogłaszanie
     * „rozłączono" przy starcie byłoby zdaniem o czymś, co się nie wydarzyło —
     * i ten test właśnie tego pilnuje.
     */
    public function testDisconnectingAnnouncesItselfButAFreshStartDoesNot(): void
    {
        $port = new StubSshSession();
        $listener = new RecordingListener();
        $session = $this->sessionWith($port, new StubHostBook(), $listener);
        $profile = new HostProfile('biuro', 'example.com');

        $session->tick();
        self::assertSame([], $listener->events, 'sam start niczego nie ogłasza');

        $session->connect($profile);
        $port->settleConnected($profile);
        $session->tick();
        $session->disconnect();
        $session->tick();

        self::assertSame([SshEvent::Connected->value, SshEvent::Disconnected->value], $listener->events);
    }

    /** Pytanie o odcisk **nie jest zdarzeniem**: to faza, która trwa, a nie rzecz, która się stała. */
    public function testAwaitingApprovalIsNotAnnounced(): void
    {
        $port = new StubSshSession();
        $listener = new RecordingListener();
        $session = $this->sessionWith($port, new StubHostBook(), $listener);
        $profile = new HostProfile('biuro', 'example.com');

        $session->connect($profile);
        $port->settleAwaitingApproval($profile);
        $session->tick();

        self::assertSame(SessionStage::AwaitingApproval, $session->state()->stage);
        self::assertSame([], $listener->events);
    }

    /** Moduł z podstawionym portem nie pyta o klienta — start testu nie zależy od maszyny. */
    public function testModuleWithAStubbedPortIsAlwaysAvailable(): void
    {
        $module = new SshModule(
            new \LightManager\Presentation\Cli\LoopState(),
            new \LightManager\Tests\Support\StubTranslator(),
            new InMemorySettings(),
            new StubSshSession(),
            new StubHostBook(),
        );

        self::assertNull($module->unavailableReason());
        self::assertSame('ssh', $module->id());
        self::assertEquals(new ModuleShortcut('s'), $module->shortcut());
    }

    /** Słownik zdarzeń powstaje z `cases()`, więc publikacja i spis nie mają jak się rozjechać. */
    public function testEventDictionaryComesFromTheEnum(): void
    {
        $names = array_map(
            static fn (\LightManager\Application\Event\EventDeclaration $d): string => $d->name,
            SshEvent::declarations(),
        );

        self::assertSame(
            ['ssh.connected', 'ssh.disconnected', 'ssh.failed', 'ssh.transfer.done', 'ssh.transfer.failed'],
            $names,
        );
    }

    private function sessionWith(
        StubSshSession $port,
        StubHostBook $storage,
        ?RecordingListener $listener = null,
    ): SshSession {
        $events = new EventRegistry();
        $events->declare('ssh', SshEvent::declarations());

        if ($listener !== null) {
            $events->useModules([$listener]);
        }

        return new SshSession($port, $storage, new InMemorySettings(), $events);
    }
}

/** Odbiorca zdarzeń, który wyłącznie je zapisuje — po to, żeby dało się je policzyć. */
final class RecordingListener implements ModuleInterface, ListensToEvents
{
    /** @var list<string> */
    public array $events = [];

    public function id(): string
    {
        return 'listener';
    }

    public function nameKey(): string
    {
        return 'listener.name';
    }

    public function descriptionKey(): string
    {
        return 'listener.description';
    }

    public function shortcut(): ?ModuleShortcut
    {
        return null;
    }

    public function translations(): ?string
    {
        return null;
    }

    public function onEvent(string $event): void
    {
        $this->events[] = $event;
    }
}
