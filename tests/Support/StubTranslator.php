<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Dto\Language;
use LightManager\Application\Port\TranslatorPort;

/**
 * Tłumacz na potrzeby testów warstwy `Application`.
 *
 * Nie tłumaczy — oddaje klucz wraz z parametrami. Dzięki temu test sprawdza to,
 * co naprawdę należy do przypadku użycia (*którego* napisu żąda i z jakimi
 * danymi), a nie brzmienie zdania w katalogu; zmiana treści napisu nie psuje
 * wtedy dwudziestu asercji. Kompletność samych katalogów pilnuje osobny test
 * `TranslatorServiceTest`.
 */
final class StubTranslator implements TranslatorPort
{
    public function __construct(
        private readonly Language $language = Language::English,
    ) {
    }

    public function translate(string $key, array $parameters = []): string
    {
        return $parameters === [] ? $key : $key . '(' . $this->describe($parameters) . ')';
    }

    public function plural(string $key, int $count, array $parameters = []): string
    {
        $parameters['count'] = $count;

        return $key . '(' . $this->describe($parameters) . ')';
    }

    /** Kropka dziesiętna i żadnych separatorów — postać, którą łatwo sprawdzić. */
    public function number(float $value, int $decimals = 0): string
    {
        return number_format($value, $decimals, '.', '');
    }

    public function active(): Language
    {
        return $this->language;
    }

    /** @param array<string, string|int|float> $parameters */
    private function describe(array $parameters): string
    {
        $parts = [];

        foreach ($parameters as $name => $value) {
            $parts[] = $name . '=' . (is_float($value) ? $this->number($value, 1) : $value);
        }

        return implode(',', $parts);
    }
}
