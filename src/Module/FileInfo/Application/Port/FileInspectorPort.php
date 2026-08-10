<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\Port;

/**
 * Zewnętrzne narzędzie opisujące zawartość pliku.
 *
 * Port należy do modułu, a nie do rdzenia — moduł ma własną warstwę
 * `Infrastructure` i sięga do niej dokładnie tak samo, jak rdzeń do swojej:
 * przez interfejs opisany we własnym `Application`.
 */
interface FileInspectorPort
{
    /**
     * Krótki opis zawartości pliku. Nigdy nie rzuca — gdy opisu nie da się
     * ustalić, zwraca zdanie wyjaśniające dlaczego, bo i tak trafia ono wprost
     * na ekran pokazywany użytkownikowi.
     *
     * @param int    $timeoutSeconds po tylu sekundach polecenie zostaje przerwane
     * @param string $arguments      dodatkowe argumenty polecenia, tak jak wpisał
     *                               je użytkownik — rozbiór na słowa i cytowanie
     *                               należą do implementacji
     */
    public function describe(string $path, int $timeoutSeconds, string $arguments): string;
}
