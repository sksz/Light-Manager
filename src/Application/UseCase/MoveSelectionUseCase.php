<?php

declare(strict_types=1);

namespace LightManager\Application\UseCase;

use LightManager\Domain\Aggregate\Directory;

final class MoveSelectionUseCase
{
    public function up(Directory $directory): void
    {
        $directory->moveSelectionUp();
    }

    public function down(Directory $directory): void
    {
        $directory->moveSelectionDown();
    }
}
