<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Audio;

use LightManager\Application\Dto\Settings;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\EffectAssignment;
use LightManager\Module\Audio\Application\SoundEffects;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\StubAudio;
use LightManager\Tests\Support\StubEffectStorage;
use LightManager\Tests\Support\StubTrackFiles;
use PHPUnit\Framework\TestCase;

/**
 * Odbiorca zdarzeń: kiedy gra, a kiedy milczy (krok 46).
 *
 * **Żaden z tych testów nie uruchamia silnika audio** — reguła z kroku 36 zostaje
 * w mocy, a atrapa portu zapamiętuje prośby o granie zamiast je spełniać. Efekt
 * jedzie przy tym osobną listą niż muzyka i to nie jest wygoda testu: jedna
 * lista nie odróżniłaby dźwięku **dołożonego** od utworu podmienionego, a cała
 * rzecz polega właśnie na tym, że muzyka gra dalej.
 */
final class SoundEffectsTest extends TestCase
{
    private const EVENT = 'core.message.error';

    private const SOUND = 'assets/sfx/fail.mp3';

    private StubAudio $audio;

    private StubTrackFiles $files;

    protected function setUp(): void
    {
        $this->audio = new StubAudio();
        $this->files = new StubTrackFiles();
    }

    /**
     * Zdarzenie przed pierwszym taktem **milczy** — bo takt jest jedynym miejscem,
     * w którym wolno sięgnąć na dysk po mapę.
     */
    public function testAnEventBeforeTheFirstTickIsSilentAndTouchesNoStorage(): void
    {
        $storage = $this->storage();
        $effects = $this->effects($storage);

        $effects->onEvent(self::EVENT);

        self::assertSame([], $this->audio->effects);
        self::assertSame(0, $storage->loads, 'odbiór zdarzenia nie ma prawa czytać mapy');
    }

    /** Po takcie przypisane zdarzenie gra — **głośnością efektów**, nie muzyki. */
    public function testAssignedEventPlaysWithTheEffectsVolume(): void
    {
        $effects = $this->effects($this->storage(), volume: 30);
        $effects->useTime(10.0);

        $effects->onEvent(self::EVENT);

        self::assertSame([['path' => self::SOUND, 'volume' => 30]], $this->audio->effects);
    }

    /**
     * Efekt **nie rusza muzyki**: nie zatrzymuje jej, nie podmienia i nie zmienia
     * odpowiedzi na pytanie „czy coś gra”.
     */
    public function testAnEffectDoesNotDisturbTheMusic(): void
    {
        $this->audio->play('/muzyka/utwor.mp3', 50, false);
        $effects = $this->effects($this->storage());
        $effects->useTime(10.0);

        $effects->onEvent(self::EVENT);

        self::assertSame(0, $this->audio->stopCount, 'efekt nie zatrzymuje utworu');
        self::assertCount(1, $this->audio->played, 'efekt nie wchodzi na miejsce utworu');
        self::assertTrue($this->audio->isPlaying(), 'muzyka gra dalej');
    }

    /** Zdarzenie bez przypisania to cisza — i to jest cały wybór użytkownika. */
    public function testAnEventWithoutAnAssignmentIsSilent(): void
    {
        $effects = $this->effects(new StubEffectStorage());
        $effects->useTime(10.0);

        $effects->onEvent(self::EVENT);

        self::assertSame([], $this->audio->effects);
    }

    /** Wyciszone przypisanie **zostaje w mapie**, ale milczy. */
    public function testAMutedAssignmentIsSilent(): void
    {
        $storage = new StubEffectStorage([self::EVENT => new EffectAssignment(self::SOUND, enabled: false)]);
        $effects = $this->effects($storage);
        $effects->useTime(10.0);

        $effects->onEvent(self::EVENT);

        self::assertSame([], $this->audio->effects);
        self::assertNotNull($effects->map()->at(self::EVENT), 'plik zostaje przypisany');
    }

    /** Plik, którego nie ma, milczy — sprawdzone przy wczytaniu, nie przy zdarzeniu. */
    public function testAMissingFileIsSilent(): void
    {
        $files = new StubTrackFiles(missing: [self::SOUND]);
        $effects = new SoundEffects($this->audio, $this->storage(), $files, $this->settings());
        $effects->useTime(10.0);

        $effects->onEvent(self::EVENT);

        self::assertSame([], $this->audio->effects);
    }

    /**
     * To samo zdarzenie odpalone dwa razy w jednej klatce gra **raz** — inaczej
     * trzymana strzałka zamieniłaby klik w warkot.
     */
    public function testTheSameEventIsSilencedWithinTheMinimumInterval(): void
    {
        $effects = $this->effects($this->storage());
        $effects->useTime(10.0);

        $effects->onEvent(self::EVENT);
        $effects->onEvent(self::EVENT);
        $effects->useTime(10.05);
        $effects->onEvent(self::EVENT);

        self::assertCount(1, $this->audio->effects);

        $effects->useTime(10.2);
        $effects->onEvent(self::EVENT);

        self::assertCount(2, $this->audio->effects, 'po odstępie gra znowu');
    }

    /** Próg jest **na zdarzenie**: usunięcie pliku tuż po ruchu kursora ma zagrać. */
    public function testTheIntervalIsPerEventNotPerPlayer(): void
    {
        $storage = new StubEffectStorage([
            self::EVENT => new EffectAssignment(self::SOUND),
            'browser.cursor.moved' => new EffectAssignment('assets/sfx/click.wav'),
        ]);
        $effects = new SoundEffects($this->audio, $storage, $this->files, $this->settings());
        $effects->useTime(10.0);

        $effects->onEvent('browser.cursor.moved');
        $effects->onEvent(self::EVENT);

        self::assertCount(2, $this->audio->effects);
    }

    /**
     * Przełącznik ucisza **wszystko naraz** i nie dotyka przy tym mapy ani dysku
     * — to jest kryterium ukończenia kroku, zapisane testem.
     */
    public function testTheSwitchSilencesEverythingAndReadsNothing(): void
    {
        $storage = $this->storage();
        $effects = new SoundEffects($this->audio, $storage, $this->files, $this->settings(enabled: false));

        $effects->useTime(10.0);
        $effects->onEvent(self::EVENT);

        self::assertSame([], $this->audio->effects);
        self::assertSame(0, $storage->loads, 'wyłączone efekty nie czytają mapy z dysku');
    }

    /** Przypisanie, wyciszenie i zabranie pliku **zapisują mapę** za każdym razem. */
    public function testEveryChangeToTheMapIsSaved(): void
    {
        $storage = new StubEffectStorage();
        $effects = new SoundEffects($this->audio, $storage, $this->files, $this->settings());

        self::assertTrue($effects->assign(self::EVENT, self::SOUND));
        self::assertTrue($effects->toggle(self::EVENT));
        self::assertTrue($effects->clear(self::EVENT));

        self::assertCount(3, $storage->saved);
        self::assertSame([self::EVENT => self::SOUND], $storage->saved[0]);
        self::assertSame([], $storage->saved[2]);
    }

    /** Pusta ścieżka nie jest przypisaniem; zdarzenie bez przypisania nie ma czego zabrać. */
    public function testEmptyPathIsRefusedAndClearingNothingIsFalse(): void
    {
        $effects = $this->effects(new StubEffectStorage());

        self::assertFalse($effects->assign(self::EVENT, '   '));
        self::assertFalse($effects->clear(self::EVENT));
        self::assertFalse($effects->toggle(self::EVENT));
    }

    /**
     * Wyciszenie **zostawia plik**, a zabranie go usuwa — różnica, dla której
     * spacja i `F8` są dwoma różnymi klawiszami.
     */
    public function testMutingKeepsThePathWhileClearingDropsIt(): void
    {
        $effects = $this->effects($this->storage());
        $effects->toggle(self::EVENT);
        $assignment = $effects->map()->at(self::EVENT);

        self::assertNotNull($assignment);
        self::assertSame(self::SOUND, $assignment->path);
        self::assertFalse($assignment->enabled);

        $effects->clear(self::EVENT);

        self::assertNull($effects->map()->at(self::EVENT));
    }

    private function storage(): StubEffectStorage
    {
        return new StubEffectStorage([self::EVENT => new EffectAssignment(self::SOUND)]);
    }

    private function effects(StubEffectStorage $storage, int $volume = AudioSettings::DEFAULT_EFFECTS_VOLUME): SoundEffects
    {
        return new SoundEffects($this->audio, $storage, $this->files, $this->settings(volume: $volume));
    }

    private function settings(bool $enabled = true, int $volume = AudioSettings::DEFAULT_EFFECTS_VOLUME): InMemorySettings
    {
        return new InMemorySettings(new Settings(modules: [
            AudioSettings::ID => [
                AudioSettings::EFFECTS => $enabled,
                AudioSettings::EFFECTS_VOLUME => $volume,
            ],
        ]));
    }
}
