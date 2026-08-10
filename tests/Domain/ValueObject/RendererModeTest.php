<?php

declare(strict_types=1);

namespace LightManager\Tests\Domain\ValueObject;

use LightManager\Domain\ValueObject\RendererMode;
use PHPUnit\Framework\TestCase;

final class RendererModeTest extends TestCase
{
    public function testOffersExactlyTwoModes(): void
    {
        self::assertSame(
            [RendererMode::Sixel, RendererMode::TextFallback],
            RendererMode::cases(),
        );
    }

    public function testCasesAreComparableByIdentity(): void
    {
        self::assertSame(RendererMode::Sixel, RendererMode::Sixel);
        self::assertNotSame(RendererMode::Sixel, RendererMode::TextFallback);
    }
}
