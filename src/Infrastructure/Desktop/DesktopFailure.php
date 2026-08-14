<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Desktop;

/**
 * Który krok zakładania wpisu pulpitu się nie udał.
 *
 * Wzorem `ConfigFailure`: zapis idzie przez katalogi i pliki, a napis dla
 * użytkownika składa się z powodu i ze ścieżki, więc wołający musi wiedzieć,
 * o który krok chodzi, a nie tylko że coś padło.
 */
enum DesktopFailure
{
    case UnwritableDirectory;
    case UnwritableFile;
}
