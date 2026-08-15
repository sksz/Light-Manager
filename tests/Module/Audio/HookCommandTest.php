<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Audio;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandRegistry;
use LightManager\Application\Command\SuggestionSource;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Event\EventRegistry;
use LightManager\Domain\ValueObject\MessageTone;
use LightManager\Module\Audio\Application\EffectAssignment;
use LightManager\Module\Audio\Application\SoundEffects;
use LightManager\Module\Audio\Presentation\Command\HookCommand;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\StubAudio;
use LightManager\Tests\Support\StubEffectStorage;
use LightManager\Tests\Support\StubTrackFiles;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * `audio.hook <zdarzenie> <ścieżka>` — trzecia droga do mapy przypisań
 * (krok 46).
 *
 * Komenda jest **pierwszą w projekcie z dwoma argumentami podpowiadanymi**, więc
 * test pilnuje przede wszystkim tego: każdy argument bierze podpowiedzi ze swojego
 * źródła, a nie oba z jednego.
 */
final class HookCommandTest extends TestCase
{
    private const SOUND = 'assets/sfx/fail.mp3';

    private StubEffectStorage $storage;

    private HookCommand $command;

    protected function setUp(): void
    {
        $this->storage = new StubEffectStorage();
        $settings = new InMemorySettings(new Settings());

        $this->command = new HookCommand(
            new SoundEffects(
                new StubAudio(),
                $this->storage,
                new StubTrackFiles(hints: ['assets/sfx/fail.mp3', 'assets/sfx/klik.wav']),
                $settings,
            ),
            new EventRegistry(),
            new StubTrackFiles(hints: ['assets/sfx/fail.mp3', 'assets/sfx/klik.wav']),
            new StubTranslator(),
        );
    }

    /** Nazwa mieści się w przestrzeni modułu — tego pilnuje rejestr komend. */
    public function testTheCommandLivesInTheModuleNamespace(): void
    {
        $registry = new CommandRegistry();
        $registry->add('audio', [$this->command]);

        self::assertSame([], $registry->rejections());
        self::assertNotNull($registry->find('audio.hook'));
    }

    /** Pierwszy argument podpowiada **zdarzenia**, drugi — pliki z dysku. */
    public function testEachArgumentTakesItsSuggestionsFromItsOwnSource(): void
    {
        self::assertSame(
            ['core.message.info', 'core.message.warning', 'core.message.error'],
            $this->command->suggestions('event', 'core.message.'),
        );
        self::assertSame(
            ['assets/sfx/fail.mp3', 'assets/sfx/klik.wav'],
            $this->command->suggestions('path', 'assets/'),
        );

        foreach ($this->command->arguments() as $argument) {
            self::assertSame(SuggestionSource::OnDemand, $argument->suggestions);
        }
    }

    /** Ścieżka podana wprost przypisuje się do zdarzenia i zapisuje. */
    public function testAssigningWritesTheMap(): void
    {
        $outcome = $this->command->execute(new CommandInput([
            'event' => 'core.message.error',
            'path' => self::SOUND,
        ]));

        self::assertSame(MessageTone::Info, $outcome->message?->tone);
        self::assertSame([['core.message.error' => self::SOUND]], $this->storage->saved);
    }

    /**
     * Ścieżka pusta **zabiera przypisanie** — jedyna droga do wyczyszczenia mapy
     * bez otwierania okna.
     */
    public function testAnEmptyPathTakesTheAssignmentAway(): void
    {
        $storage = new StubEffectStorage(['core.message.error' => new EffectAssignment(self::SOUND)]);
        $command = new HookCommand(
            new SoundEffects(new StubAudio(), $storage, new StubTrackFiles(), new InMemorySettings(new Settings())),
            new EventRegistry(),
            new StubTrackFiles(),
            new StubTranslator(),
        );

        $command->execute(new CommandInput(['event' => 'core.message.error']));

        self::assertSame([[]], $storage->saved);
    }

    /** Nazwa spoza słownika kończy się zdaniem, a nie cichym przypisaniem donikąd. */
    public function testAnUnknownEventIsRefused(): void
    {
        $outcome = $this->command->execute(new CommandInput([
            'event' => 'core.message.whatever',
            'path' => self::SOUND,
        ]));

        self::assertSame(MessageTone::Error, $outcome->message?->tone);
        self::assertSame([], $this->storage->saved);
    }
}
