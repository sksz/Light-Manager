<?php

declare(strict_types=1);

namespace LightManager\Application\Port;

use LightManager\Application\Dto\Language;

/**
 * Katalog napisów widocznych dla użytkownika.
 *
 * Port jest jedyną drogą, którą `Application` sięga po tekst — sama warstwa nie
 * zna ani plików katalogu, ani sposobu wyboru języka. Nigdy nie rzuca: brakujący
 * klucz wraca jako własna nazwa, bo napis, którego nie przetłumaczono, nie ma
 * prawa przerwać rysowania klatki.
 *
 * Formatowanie liczb należy tu, a nie do osobnego portu: separator dziesiętny
 * jest elementem języka dokładnie tak samo jak słowo.
 */
interface TranslatorPort
{
    /**
     * Napis spod klucza, z parametrami podstawionymi w miejsce `{nazwa}`.
     *
     * @param array<string, string|int|float> $parameters
     */
    public function translate(string $key, array $parameters = []): string;

    /**
     * Napis w formie odpowiedniej dla `$count`. Sama liczba trafia do podstawień
     * pod nazwą `{count}`, więc nie trzeba jej przekazywać dwa razy.
     *
     * @param array<string, string|int|float> $parameters
     */
    public function plural(string $key, int $count, array $parameters = []): string;

    /** Liczba w zapisie obowiązującym w bieżącym języku (separator dziesiętny). */
    public function number(float $value, int $decimals = 0): string;

    /** Język obowiązujący w tej chwili — rozstrzygnięty, więc nigdy `Auto`. */
    public function active(): Language;
}
