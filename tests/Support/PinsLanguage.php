<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Dto\Language;
use LightManager\Infrastructure\Config\SettingsService;
use LightManager\Infrastructure\I18n\TranslatorService;

/**
 * Ustala język na czas testu, żeby asercje na napisach nie zależały od tego,
 * jak ma ustawione środowisko osoba uruchamiająca testy.
 *
 * Język idzie przez zmienne środowiskowe, bo to najkrótsza droga do tego samego
 * wyniku: domyślne ustawienie `auto` pyta właśnie o nie. Katalog domowy jest
 * przy okazji podmieniany na tymczasowy, więc konfiguracja osoby uruchamiającej
 * testy nie ma jak wpłynąć na wynik ani ucierpieć — a test, który chce sprawdzić
 * pierwszeństwo konfiguracji nad środowiskiem, ma gdzie położyć swój plik.
 */
trait PinsLanguage
{
    use ResetsSingletons;

    /** @var array<string, string|false> */
    private array $previousEnvironment = [];

    private string $pinnedHome = '';

    protected function pinLanguage(Language $language): void
    {
        $this->pinnedHome = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'light-manager-lang-' . uniqid();

        mkdir($this->pinnedHome, 0o700, true);

        $variables = [
            'HOME' => $this->pinnedHome,
            'LC_ALL' => $language->value,
            'LC_MESSAGES' => '',
            'LANG' => '',
        ];

        foreach ($variables as $name => $value) {
            $this->previousEnvironment[$name] = getenv($name);
            putenv($value === '' ? $name : $name . '=' . $value);
        }

        $this->forgetLanguageServices();
    }

    protected function unpinLanguage(): void
    {
        foreach ($this->previousEnvironment as $name => $value) {
            putenv($value === false ? $name : $name . '=' . $value);
        }

        $this->previousEnvironment = [];

        $this->removePinnedHome();
        $this->forgetLanguageServices();
    }

    /** Katalog domowy obowiązujący w tym teście — miejsce na plik `settings.json`. */
    protected function pinnedHome(): string
    {
        return $this->pinnedHome;
    }

    /** Po ręcznej zmianie pliku konfiguracyjnego usługi muszą przeczytać go od nowa. */
    protected function forgetLanguageServices(): void
    {
        $this->resetSingleton(SettingsService::class);
        $this->resetSingleton(TranslatorService::class);
    }

    private function removePinnedHome(): void
    {
        if ($this->pinnedHome === '') {
            return;
        }

        foreach (['/.light-manager/settings.json', '/.light-manager/history', '/.light-manager', ''] as $suffix) {
            $path = $this->pinnedHome . $suffix;

            if (is_file($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                @rmdir($path);
            }
        }

        $this->pinnedHome = '';
    }
}
