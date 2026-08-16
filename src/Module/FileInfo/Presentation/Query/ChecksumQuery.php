<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Module\FileInfo\Presentation\FileInfoState;

/**
 * `file-info.digest` — `sha256` zaznaczonego pliku wraz z etapem liczenia.
 *
 * Druga kwerenda oddająca **stan pracy, nie wynik po czekaniu**. Ułamek jest tu
 * po to, żeby pytający umiał powiedzieć „liczę, 40%” — sam etap wystarcza do
 * rozstrzygnięcia, czy suma jest, ale nie wystarcza, żeby o niej opowiedzieć.
 */
final class ChecksumQuery implements QueryInterface
{
    public function __construct(
        private readonly FileInfoState $state,
    ) {
    }

    public function name(): string
    {
        return FileInfoSettings::ID . '.digest';
    }

    public function descriptionKey(): string
    {
        return 'module.' . FileInfoSettings::ID . '.query.digest';
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
        $checksum = $this->state->checksum();

        return QueryResult::owned(FileInfoSettings::ID, $checksum, static fn (): array => [[
            'stage' => strtolower($checksum->stage->name),
            'digest' => $checksum->digest ?? '',
            'fraction' => (int) round($checksum->fraction * 100),
            'problem' => $checksum->problemKey ?? '',
        ]]);
    }
}
