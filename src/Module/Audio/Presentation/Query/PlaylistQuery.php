<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\PlaylistEntry;
use LightManager\Module\Audio\Application\PlaylistPlayer;

/**
 * `audio.playlist` — pozycje playlisty wraz z tym, której nie da się zagrać.
 *
 * **Właściciel dostaje obiekt, obcy — wiersze** (D92 nr 4). Okno modułu rysuje
 * listę z `Playlist`, bo zna ten typ i zna go od kroku 45; moduł spoza dźwięku
 * dostaje napisy i liczby, bo `Playlist` należy do cudzej dziedziny (reguła 15).
 * Jedna kwerenda odpowiada obu, a różnica jest w tym, kto się po nią zgłasza.
 *
 * Pokolenie bierze się z licznika zmian odtwarzacza, więc wiersze budują się po
 * dopisaniu, usunięciu, przestawieniu i odświeżeniu pozycji — a nie trzydzieści
 * razy na sekundę, kiedy okno modułu stoi otwarte.
 */
final class PlaylistQuery implements QueryInterface
{
    public function __construct(
        private readonly PlaylistPlayer $player,
    ) {
    }

    public function name(): string
    {
        return AudioSettings::ID . '.playlist';
    }

    public function descriptionKey(): string
    {
        return 'module.' . AudioSettings::ID . '.query.playlist';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return $this->player->revision();
    }

    public function ask(CommandInput $input): QueryResult
    {
        $playlist = $this->player->playlist();

        return QueryResult::owned(AudioSettings::ID, $playlist, static function () use ($playlist): array {
            $rows = [];
            $playing = $playlist->playing();

            foreach ($playlist->entries() as $index => $entry) {
                $rows[] = self::describe($index, $entry, $index === $playing);
            }

            return $rows;
        });
    }

    /** @return array<string, string|int|bool> */
    private static function describe(int $index, PlaylistEntry $entry, bool $playing): array
    {
        return [
            'index' => $index,
            'title' => $entry->name,
            'path' => $entry->path,
            'playable' => !$entry->missing,
            'playing' => $playing,
        ];
    }
}
