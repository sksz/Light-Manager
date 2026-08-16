<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\NowPlaying;
use LightManager\Module\Audio\Application\PlaylistPlayer;

/**
 * `audio.now-playing` — co gra, w jakim trybie i czy jest czym grać.
 *
 * Kwerenda **ulotna**: granie kończy się w wątku miksującym, a nie w żadnej
 * metodzie tej aplikacji, więc pokolenia nie ma z czego zbudować — jedyną
 * uczciwą odpowiedzią jest „licz raz na klatkę”. Cena jest znana i mała:
 * odpowiedź to jedno pytanie do silnika i jedno do ustawień, czyli dokładnie
 * to, co okno modułu robiło do tego kroku samo, tyle że w czterech miejscach.
 */
final class NowPlayingQuery implements QueryInterface
{
    public function __construct(
        private readonly PlaylistPlayer $player,
    ) {
    }

    public function name(): string
    {
        return AudioSettings::ID . '.now-playing';
    }

    public function descriptionKey(): string
    {
        return 'module.' . AudioSettings::ID . '.query.now-playing';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return self::VOLATILE;
    }

    public function ask(CommandInput $input): QueryResult
    {
        $playlist = $this->player->playlist();
        $index = $playlist->playing();
        $playing = new NowPlaying(
            $index === null ? null : $playlist->at($index),
            $index,
            $this->player->isPlaying(),
            $this->player->mode(),
            $this->player->isAvailable(),
            $this->player->problem(),
        );

        $entry = $playing->entry;

        return QueryResult::owned(AudioSettings::ID, $playing, static fn (): array => [[
            'title' => $entry === null ? '' : $entry->name,
            'path' => $entry === null ? '' : $entry->path,
            'index' => $playing->index ?? -1,
            'playing' => $playing->playing,
            'mode' => $playing->mode->value,
            'available' => $playing->available,
            'problem' => $playing->problem ?? '',
        ]]);
    }
}
