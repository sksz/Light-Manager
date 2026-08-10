<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Rendering;

use LightManager\Application\Port\ThemePort;
use LightManager\Infrastructure\Config\SettingsService;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Katalog palet i motyw obowiązujący w tej chwili.
 *
 * Motyw wybiera konfiguracja, więc `active()` pyta o nią przy każdym wywołaniu,
 * a nie raz przy budowie usługi — dzięki temu zmiana na ekranie ustawień jest
 * widoczna w następnej klatce, bez restartu. Kosztuje to odczyt zapamiętanej
 * wartości z pamięci, nie z dysku.
 *
 * Nazwa spoza katalogu (plik konfiguracyjny ruszony ręcznie) cofa się do
 * Grafitu zamiast wywracać rysowanie. Ostrzeżenie o takiej wartości pokazuje
 * już wcześniej wczytanie konfiguracji.
 */
final class ThemeService extends AbstractSingleton implements ThemePort
{
    /** @var array<string, Theme> */
    private readonly array $catalog;

    protected function __construct()
    {
        parent::__construct();

        $this->catalog = [
            'grafit' => Theme::grafit(),
            'nordyk' => Theme::nordyk(),
            'papier' => Theme::papier(),
            'indygo' => Theme::indygo(),
        ];
    }

    public function active(): Theme
    {
        return $this->named(SettingsService::getInstance()->current()->theme);
    }

    public function named(string $name): Theme
    {
        return $this->catalog[$name] ?? $this->catalog['grafit'];
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->catalog);
    }

    public function has(string $name): bool
    {
        return isset($this->catalog[$name]);
    }
}
