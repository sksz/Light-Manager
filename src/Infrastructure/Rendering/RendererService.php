<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Rendering;

use LightManager\Application\Port\FrameRendererPort;
use LightManager\Application\Ui\Frame;
use LightManager\Domain\ValueObject\RendererMode;
use LightManager\Infrastructure\Imagick\SixelFrameEncoder;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Infrastructure\Terminal\SixelCapabilityService;
use LightManager\Infrastructure\Terminal\TerminalService;
use LightManager\Infrastructure\Terminal\TerminalSizeService;

/**
 * Jedyne wejście do renderowania dla reszty aplikacji.
 *
 * Wybiera strategię raz, na podstawie trybu wykrytego w kroku 07, i przejmuje
 * ekran (alternatywny bufor) — to ten efekt uboczny konstruktora sprawia, że
 * usługa musi znaleźć się w jawnej sekwencji bootstrapu.
 *
 * Strategia jest wybrana raz, ale kolory i jakość rysowania — nie: od kroku 14
 * bierze je z konfiguracji każda klatka z osobna, więc zmiana motywu na ekranie
 * ustawień jest widoczna natychmiast.
 */
final class RendererService extends AbstractSingleton implements FrameRendererPort
{
    private readonly FrameRendererPort $renderer;

    private float $lastRenderMilliseconds = 0.0;

    protected function __construct()
    {
        parent::__construct();

        TerminalService::getInstance()->enterAlternateScreen();

        // Rozmiar okna musi być zmierzony tutaj, a nie przy pierwszym
        // renderowaniu: pytanie o piksele czeka na odpowiedź terminala do
        // 300 ms i doliczyłoby się do czasu pierwszej klatki, zniekształcając
        // pomiar, na którym opiera się model odświeżania z kroku 09.
        TerminalSizeService::getInstance();

        $this->renderer = SixelCapabilityService::getInstance()->detect() === RendererMode::Sixel
            ? new SixelFrameRenderer(new SixelFrameEncoder())
            : new TextFrameRenderer(AnsiPalette::fromEnvironment());
    }

    public function render(Frame $frame): void
    {
        $started = microtime(true);

        $this->renderer->render($frame);

        $this->lastRenderMilliseconds = (microtime(true) - $started) * 1000;
    }

    /** Czas ostatniej klatki — wejście do decyzji o modelu odświeżania w kroku 09. */
    public function lastRenderMilliseconds(): float
    {
        return $this->lastRenderMilliseconds;
    }
}
