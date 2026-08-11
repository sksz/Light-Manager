<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\Dto;

/**
 * W którym miejscu jest liczenie sumy kontrolnej.
 *
 * Cztery stany, bo tyle widzi użytkownik: nie zaczęto, trwa, gotowe, nie da się.
 * „Nie da się” obejmuje zarówno plik nie do odczytania, jak i plik większy od
 * ustawionego limitu — obie sytuacje kończą się zdaniem mówiącym **dlaczego**,
 * a nie pustym wierszem.
 */
enum ChecksumStage
{
    case Idle;
    case Running;
    case Done;
    case Failed;
}
