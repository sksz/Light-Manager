<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Dto\Language;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Infrastructure\I18n\Catalog;

/**
 * Katalogi napisów obu języków naraz — dla testów zgodności dokumentacji
 * (krok 66).
 *
 * Testy porównują **napis, który widzi użytkownik**, z komórką tabeli
 * w podręczniku, i muszą to zrobić w obu językach w jednym przebiegu.
 * `TranslatorService` tego nie umie z założenia: język wybiera się raz, na całe
 * uruchomienie, a przypięcie go w teście idzie przez zmienne środowiskowe
 * (`PinsLanguage`). Dlatego tutaj czyta się **same katalogi**, tą samą klasą,
 * której używa aplikacja — z modułami dołożonymi jako źródła, bo napisy modułu
 * scalają się z rdzeniowymi.
 */
final class DocumentedCatalogues
{
    /** @var array<string, Catalog> */
    private static array $loaded = [];

    private function __construct()
    {
    }

    /** @param list<ModuleInterface> $modules */
    public static function of(Language $language, array $modules): Catalog
    {
        $key = $language->value;

        if (isset(self::$loaded[$key])) {
            return self::$loaded[$key];
        }

        $sources = [];

        foreach ($modules as $module) {
            $directory = $module->translations();

            if ($directory !== null) {
                $sources[$module->id()] = $directory;
            }
        }

        $catalog = Catalog::load($language, DocumentationTree::root() . '/lang', $sources);

        if ($catalog === null) {
            throw new \RuntimeException('brak katalogu napisów dla języka ' . $language->value);
        }

        return self::$loaded[$key] = $catalog;
    }

    /** Napis dla klucza; klucz bez wpisu wraca sam sobą, jak w aplikacji. */
    public static function text(Catalog $catalog, string $key): string
    {
        return $catalog->text($key) ?? $key;
    }
}
