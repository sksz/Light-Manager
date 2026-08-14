<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Wybór sposobu zrzutu obrazu dla toru, którym idzie klatka (krok 38).
 *
 * Jedno miejsce, w którym stoi ta decyzja — żeby `Bootstrap` nie musiał znać
 * trzech klas, a dopisanie czwartego toru nie kosztowało poprawki w warstwie
 * dostarczania.
 */
final class TrackImageGrabbers
{
    private function __construct()
    {
    }

    public static function forTrack(BenchmarkTrack $track): FrameImageGrabber
    {
        return match ($track) {
            BenchmarkTrack::Window => new WindowFrameGrabber(),
            BenchmarkTrack::Text => new TextFrameGrabber(),
            // Tor taktu pętli nie rysuje obrazu w ogóle i w żywej aplikacji nie
            // istnieje — zrzut z niego zamawia wyłącznie pomyłka w wołającym.
            default => new SixelFrameGrabber(),
        };
    }
}
