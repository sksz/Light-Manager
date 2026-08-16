<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Presentation;

use LightManager\Application\Query\QueryRegistry;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\EffectMap;
use LightManager\Module\Audio\Application\NowPlaying;
use LightManager\Module\Audio\Application\PlaybackMode;
use LightManager\Module\Audio\Application\Playlist;

/**
 * Odczyt danych modułu dźwięku — **przez rejestr kwerend, jak każdy inny**
 * (krok 53, D92 nr 3).
 *
 * Wzorzec fasady powtarza się w każdym module i sprowadza się do jednego zdania:
 * *ekran nie zna stanu, zna pytanie*. Okno modułu nie trzyma już `PlaylistPlayer`
 * po to, żeby z niego czytać — pyta stąd, a tu pada `QueryRegistry::ask()`,
 * dokładnie ten sam, którym zapytałby moduł Kubernetesa.
 *
 * Rozpakowanie ładunku stoi w **jednym miejscu** i to jest cały powód, dla
 * którego ta klasa istnieje: `payloadFor()` oddaje `?object`, więc bez fasady
 * każde z kilkunastu miejsc odczytu musiałoby powtórzyć `instanceof`. Cudzy
 * ładunek wraca stąd jako `null` i wtedy fasada oddaje pustą odpowiedź — nie
 * rzuca, bo brak odpowiedzi jest zwykłym stanem (reguła 8).
 */
final readonly class AudioQueries
{
    public function __construct(
        private QueryRegistry $queries,
    ) {
    }

    public function playlist(): Playlist
    {
        $payload = $this->queries->ask(AudioSettings::ID . '.playlist')->payloadFor(AudioSettings::ID);

        return $payload instanceof Playlist ? $payload : new Playlist();
    }

    public function nowPlaying(): NowPlaying
    {
        $payload = $this->queries->ask(AudioSettings::ID . '.now-playing')->payloadFor(AudioSettings::ID);

        return $payload instanceof NowPlaying
            ? $payload
            : new NowPlaying(null, null, false, PlaybackMode::LoopList, false, null);
    }

    public function effects(): EffectMap
    {
        $payload = $this->queries->ask(AudioSettings::ID . '.effects')->payloadFor(AudioSettings::ID);

        return $payload instanceof EffectMap ? $payload : new EffectMap();
    }
}
