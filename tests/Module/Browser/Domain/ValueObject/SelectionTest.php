<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Browser\Domain\ValueObject;

use LightManager\Module\Browser\Domain\Exception\InvalidSelectionException;
use LightManager\Module\Browser\Domain\ValueObject\Selection;
use PHPUnit\Framework\TestCase;

final class SelectionTest extends TestCase
{
    public function testKeepsIndex(): void
    {
        self::assertSame(3, (new Selection(3))->index);
    }

    public function testAllowsFirstPosition(): void
    {
        self::assertSame(0, (new Selection(0))->index);
    }

    public function testRejectsNegativeIndex(): void
    {
        $this->expectException(InvalidSelectionException::class);

        new Selection(-1);
    }

    public function testComparesByValue(): void
    {
        self::assertTrue((new Selection(2))->equals(new Selection(2)));
        self::assertFalse((new Selection(2))->equals(new Selection(3)));
    }
}
