<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/** Etap pakowania kontekstu budowy (krok 51). */
enum PackStage
{
    case Idle;
    case Packing;

    /** Archiwum gotowe — jest co wysłać demonowi. */
    case Packed;

    case Failed;
}
