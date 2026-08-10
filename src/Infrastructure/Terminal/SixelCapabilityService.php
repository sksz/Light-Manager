<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Terminal;

use LightManager\Application\Port\RendererModeDetectorPort;
use LightManager\Domain\ValueObject\RendererMode;
use LightManager\Infrastructure\Imagick\ImagickCapabilityService;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Ustala tryb renderowania przy starcie aplikacji.
 *
 * Tryb Sixel wymaga dwóch niezależnych warunków: terminal musi zgłosić obsługę
 * Sixela w odpowiedzi na zapytanie DA1, a lokalny ImageMagick musi mieć
 * wkompilowany koder `SIXEL`. Niespełnienie któregokolwiek oznacza fallback
 * tekstowy.
 */
final class SixelCapabilityService extends AbstractSingleton implements RendererModeDetectorPort
{
    /** Primary Device Attributes — prośba o listę możliwości terminala. */
    private const DEVICE_ATTRIBUTES_QUERY = "\e[c";

    /**
     * Kompromis między fałszywym negatywem na wolnym łączu (np. SSH) a
     * odczuwalnym opóźnieniem startu na terminalu, który nigdy nie odpowie.
     */
    private const RESPONSE_TIMEOUT_MICROSECONDS = 300000;

    private const POLL_INTERVAL_MICROSECONDS = 5000;

    private readonly RendererMode $mode;

    protected function __construct()
    {
        parent::__construct();

        $this->mode = $this->detectMode();
    }

    public function detect(): RendererMode
    {
        return $this->mode;
    }

    private function detectMode(): RendererMode
    {
        // Brak kodera przesądza sprawę bez zaczepiania terminala — nie ma po co
        // wysyłać zapytania, którego wynik i tak niczego nie zmieni.
        if (!ImagickCapabilityService::getInstance()->supportsSixelCoder()) {
            return RendererMode::TextFallback;
        }

        return $this->terminalSupportsSixel()
            ? RendererMode::Sixel
            : RendererMode::TextFallback;
    }

    private function terminalSupportsSixel(): bool
    {
        $terminal = TerminalService::getInstance();
        $parser = new DeviceAttributesParser();

        $terminal->write(self::DEVICE_ATTRIBUTES_QUERY);

        $deadline = microtime(true) + self::RESPONSE_TIMEOUT_MICROSECONDS / 1000000;
        $response = '';

        while (microtime(true) < $deadline) {
            $response .= $terminal->readRawBytes(self::POLL_INTERVAL_MICROSECONDS);

            if ($parser->isComplete($response)) {
                $terminal->pushBackBytes($parser->strip($response));

                return $parser->supportsSixel($response);
            }
        }

        // Milczenie terminala traktujemy jak brak wsparcia — multipleksery
        // (tmux, screen) potrafią odpowiedź DA1 odfiltrować.
        return false;
    }
}
