<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Application\UseCase;

use LightManager\Module\Browser\Domain\Aggregate\Directory;

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
