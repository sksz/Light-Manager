<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\SoundEffects;

/**
 * `audio.effects` — mapa „zdarzenie → plik” wraz z tym, czy wolno jej zagrać.
 *
 * Trzecia kwerenda modułu i jedyna, która mówi o **cudzych** zdarzeniach: klucze
 * mapy pochodzą ze słownika rdzenia i modułów (krok 46), a moduł dźwięku nie zna
 * ani jednej nazwy z nich. Odpowiedź zestawia więc dwie rzeczy, których nigdzie
 * indziej nie widać razem — co jest zadeklarowane i co z tego ma dźwięk.
 */
final class EffectsQuery implements QueryInterface
{
    public function __construct(
        private readonly SoundEffects $effects,
    ) {
    }

    public function name(): string
    {
        return AudioSettings::ID . '.effects';
    }

    public function descriptionKey(): string
    {
        return 'module.' . AudioSettings::ID . '.query.effects';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return $this->effects->revision();
    }

    public function ask(CommandInput $input): QueryResult
    {
        $effects = $this->effects;
        $map = $effects->map();

        return QueryResult::owned(AudioSettings::ID, $map, static function () use ($effects, $map): array {
            $rows = [];
            $enabled = $effects->enabled();

            foreach ($map->all() as $event => $assignment) {
                $rows[] = [
                    'event' => $event,
                    'path' => $assignment->path,
                    'enabled' => $assignment->enabled,
                    'playable' => $enabled && $assignment->playable(),
                ];
            }

            return $rows;
        });
    }
}
