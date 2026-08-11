<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Domain\ValueObject;

enum EntryType
{
    case Directory;
    case File;
}
