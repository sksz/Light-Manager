<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Module\FileInfo\Presentation\FileInfoState;

/**
 * `file-info.preview` — miniatura zaznaczonego wpisu albo powód, dla którego
 * jej nie ma.
 *
 * **Czego ta kwerenda nie obejmuje i dlaczego** — bo to jest jedyne miejsce
 * w tym kroku, gdzie źródło danych świadomie zostaje poza rejestrem. Podgląd
 * **tekstu** (`FileInfoState::textWindow()`) kwerendą nie jest, mimo że jest
 * daną do pokazania: jego odczyt **zmienia stan** — rozlicza zamówione
 * przewinięcie i przesuwa kotwicę w pliku (D60) — a reguła nr 1 mówi, że
 * kwerenda czyta i nie zmienia. Rzecz, która przy czytaniu przestawia to, co
 * czyta, jest negocjacją z geometrią panelu, a nie źródłem danych.
 *
 * Miniatura liczy się **leniwie i drogo** (Imagick albo dekoder tekstur), więc
 * `ask()` sięga po nią dopiero na żądanie — a pokolenie bierze z opisu, bo
 * zmienia się dokładnie wtedy, gdy zmienia się wpis.
 */
final class PreviewQuery implements QueryInterface
{
    public function __construct(
        private readonly FileInfoState $state,
    ) {
    }

    public function name(): string
    {
        return FileInfoSettings::ID . '.preview';
    }

    public function descriptionKey(): string
    {
        return 'module.' . FileInfoSettings::ID . '.query.preview';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return $this->state->revision();
    }

    public function ask(CommandInput $input): QueryResult
    {
        $preview = $this->state->preview();

        if ($preview === null) {
            return QueryResult::empty();
        }

        return QueryResult::owned(FileInfoSettings::ID, $preview, static fn (): array => [[
            'path' => $preview->path ?? '',
            'caption' => $preview->caption,
            'renderable' => $preview->isRenderable(),
        ]]);
    }
}
