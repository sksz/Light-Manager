<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Terminal;

use LightManager\Application\Dto\KeyPress;

final class ParsedKey
{
    public function __construct(
        public readonly KeyPress $keyPress,
        public readonly int $consumedBytes,
    ) {
    }
}
