<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Application\Module\NeedsTick;
use LightManager\Domain\Exception\DomainException;

/**
 * Moduł, który prosi o takt — atrapa mechanizmu z kroku 45.
 *
 * Zapamiętuje **czasy**, a nie samą liczbę uderzeń, bo najważniejsza rzecz do
 * sprawdzenia w takcie to skąd bierze się zegar: z zewnątrz, a nie z
 * `microtime()` w środku modułu (reguła 11b).
 *
 * `$fails` udaje moduł zepsuty. Wyjątek w takcie nie ma prawa przerwać pętli,
 * a sprawdzić się to da wyłącznie modułem, który naprawdę rzuca — prawdziwy
 * moduł trzeba by w tym celu na chwilę popsuć.
 */
final class TickingModule implements ModuleInterface, NeedsTick
{
    /** @var list<float> czasy klatek, w których moduł dostał takt */
    public array $ticks = [];

    public function __construct(
        private readonly string $id = 'takt',
        private readonly bool $fails = false,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function nameKey(): string
    {
        return 'module.' . $this->id . '.name';
    }

    public function descriptionKey(): string
    {
        return 'module.' . $this->id . '.description';
    }

    public function shortcut(): ?ModuleShortcut
    {
        return null;
    }

    public function translations(): ?string
    {
        return null;
    }

    public function tick(float $now): void
    {
        $this->ticks[] = $now;

        if ($this->fails) {
            throw new class ('Module tick failed.') extends DomainException {};
        }
    }
}
