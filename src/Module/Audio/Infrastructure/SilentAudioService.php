<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Infrastructure;

use LightManager\Infrastructure\I18n\TranslatorService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\Port\AudioPort;

/**
 * Cisza — implementacja portu dla środowisk bez rozszerzenia `glfw` (krok 36).
 *
 * Istnieje po to, żeby **brak rozszerzenia nie był rozgałęzieniem w kodzie
 * komend**: bez niej każda z nich musiałaby zaczynać się od pytania „a czy
 * w ogóle mamy czym zagrać”, a to pytanie ma jedno miejsce — wybór implementacji
 * przy składaniu modułu. Ta sama zasada, którą tor okienkowy traktuje brak
 * `ext-glfw` od kroku 34: możliwość, nie wymóg.
 *
 * Klasa **odpowiada zdaniem, a nie milczeniem**: `play()` oddaje powód, więc
 * użytkownik dowiaduje się, czego brakuje, zamiast patrzeć na komendę, która
 * niby się wykonała.
 */
final class SilentAudioService extends AbstractSingleton implements AudioPort
{
    public function isAvailable(): bool
    {
        return false;
    }

    /**
     * Typ wyniku jest tu **węższy niż w porcie** (`string` zamiast `?string`)
     * i to nie jest przypadek: cisza nigdy się nie udaje, więc powód wraca
     * zawsze. PHP na zawężenie wyniku pozwala, a czytający widzi zachowanie
     * klasy w jej sygnaturze.
     */
    public function play(string $path, int $volume, bool $loop): string
    {
        return TranslatorService::getInstance()->translate(
            'module.' . AudioSettings::ID . '.problem.unavailable',
        );
    }

    /** Cisza gra efekt tak samo, jak gra muzykę — czyli mówi, czego brakuje. */
    public function playEffect(string $path, int $volume): string
    {
        return TranslatorService::getInstance()->translate(
            'module.' . AudioSettings::ID . '.problem.unavailable',
        );
    }

    public function stop(): void
    {
    }

    public function isPlaying(): bool
    {
        return false;
    }

    public function useVolume(int $volume): void
    {
    }

    public function shutdown(): void
    {
    }
}
