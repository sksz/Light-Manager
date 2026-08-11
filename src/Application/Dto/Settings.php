<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * Komplet ustawień aplikacji — niemutowalny, z wartościami domyślnymi
 * obowiązującymi przy pierwszym uruchomieniu.
 *
 * Wartości domyślne wygładzania i palety pochodzą z pomiarów kroku 13
 * ([00-decyzje.md](../../../docs/plans/00-decyzje.md), D27): tekst bez
 * wygładzania (kosztowało 33 ms na klatkę), obrysy z wygładzaniem (bez niego
 * łuk o promieniu dziewięciu pikseli jest schodkami), paleta 64 kolorów (przy
 * 16 i 32 obwódki paneli nie przeżywają kwantyzacji). Pomiary były doraźne —
 * po kroku 16, który daje powtarzalne narzędzie, należy je zweryfikować.
 *
 * Język trzymamy jako kod, a nie jako `Language`, bo ten obiekt jest zapisywany
 * do JSON-a jeden do jednego; sprawdzeniem, że kod ma pokrycie w enumie,
 * zajmuje się odczyt konfiguracji.
 *
 * Od kroku 20 doszła **podprzestrzeń modułów** (`modules.<id>.<klucz>`) i jest
 * jedynym polem trzymanym surowo: rdzeń nie zna deklaracji pozycji modułu, więc
 * nie ma czym sprawdzić wartości przy odczycie. Robi to `ModuleSetting` przy
 * pokazaniu i przy zapisie, a wartości modułu **nieznanego zostają nietknięte** —
 * moduł chwilowo wyłączony albo usunięty z listy w `Bootstrap` nie ma tracić
 * swojej konfiguracji.
 */
final class Settings
{
    public const DEFAULT_THEME = 'grafit';

    /** Wartości, po których chodzi przełącznik palety. Powyżej 128 zysk jest żaden. */
    public const PALETTE_CHOICES = [16, 32, 64, 128];

    public const DEFAULT_PALETTE_COLORS = 64;

    /** Poniżej tylu kolorów kwantyzator poświęca odcień obwódki paneli (D27). */
    public const SAFE_PALETTE_COLORS = 64;

    /** Moduł otwierany przy starcie, dopóki konfiguracja nie wskaże innego. */
    public const DEFAULT_STARTUP_MODULE = 'browser';

    /** @param array<string, array<string, bool|int|string>> $modules `id modułu` → klucz → wartość */
    public function __construct(
        public readonly string $language = Language::Auto->value,
        public readonly string $theme = self::DEFAULT_THEME,
        public readonly string $startupModule = self::DEFAULT_STARTUP_MODULE,
        public readonly bool $textAntialias = false,
        public readonly bool $strokeAntialias = true,
        public readonly int $paletteColors = self::DEFAULT_PALETTE_COLORS,
        public readonly array $modules = [],
    ) {
    }

    /**
     * Następna albo poprzednia wartość ustawienia. Przełączniki dwustanowe
     * ignorują kierunek — obie strzałki robią z nich to samo, bo „poprzednie
     * nie” i „następne nie” to ta sama wartość.
     *
     * @param list<string> $themeNames     motywy dostępne w tym uruchomieniu; lista
     *                                     przychodzi z portu, bo katalog palet zna
     *                                     wyłącznie warstwa renderowania
     * @param list<string> $startupModules identyfikatory modułów z ekranem, przyjętych
     *                                     w tym uruchomieniu; lista liczona przy
     *                                     starcie, nie wpisana w kod (krok 21)
     */
    public function shifted(SettingKey $key, int $direction, array $themeNames, array $startupModules = []): self
    {
        return match ($key) {
            SettingKey::Language => $this->withLanguage(
                self::next(self::languageCodes(), $this->language, $direction),
            ),
            SettingKey::Theme => $this->withTheme(self::next($themeNames, $this->theme, $direction)),
            SettingKey::StartupModule => $this->withStartupModule(
                self::next($startupModules, $this->startupModule, $direction),
            ),
            SettingKey::TextAntialias => $this->withTextAntialias(!$this->textAntialias),
            SettingKey::StrokeAntialias => $this->withStrokeAntialias(!$this->strokeAntialias),
            SettingKey::PaletteColors => $this->withPaletteColors(
                self::next(self::PALETTE_CHOICES, $this->paletteColors, $direction),
            ),
        };
    }

    public function withLanguage(string $language): self
    {
        return $this->copy(language: $language);
    }

    public function withTheme(string $theme): self
    {
        return $this->copy(theme: $theme);
    }

    public function withStartupModule(string $startupModule): self
    {
        return $this->copy(startupModule: $startupModule);
    }

    public function withTextAntialias(bool $textAntialias): self
    {
        return $this->copy(textAntialias: $textAntialias);
    }

    public function withStrokeAntialias(bool $strokeAntialias): self
    {
        return $this->copy(strokeAntialias: $strokeAntialias);
    }

    public function withPaletteColors(int $paletteColors): self
    {
        return $this->copy(paletteColors: $paletteColors);
    }

    /** @param array<string, array<string, bool|int|string>> $modules */
    public function withModules(array $modules): self
    {
        return $this->copy(modules: $modules);
    }

    /** Wartość ustawienia modułu tak, jak leży w pliku; `null`, gdy nikt jej nie zapisał. */
    public function moduleValue(string $id, string $key): bool|int|string|null
    {
        return $this->modules[$id][$key] ?? null;
    }

    /** @return array<string, bool|int|string> komplet wartości jednego modułu */
    public function moduleSettings(string $id): array
    {
        return $this->modules[$id] ?? [];
    }

    public function withModuleValue(string $id, string $key, bool|int|string $value): self
    {
        $modules = $this->modules;
        $modules[$id][$key] = $value;

        return $this->copy(modules: $modules);
    }

    public function equals(self $other): bool
    {
        return $this->language === $other->language
            && $this->theme === $other->theme
            && $this->startupModule === $other->startupModule
            && $this->textAntialias === $other->textAntialias
            && $this->strokeAntialias === $other->strokeAntialias
            && $this->paletteColors === $other->paletteColors
            && $this->modules === $other->modules;
    }

    /**
     * Kopia ze zmienioną częścią pól; `null` znaczy „zostaw jak było”.
     *
     * Do kroku 20 każda metoda `with*` wypisywała komplet pól z osobna. Przy
     * sześciu polach było to znośne; siódme — podprzestrzeń modułów — zamieniłoby
     * pominięcie jednego argumentu w cichą utratę całej konfiguracji modułów.
     * Jedno miejsce, w którym pola się przepisują, znosi tę klasę pomyłek.
     *
     * @param array<string, array<string, bool|int|string>>|null $modules
     */
    private function copy(
        ?string $language = null,
        ?string $theme = null,
        ?string $startupModule = null,
        ?bool $textAntialias = null,
        ?bool $strokeAntialias = null,
        ?int $paletteColors = null,
        ?array $modules = null,
    ): self {
        return new self(
            $language ?? $this->language,
            $theme ?? $this->theme,
            $startupModule ?? $this->startupModule,
            $textAntialias ?? $this->textAntialias,
            $strokeAntialias ?? $this->strokeAntialias,
            $paletteColors ?? $this->paletteColors,
            $modules ?? $this->modules,
        );
    }

    /** @return list<string> */
    private static function languageCodes(): array
    {
        return array_map(static fn (Language $language): string => $language->value, Language::cases());
    }

    /**
     * Sąsiad na liście, cyklicznie. Wartość spoza listy (plik z konfiguracją
     * ruszony ręcznie) traktujemy jak stojącą przed pierwszą pozycją.
     *
     * @template TValue of int|string
     *
     * @param list<TValue> $choices
     * @param TValue       $current
     *
     * @return TValue
     */
    private static function next(array $choices, int|string $current, int $direction): int|string
    {
        if ($choices === []) {
            return $current;
        }

        $position = array_search($current, $choices, true);
        $count = count($choices);
        $step = $direction < 0 ? -1 : 1;

        if ($position === false) {
            return $choices[0];
        }

        return $choices[($position + $step + $count) % $count];
    }
}
