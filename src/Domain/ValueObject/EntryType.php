<?php

declare(strict_types=1);

namespace LightManager\Domain\ValueObject;

enum EntryType
{
    case Directory;
    case File;
}
