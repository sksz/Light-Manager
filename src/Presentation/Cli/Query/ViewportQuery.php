<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Port\ViewportPort;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Domain\ValueObject\RendererMode;

/**
 * `core.viewport` — ile jest miejsca i czym jest rysowane.
 *
 * Rozmiar nie jest stałą uruchomienia od kroku 33, a tor klatki wybiera się przy
 * starcie (Sixel, tekst, okno OpenGL) — jedno i drugie jest daną, o którą moduł
 * dotąd nie miał jak zapytać, choć obie zmieniają to, co ma sens narysować.
 *
 * Pokolenie **liczy się z rozmiaru**, a nie jest ulotne: siatka zmienia się przy
 * `SIGWINCH`, czyli rzadko, a spakowanie dwóch liczb w jedną kosztuje mnożenie.
 * Kwerenda ulotna przeliczałaby się co klatkę bez powodu.
 */
final class ViewportQuery implements QueryInterface
{
    /** Mnożnik pakujący dwie liczby w jedno pokolenie — z zapasem ponad każdy realny terminal. */
    private const COLUMNS_PER_GENERATION = 100000;

    public function __construct(
        private readonly ViewportPort $viewport,
        private readonly RendererMode $mode,
    ) {
    }

    public function name(): string
    {
        return 'core.viewport';
    }

    public function descriptionKey(): string
    {
        return 'query.core.viewport';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return $this->viewport->rows() * self::COLUMNS_PER_GENERATION + $this->viewport->columns();
    }

    public function ask(CommandInput $input): QueryResult
    {
        return QueryResult::of([[
            'rows' => $this->viewport->rows(),
            'columns' => $this->viewport->columns(),
            'renderer' => $this->mode->name,
            'windowed' => $this->mode === RendererMode::OpenGl,
        ]]);
    }
}
