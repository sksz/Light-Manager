<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Config;

use LightManager\Application\Dto\Language;
use LightManager\Application\Dto\LoadedSettings;
use LightManager\Application\Dto\SettingKey;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Port\SettingsPort;
use LightManager\Infrastructure\I18n\TranslatorService;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Konfiguracja w pliku `~/.light-manager/settings.json`.
 *
 * Katalog i plik powstają dopiero przy pierwszym zapisie — sam start aplikacji
 * niczego nie tworzy na dysku, bo użytkownik, który nigdy nie wszedł w
 * ustawienia, nie ma powodu znajdować po sobie śladów w katalogu domowym.
 *
 * Odczyt jest wyrozumiały i milczący tam, gdzie milczeć wypada: brak pliku to
 * normalny stan pierwszego uruchomienia, nieznany klucz idzie do kosza bez
 * słowa (plik z przyszłej wersji nie ma prawa straszyć), a znany klucz z
 * wartością spoza zakresu wraca do domyślnej **i mówi o tym w pasku stanu**.
 * Pliku, którego nie zrozumieliśmy, nigdy nie nadpisujemy sami — nadpisze go
 * dopiero jawna zmiana ustawienia przez użytkownika.
 *
 * Zapis idzie przez plik tymczasowy i `rename()` w tym samym katalogu: `rename`
 * w obrębie jednego systemu plików jest niepodzielny, więc przerwany zapis nie
 * zostawia obciętego JSON-a, tylko poprzednią, poprawną wersję.
 */
final class SettingsService extends AbstractSingleton implements SettingsPort
{
    private const DIRECTORY = '.light-manager';

    private const FILE = 'settings.json';

    private const TEMPORARY_PREFIX = '.settings-';

    /** Podobiekt z ustawieniami modułów, obok kluczy rdzenia. */
    private const MODULES_KEY = 'modules';

    /**
     * Klucz rdzenia, który w kroku 21 zszedł do modułu przeglądarki.
     *
     * Przepisujemy go **raz**, przy odczycie, i tylko wtedy, gdy w podprzestrzeni
     * modułu nic jeszcze nie stoi. Reguła kroku 14 kazałaby go po prostu pominąć
     * jak każdy nieznany klucz — świadomie z niej tu rezygnujemy, żeby ustawienie
     * przeżyło aktualizację. Ceną są trzy stałe, przez które usługa konfiguracji
     * zna nazwę jednego modułu; wolno je usunąć, gdy przestanie być prawdopodobne,
     * że ktoś ma jeszcze plik sprzed tej wersji.
     */
    private const LEGACY_HIDDEN_KEY = 'showHiddenEntries';

    private const LEGACY_HIDDEN_MODULE = 'browser';

    private const LEGACY_HIDDEN_SETTING = 'showHidden';

    /** Właściciel czyta i pisze, reszta świata nic — plik opisuje wyłącznie jego środowisko. */
    private const FILE_MODE = 0o600;

    private const DIRECTORY_MODE = 0o700;

    private ?Settings $current = null;

    private ?string $problem = null;

    /**
     * Wynik pierwszego wywołania jest zapamiętany: pętla składa klatkę 20 razy
     * na sekundę i każda z nich pyta o ustawienia, a plik zmienia się wyłącznie
     * naszą ręką.
     *
     * @param list<string> $themeNames dopuszczalne nazwy motywów — zakres tego
     *                                 jednego klucza zna katalog palet, nie
     *                                 usługa konfiguracji
     */
    public function load(array $themeNames): LoadedSettings
    {
        if ($this->current === null) {
            [$settings, $rejected, $unreadable] = $this->read($themeNames);

            // Ustawienia zapamiętujemy **przed** złożeniem komunikatu, bo napis
            // idzie przez tłumacza, a ten pyta konfigurację o wybrany język.
            // Odwrotna kolejność wpuściłaby go w środek trwającego odczytu.
            $this->current = $settings;
            $this->problem = $this->describe($rejected, $unreadable);
        }

        return new LoadedSettings($this->current, $this->problem);
    }

    /** Ustawienia obowiązujące w tej chwili — wejście dla renderowania. */
    public function current(): Settings
    {
        return $this->current ??= $this->read([])[0];
    }

    public function save(Settings $settings): ?string
    {
        $this->current = $settings;

        // Zapisany plik jest już zrozumiały, więc powód ewentualnego wcześniejszego
        // ostrzeżenia przestaje obowiązywać.
        $this->problem = null;

        try {
            $this->write($settings);
        } catch (ConfigException $exception) {
            return TranslatorService::getInstance()->translate(
                match ($exception->failure) {
                    ConfigFailure::UnwritableDirectory => 'config.save.directory',
                    ConfigFailure::UnwritableFile => 'config.save.file',
                    ConfigFailure::FailedEncoding => 'config.save.encoding',
                },
                ['path' => $exception->path],
            );
        }

        return null;
    }

    public function location(): string
    {
        return $this->directory() . DIRECTORY_SEPARATOR . self::FILE;
    }

    /**
     * Odczyt niczego nie tłumaczy — oddaje surowy materiał na komunikat: listę
     * odrzuconych kluczy i ścieżkę pliku, którego nie dało się przeczytać.
     *
     * @param list<string> $themeNames
     *
     * @return array{Settings, list<SettingKey>, string|null}
     */
    private function read(array $themeNames): array
    {
        $path = $this->location();

        if (!is_file($path)) {
            return [new Settings(), [], null];
        }

        $raw = @file_get_contents($path);

        if ($raw === false) {
            return [new Settings(), [], $path];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return [new Settings(), [], $path];
        }

        [$settings, $rejected] = $this->fromArray($decoded, $themeNames);

        return [$settings, $rejected, null];
    }

    /**
     * @param array<array-key, mixed> $values
     * @param list<string>            $themeNames
     *
     * @return array{Settings, list<SettingKey>}
     */
    private function fromArray(array $values, array $themeNames): array
    {
        $settings = new Settings();
        $rejected = [];

        foreach (SettingKey::cases() as $key) {
            if (!array_key_exists($key->value, $values)) {
                continue;
            }

            $applied = $this->apply($settings, $key, $values[$key->value], $themeNames);

            if ($applied === null) {
                $rejected[] = $key;

                continue;
            }

            $settings = $applied;
        }

        $modules = self::modulesFrom($values[self::MODULES_KEY] ?? null);

        return [$settings->withModules(self::migrated($modules, $values)), $rejected];
    }

    /**
     * Widoczność wpisów ukrytych z pliku sprzed kroku 21, przepisana do
     * podprzestrzeni modułu przeglądarki.
     *
     * Wartość zapisana już przez moduł ma pierwszeństwo: plik z obydwoma kluczami
     * naraz powstaje wyłącznie wtedy, gdy użytkownik zdążył ruszyć ustawienie po
     * aktualizacji, a wtedy to ono jest prawdą. Sam stary klucz z pliku nie
     * znika — wypadnie z niego przy najbliższym zapisie, bo `toArray()` już go
     * nie wypisuje.
     *
     * @param array<string, array<string, bool|int|string>> $modules
     * @param array<array-key, mixed>                       $values
     *
     * @return array<string, array<string, bool|int|string>>
     */
    private static function migrated(array $modules, array $values): array
    {
        $legacy = $values[self::LEGACY_HIDDEN_KEY] ?? null;

        if (!is_bool($legacy) || isset($modules[self::LEGACY_HIDDEN_MODULE][self::LEGACY_HIDDEN_SETTING])) {
            return $modules;
        }

        $modules[self::LEGACY_HIDDEN_MODULE][self::LEGACY_HIDDEN_SETTING] = $legacy;

        return $modules;
    }

    /**
     * Podprzestrzeń modułów wczytana **surowo**: usługa konfiguracji nie zna
     * deklaracji pozycji modułu, więc nie ma czym sprawdzić wartości. Robi to
     * `ModuleSetting` przy pokazaniu i przy zapisie.
     *
     * Odsiew jest więc wyłącznie typowy — przechodzą podobiekty i wartości
     * skalarne. Dzięki temu **ustawienia modułu nieznanego zostają nietknięte**:
     * moduł chwilowo wyłączony albo usunięty z listy w `Bootstrap` odzyska swoją
     * konfigurację, gdy wróci.
     *
     * @return array<string, array<string, bool|int|string>>
     */
    private static function modulesFrom(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $modules = [];

        /** @var mixed $entry */
        foreach ($value as $id => $entry) {
            if (!is_string($id) || !is_array($entry)) {
                continue;
            }

            $values = [];

            /** @var mixed $item */
            foreach ($entry as $key => $item) {
                if (is_string($key) && (is_bool($item) || is_int($item) || is_string($item))) {
                    $values[$key] = $item;
                }
            }

            $modules[$id] = $values;
        }

        return $modules;
    }

    /**
     * Komunikat o pliku, który nie w pełni doszedł do skutku.
     *
     * Liczba odrzuconych kluczy odmienia zdanie, więc idzie przez formę mnogą —
     * to jedyne miejsce w interfejsie, w którym liczba naprawdę zmienia napis.
     *
     * @param list<SettingKey> $rejected
     */
    private function describe(array $rejected, ?string $unreadable): ?string
    {
        $translator = TranslatorService::getInstance();

        if ($unreadable !== null) {
            return $translator->translate('config.unreadable', ['path' => $unreadable]);
        }

        if ($rejected === []) {
            return null;
        }

        $labels = array_map(
            static fn (SettingKey $key): string => $translator->translate($key->labelKey()),
            $rejected,
        );

        return $translator->plural('config.rejected', count($rejected), ['keys' => implode(', ', $labels)]);
    }

    /**
     * `null` znaczy „wartość odrzucona” — ustawienie zostaje przy domyślnym.
     *
     * @param list<string> $themeNames
     */
    private function apply(Settings $settings, SettingKey $key, mixed $value, array $themeNames): ?Settings
    {
        return match ($key) {
            SettingKey::Language => is_string($value) && Language::tryFrom($value) !== null
                ? $settings->withLanguage($value)
                : null,
            SettingKey::Theme => is_string($value) && ($themeNames === [] || in_array($value, $themeNames, true))
                ? $settings->withTheme($value)
                : null,
            // Dopuszczalne wartości modułu domyślnego zna dopiero rejestr, a ten
            // powstaje po odczycie konfiguracji. Tu sprawdzamy więc sam typ;
            // wartość bez pokrycia w rejestrze rozliczy `Bootstrap`, wracając do
            // modułu ostatniej szansy wraz z komunikatem (krok 21).
            SettingKey::StartupModule => is_string($value) && $value !== ''
                ? $settings->withStartupModule($value)
                : null,
            SettingKey::TextAntialias => is_bool($value) ? $settings->withTextAntialias($value) : null,
            SettingKey::StrokeAntialias => is_bool($value) ? $settings->withStrokeAntialias($value) : null,
            SettingKey::PaletteColors => is_int($value) && in_array($value, Settings::PALETTE_CHOICES, true)
                ? $settings->withPaletteColors($value)
                : null,
            // Rozmiar okna sprawdzamy **zakresem, nie listą** (krok 37): okno
            // zapamiętuje rozmiar nadany przeciągnięciem rogu, więc wartość
            // wypadająca między przystankami strzałek jest tu stanem zwykłym.
            SettingKey::WindowColumns => is_int($value) && Settings::allowsWindowColumns($value)
                ? $settings->withWindowColumns($value)
                : null,
            SettingKey::WindowRows => is_int($value) && Settings::allowsWindowRows($value)
                ? $settings->withWindowRows($value)
                : null,
        };
    }

    private function write(Settings $settings): void
    {
        $directory = $this->directory();

        if (!is_dir($directory) && !@mkdir($directory, self::DIRECTORY_MODE, true) && !is_dir($directory)) {
            throw ConfigException::forUnwritableDirectory($directory);
        }

        $json = json_encode($this->toArray($settings), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw ConfigException::forFailedEncoding();
        }

        $path = $this->location();
        $temporary = $directory . DIRECTORY_SEPARATOR . self::TEMPORARY_PREFIX . getmypid() . '.tmp';

        if (@file_put_contents($temporary, $json . "\n") === false) {
            throw ConfigException::forUnwritableFile($path);
        }

        @chmod($temporary, self::FILE_MODE);

        if (!@rename($temporary, $path)) {
            @unlink($temporary);

            throw ConfigException::forUnwritableFile($path);
        }
    }

    /**
     * Podprzestrzeń modułów dopisuje się dopiero wtedy, gdy jest co dopisać —
     * plik użytkownika, który nie ruszył żadnego modułu, nie ma powodu nosić
     * pustego podobiektu.
     *
     * @return array<string, bool|int|string|array<string, array<string, bool|int|string>>>
     */
    private function toArray(Settings $settings): array
    {
        $values = [
            SettingKey::Language->value => $settings->language,
            SettingKey::Theme->value => $settings->theme,
            SettingKey::StartupModule->value => $settings->startupModule,
            SettingKey::TextAntialias->value => $settings->textAntialias,
            SettingKey::StrokeAntialias->value => $settings->strokeAntialias,
            SettingKey::PaletteColors->value => $settings->paletteColors,
            SettingKey::WindowColumns->value => $settings->windowColumns,
            SettingKey::WindowRows->value => $settings->windowRows,
        ];

        if ($settings->modules !== []) {
            $values[self::MODULES_KEY] = $settings->modules;
        }

        return $values;
    }

    /**
     * Katalog domowy bierzemy z `HOME`. Gdy zmiennej nie ma — a to stan
     * patologiczny, nie zwykły — konfiguracja ląduje w katalogu roboczym
     * procesu, żeby ekran ustawień działał zamiast wywracać się na starcie.
     */
    private function directory(): string
    {
        $home = getenv('HOME');

        if (!is_string($home) || $home === '') {
            $working = getcwd();
            $home = $working === false ? '.' : $working;
        }

        return rtrim($home, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::DIRECTORY;
    }
}
