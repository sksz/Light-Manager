<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Config;

/**
 * Który z kroków zapisu konfiguracji się nie udał.
 *
 * Zapis idzie przez katalog, treść i plik tymczasowy — a `SettingsService`
 * musi zamienić awarię na przetłumaczony opis, więc potrzebuje wiedzieć, o
 * który krok chodzi, nie tylko że coś padło.
 */
enum ConfigFailure
{
    case UnwritableDirectory;
    case UnwritableFile;
    case FailedEncoding;
}
