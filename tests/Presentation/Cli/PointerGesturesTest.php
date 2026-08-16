<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Cli;

use LightManager\Application\Dto\PointerButton;
use LightManager\Application\Dto\PointerEvent;
use LightManager\Presentation\Cli\PointerGestures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Rozpoznanie pary kliknięć — jedyna rzecz kroku 55 pytająca o czas. */
final class PointerGesturesTest extends TestCase
{
    private const NOW = 1000.0;

    private PointerGestures $gestures;

    protected function setUp(): void
    {
        $this->gestures = new PointerGestures();
    }

    public function testTheFirstClickIsNeverAPair(): void
    {
        self::assertFalse($this->gestures->isDoubleClick(PointerEvent::press(3, 7), self::NOW));
    }

    public function testTwoQuickClicksInTheSameCellMakeAPair(): void
    {
        $this->gestures->isDoubleClick(PointerEvent::press(3, 7), self::NOW);

        self::assertTrue($this->gestures->isDoubleClick(PointerEvent::press(3, 7), self::NOW + 0.2));
    }

    public function testTheThresholdIsFourHundredMilliseconds(): void
    {
        $this->gestures->isDoubleClick(PointerEvent::press(3, 7), self::NOW);

        self::assertTrue($this->gestures->isDoubleClick(PointerEvent::press(3, 7), self::NOW + 0.4));
    }

    public function testASlowerSecondClickIsASeparateClick(): void
    {
        $this->gestures->isDoubleClick(PointerEvent::press(3, 7), self::NOW);

        self::assertFalse($this->gestures->isDoubleClick(PointerEvent::press(3, 7), self::NOW + 0.41));
    }

    /**
     * Ten sam czas, inna komórka — dwa kliknięcia. Bez tego warunku szybko
     * klikający użytkownik wchodziłby do katalogów, których nie wybrał.
     */
    public function testADifferentCellIsASeparateClick(): void
    {
        $this->gestures->isDoubleClick(PointerEvent::press(3, 7), self::NOW);

        self::assertFalse($this->gestures->isDoubleClick(PointerEvent::press(4, 7), self::NOW + 0.1));
    }

    /** Trzecie kliknięcie **nie jest** drugim podwójnym: para, która się domknęła, gasi pamięć. */
    public function testATripleClickIsOnlyOnePair(): void
    {
        $this->gestures->isDoubleClick(PointerEvent::press(3, 7), self::NOW);
        $this->gestures->isDoubleClick(PointerEvent::press(3, 7), self::NOW + 0.1);

        self::assertFalse($this->gestures->isDoubleClick(PointerEvent::press(3, 7), self::NOW + 0.2));
    }

    /** @return array<string, array{PointerEvent}> */
    public static function ignoredEvents(): array
    {
        return [
            'zwolnienie' => [PointerEvent::release(3, 7)],
            'przeciągnięcie' => [PointerEvent::drag(3, 7)],
            'kółko' => [PointerEvent::scroll(3, 7, true)],
            'prawy przycisk' => [PointerEvent::press(3, 7, PointerButton::Right)],
            'środkowy przycisk' => [PointerEvent::press(3, 7, PointerButton::Middle)],
        ];
    }

    /**
     * Para powstaje **wyłącznie** z naciśnięć lewym przyciskiem — i zdarzenia
     * innego rodzaju pamięci nie ruszają.
     */
    #[DataProvider('ignoredEvents')]
    public function testOnlyLeftPressesCount(PointerEvent $event): void
    {
        $this->gestures->isDoubleClick(PointerEvent::press(3, 7), self::NOW);

        self::assertFalse($this->gestures->isDoubleClick($event, self::NOW + 0.1));
        self::assertTrue(
            $this->gestures->isDoubleClick(PointerEvent::press(3, 7), self::NOW + 0.2),
            'zdarzenie obcego rodzaju nie zerwało pary',
        );
    }
}
