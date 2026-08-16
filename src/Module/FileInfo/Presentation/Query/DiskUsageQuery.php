<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Module\FileInfo\Presentation\FileInfoState;

/**
 * `file-info.usage` — zajętość policzona przez `du` **wraz z etapem**.
 *
 * Wykonanie reguły nr 4 kwerendy na istniejącym precedensie (kroki 25 i 26):
 * **kwerenda oddaje stan pracy, a nie jej wynik po czekaniu**. Liczenie katalogu
 * domowego trwa minuty, a klatka trwa 33 ms — więc pytanie zadane w trakcie
 * odpowiada „liczę”, i to jest pełnoprawna odpowiedź, a nie brak odpowiedzi.
 *
 * Kwerenda **ulotna**: postęp zmienia się w każdym takcie, bo w każdym takcie
 * dogląda się potomka. Cena jest jedna: przepisanie czterech pól.
 */
final class DiskUsageQuery implements QueryInterface
{
    public function __construct(
        private readonly FileInfoState $state,
    ) {
    }

    public function name(): string
    {
        return FileInfoSettings::ID . '.usage';
    }

    public function descriptionKey(): string
    {
        return 'module.' . FileInfoSettings::ID . '.query.usage';
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
        $usage = $this->state->diskUsage();

        return QueryResult::owned(FileInfoSettings::ID, $usage, static fn (): array => [[
            'stage' => strtolower($usage->stage->name),
            'bytes' => $usage->bytes ?? -1,
            'problem' => $usage->problemKey ?? '',
        ]]);
    }
}
