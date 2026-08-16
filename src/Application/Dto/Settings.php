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

    /**
     * Rozmiar okna trybu okienkowego, w komórkach (krok 34, D53). Wartości, po
     * których chodzą strzałki — wzorem `PALETTE_CHOICES`.
     *
     * Od kroku 37 lista **nie jest już zakresem dopuszczalnych wartości**, tylko
     * przystankami strzałek: okno zapamiętuje rozmiar nadany przeciągnięciem rogu,
     * a ten wypada między przystankami dziewięć razy na dziesięć. Zakresu pilnują
     * `WINDOW_*_MIN`/`MAX`, a strzałka z wartości spoza listy idzie do sąsiada
     * w swoją stronę, nie na początek listy (`nextStop()`).
     */
    public const WINDOW_COLUMNS_CHOICES = [80, 100, 120, 140, 160, 200];

    public const WINDOW_ROWS_CHOICES = [24, 30, 40, 50, 60];

    public const DEFAULT_WINDOW_COLUMNS = 100;

    public const DEFAULT_WINDOW_ROWS = 30;

    /**
     * Granice zapamiętanego rozmiaru (krok 37). Dolna jest tam, gdzie okno
     * przestaje cokolwiek pokazywać, górna — bezpiecznie ponad najszerszym
     * dzisiejszym monitorem; obie istnieją po to, żeby ręcznie ruszony plik
     * konfiguracji nie otworzył okna o zerowej albo monstrualnej siatce.
     */
    public const WINDOW_COLUMNS_MIN = 20;

    public const WINDOW_COLUMNS_MAX = 1000;

    public const WINDOW_ROWS_MIN = 5;

    public const WINDOW_ROWS_MAX = 400;

    /**
     * Ile najwyżej pamiętać wyjścia polecenia tłowego, w kibibajtach (krok 49).
     *
     * Klucz powstał, bo do kroku 49 limit był **stałą wpisaną w kod**
     * (64 KiB w `BackgroundProcessService`) dobraną pod polecenia oddające jeden
     * wiersz — `du -s`, `file -b`. Zdalny katalog jest pierwszym odbiorcą, dla
     * którego wyjściem jest **treść**, a nie liczba: wypis `sftp ls -l` kosztuje
     * około 84 bajtów na wpis, więc dawna stała urywała listę na siedmiuset
     * wpisach i robiła to **po cichu**.
     *
     * Wartość jest **sufitem, nie rezerwacją** — pamięć rośnie dopiero wtedy, gdy
     * polecenie naprawdę tyle wypisze. Domyślne 1024 KiB mieści katalog o około
     * dwunastu tysiącach wpisów i zarazem zostaje granicą dla polecenia, które
     * zaczęłoby sypać bez końca.
     */
    public const BACKGROUND_OUTPUT_CHOICES = [64, 256, 1024, 4096, 16384];

    public const DEFAULT_BACKGROUND_OUTPUT_KIB = 1024;

    /**
     * Ile prac tłowych wolno prowadzić naraz (krok 51, D90 nr 1).
     *
     * Do kroku 51 port prowadził **jedną** i była to decyzja z kroku 26, nie
     * ograniczenie techniczne. Trzeci odbiorca — moduł Dockera, którego
     * `compose up` trwa minutami, a `compose logs -f` nie kończy się nigdy —
     * zamieniłby ją w regułę „jedna funkcja aplikacji wyłącza drugą bez słowa
     * wyjaśnienia”.
     *
     * Granica jest **ustawieniem, a nie stałą**, bo zakres nr 7 planu kroku każe
     * zmierzyć koszt `poll()` „przy pustym zestawie prac i przy pełnym” — a
     * „pełny” jest pojęciem wykonalnym dopiero wtedy, gdy liczba daje się
     * ustawić bez przepisywania kodu. Przekroczenie znaczy **odmowę**, nie
     * wyparcie najstarszej: wyparcie przywracałoby dokładnie tę chorobę, którą
     * rozbudowa leczy.
     *
     * Wartość jest **sufitem, nie rezerwacją** — potomek powstaje dopiero wtedy,
     * gdy ktoś o niego poprosi. Przystanek `1` zostaje w liście świadomie: jest
     * dokładnie dawnym zachowaniem portu (z odmową zamiast wyparcia) i tanim
     * sposobem sprawdzenia, jak aplikacja znosi granicę osiągniętą.
     */
    public const BACKGROUND_JOBS_CHOICES = [1, 2, 4, 8, 16];

    public const DEFAULT_BACKGROUND_JOBS = 8;

    /** @param array<string, array<string, bool|int|string>> $modules `id modułu` → klucz → wartość */
    public function __construct(
        public readonly string $language = Language::Auto->value,
        public readonly string $theme = self::DEFAULT_THEME,
        public readonly string $startupModule = self::DEFAULT_STARTUP_MODULE,
        public readonly bool $textAntialias = false,
        public readonly bool $strokeAntialias = true,
        public readonly int $paletteColors = self::DEFAULT_PALETTE_COLORS,
        public readonly array $modules = [],
        public readonly int $windowColumns = self::DEFAULT_WINDOW_COLUMNS,
        public readonly int $windowRows = self::DEFAULT_WINDOW_ROWS,
        public readonly int $backgroundOutputKib = self::DEFAULT_BACKGROUND_OUTPUT_KIB,
        public readonly int $backgroundJobs = self::DEFAULT_BACKGROUND_JOBS,
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
            SettingKey::WindowColumns => $this->withWindowColumns(
                self::nextStop(self::WINDOW_COLUMNS_CHOICES, $this->windowColumns, $direction),
            ),
            SettingKey::WindowRows => $this->withWindowRows(
                self::nextStop(self::WINDOW_ROWS_CHOICES, $this->windowRows, $direction),
            ),
            SettingKey::BackgroundOutputKib => $this->withBackgroundOutputKib(
                self::next(self::BACKGROUND_OUTPUT_CHOICES, $this->backgroundOutputKib, $direction),
            ),
            SettingKey::BackgroundJobs => $this->withBackgroundJobs(
                self::next(self::BACKGROUND_JOBS_CHOICES, $this->backgroundJobs, $direction),
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

    public function withWindowColumns(int $windowColumns): self
    {
        return $this->copy(windowColumns: $windowColumns);
    }

    public function withWindowRows(int $windowRows): self
    {
        return $this->copy(windowRows: $windowRows);
    }

    public function withBackgroundOutputKib(int $backgroundOutputKib): self
    {
        return $this->copy(backgroundOutputKib: $backgroundOutputKib);
    }

    public function withBackgroundJobs(int $backgroundJobs): self
    {
        return $this->copy(backgroundJobs: $backgroundJobs);
    }

    /**
     * Granica liczby prac tłowych — postać, w której pyta o nią usługa procesu.
     *
     * Dolna granica stoi tutaj z tego samego powodu, co przy limicie wyjścia:
     * plik konfiguracji ruszony ręcznie jest jedynym sposobem, żeby wpisać tam
     * zero, a zero znaczyłoby „żadna praca tłowa się nie uruchomi” — czyli
     * awarię wyglądającą jak cisza.
     */
    public function backgroundJobLimit(): int
    {
        return max(self::BACKGROUND_JOBS_CHOICES[0], $this->backgroundJobs);
    }

    /**
     * Limit wyjścia w bajtach — postać, w której pyta o niego usługa procesu.
     *
     * Dolna granica jest tu, a nie w usłudze, bo plik konfiguracji ruszony ręcznie
     * jest jedynym sposobem, żeby wpisać tam zero — a limit zerowy znaczyłby
     * „każde polecenie oddaje pustkę”, czyli awarię wyglądającą jak cisza.
     */
    public function backgroundOutputBytes(): int
    {
        return max(self::BACKGROUND_OUTPUT_CHOICES[0], $this->backgroundOutputKib) * 1024;
    }

    /** Czy liczba kolumn nadaje się na rozmiar okna — granica pliku ruszonego ręcznie (krok 37). */
    public static function allowsWindowColumns(int $columns): bool
    {
        return $columns >= self::WINDOW_COLUMNS_MIN && $columns <= self::WINDOW_COLUMNS_MAX;
    }

    /** Czy liczba wierszy nadaje się na rozmiar okna — granica pliku ruszonego ręcznie (krok 37). */
    public static function allowsWindowRows(int $rows): bool
    {
        return $rows >= self::WINDOW_ROWS_MIN && $rows <= self::WINDOW_ROWS_MAX;
    }

    /** @param array<string, array<string, bool|int|string>> $modules */
    public function withModules(array $modules): self
    {
        return $this->copy(modules: $modules);
    }

    /**
     * Wartość ustawienia rdzenia spod klucza — jedyne miejsce, które zna
     * odwzorowanie `SettingKey` na pole (krok 53).
     *
     * Do tego kroku takiego odwzorowania nie było, bo każdy odbiorca sięgał po
     * pole po nazwie; kwerenda `core.settings` oddaje **komplet**, więc musi
     * przejść po kluczach — a `match` rozstrzygający wszystkie warianty enuma
     * jest jedynym miejscem, które psuje się jawnie, gdy dojdzie klucz
     * jedenasty. Podprzestrzeń modułów tędy nie przechodzi: ma własny klucz
     * i własną kwerendę, bo jej kształt zależy od modułu, a nie od rdzenia.
     */
    public function valueOf(SettingKey $key): bool|int|string
    {
        return match ($key) {
            SettingKey::Language => $this->language,
            SettingKey::Theme => $this->theme,
            SettingKey::StartupModule => $this->startupModule,
            SettingKey::TextAntialias => $this->textAntialias,
            SettingKey::StrokeAntialias => $this->strokeAntialias,
            SettingKey::PaletteColors => $this->paletteColors,
            SettingKey::WindowColumns => $this->windowColumns,
            SettingKey::WindowRows => $this->windowRows,
            SettingKey::BackgroundOutputKib => $this->backgroundOutputKib,
            SettingKey::BackgroundJobs => $this->backgroundJobs,
        };
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
            && $this->modules === $other->modules
            && $this->windowColumns === $other->windowColumns
            && $this->windowRows === $other->windowRows
            && $this->backgroundOutputKib === $other->backgroundOutputKib
            && $this->backgroundJobs === $other->backgroundJobs;
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
        ?int $windowColumns = null,
        ?int $windowRows = null,
        ?int $backgroundOutputKib = null,
        ?int $backgroundJobs = null,
    ): self {
        return new self(
            $language ?? $this->language,
            $theme ?? $this->theme,
            $startupModule ?? $this->startupModule,
            $textAntialias ?? $this->textAntialias,
            $strokeAntialias ?? $this->strokeAntialias,
            $paletteColors ?? $this->paletteColors,
            $modules ?? $this->modules,
            $windowColumns ?? $this->windowColumns,
            $windowRows ?? $this->windowRows,
            $backgroundOutputKib ?? $this->backgroundOutputKib,
            $backgroundJobs ?? $this->backgroundJobs,
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

    /**
     * Przystanek za bieżącą wartością, cyklicznie — dla list liczbowych
     * ułożonych rosnąco.
     *
     * Różni się od `next()` traktowaniem wartości **spoza listy**, a to od kroku
     * 37 przypadek zwykły, nie awaryjny: rozmiar zapamiętany po przeciągnięciu
     * rogu prawie nigdy nie trafia w przystanek. `next()` odsyłałby wtedy na
     * początek listy, czyli strzałka „w prawo” z 137 kolumn dawałaby 80 — tutaj
     * daje 140, a „w lewo” 120. Wartość leżąca poza całą listą wraca na jej
     * skrajny przystanek, jak przy zwykłym zawinięciu.
     *
     * @param list<int> $choices przystanki ułożone rosnąco
     */
    private static function nextStop(array $choices, int $current, int $direction): int
    {
        if ($choices === []) {
            return $current;
        }

        if ($direction < 0) {
            $smaller = array_filter($choices, static fn (int $choice): bool => $choice < $current);

            return $smaller === [] ? $choices[count($choices) - 1] : max($smaller);
        }

        $larger = array_filter($choices, static fn (int $choice): bool => $choice > $current);

        return $larger === [] ? $choices[0] : min($larger);
    }
}
