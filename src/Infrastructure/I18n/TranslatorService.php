<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\I18n;

use LightManager\Application\Dto\Language;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Infrastructure\Config\SettingsService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use NumberFormatter;
use Throwable;

/**
 * Napisy interfejsu w języku obowiązującym w tej chwili.
 *
 * Język bierze się z konfiguracji, więc `active()` pyta o nią przy każdym
 * wywołaniu, a nie raz przy budowie usługi — tak samo jak motyw w
 * `ThemeService`, i z tego samego powodu: zmiana na ekranie ustawień ma być
 * widoczna w następnej klatce, bez restartu. Ustawienie `auto` (domyślne) każe
 * zajrzeć do środowiska; odczyt `LANG` jest zapamiętywany, bo zmienne
 * środowiskowe procesu w trakcie działania się nie zmieniają.
 *
 * Żadna ścieżka nie kończy się wyjątkiem. Brakujący klucz wraca jako własna
 * nazwa — na ekranie widać wtedy `browser.hints` zamiast napisu, co jest
 * brzydkie, ale czytelne i nie przerywa pętli. Brakujący **język** cofa się do
 * angielskiego.
 *
 * Usługa nie może sięgać po konfigurację w trakcie jej wczytywania — dlatego
 * `SettingsService` buduje swój komunikat dopiero po zapamiętaniu ustawień,
 * nigdy w środku odczytu pliku.
 */
final class TranslatorService extends AbstractSingleton implements TranslatorPort
{
    /** Nazwa parametru, pod którą do napisu mnogiego trafia sama liczba. */
    private const COUNT_PARAMETER = 'count';

    private const DECIMAL_SEPARATOR_KEY = 'format.decimal';

    /** @var array<string, Catalog> */
    private array $catalogs = [];

    /** @var array<string, string> źródła napisów modułów: `id modułu` → katalog z plikami */
    private array $sources = [];

    private ?Language $environment = null;

    private bool $environmentRead = false;

    /**
     * Melduje pliki napisów modułu. `Bootstrap` woła to przed pętlą — katalogi
     * wczytują się leniwie, więc wystarczy zdążyć przed pierwszym tłumaczeniem.
     *
     * Katalogi już wczytane są przy okazji zapominane: usługa jest Singletonem
     * i przeżywa test, w którym po pierwszym tłumaczeniu dochodzi nowe źródło.
     */
    public function addSource(string $moduleId, string $directory): void
    {
        $this->sources[$moduleId] = $directory;
        $this->catalogs = [];
    }

    /**
     * Klucze, które moduły próbowały wnieść poza swoim przedrostkiem — pominięte
     * przy scalaniu (P16). Materiał na komunikat przy starcie.
     *
     * @return list<string>
     */
    public function ignoredKeys(): array
    {
        return $this->catalog($this->active())?->ignored() ?? [];
    }

    public function translate(string $key, array $parameters = []): string
    {
        $language = $this->active();
        $text = $this->catalog($language)?->text($key)
            ?? $this->catalog(Language::FALLBACK)?->text($key);

        return $text === null ? $key : $this->substitute($text, $parameters);
    }

    public function plural(string $key, int $count, array $parameters = []): string
    {
        $language = $this->active();
        $forms = $this->catalog($language)?->forms($key);

        if ($forms === null) {
            $language = Language::FALLBACK;
            $forms = $this->catalog($language)?->forms($key);
        }

        if ($forms === null) {
            return $key;
        }

        $index = min(PluralRule::forLanguage($language)->formFor($count), count($forms) - 1);
        $parameters[self::COUNT_PARAMETER] = $this->number((float) $count);

        return $this->substitute($forms[$index], $parameters);
    }

    public function number(float $value, int $decimals = 0): string
    {
        return $this->formatWithIntl($value, $decimals)
            ?? number_format($value, $decimals, $this->decimalSeparator(), '');
    }

    public function active(): Language
    {
        $configured = Language::tryFrom(SettingsService::getInstance()->current()->language);

        if ($configured !== null && $configured !== Language::Auto) {
            return $configured;
        }

        return $this->fromEnvironment() ?? Language::FALLBACK;
    }

    /**
     * Grupowanie tysięcy jest wyłączone, żeby obie ścieżki — z `intl` i bez —
     * dawały ten sam napis. Liczby pokazywane w interfejsie i tak nie dochodzą
     * do czterech cyfr (rozmiar pliku przeskakuje wtedy na większą jednostkę).
     */
    private function formatWithIntl(float $value, int $decimals): ?string
    {
        if (!extension_loaded('intl')) {
            return null;
        }

        try {
            $formatter = new NumberFormatter($this->active()->value, NumberFormatter::DECIMAL);
        } catch (Throwable) {
            return null;
        }

        $formatter->setAttribute(NumberFormatter::GROUPING_USED, 0);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

        $formatted = $formatter->format($value);

        return $formatted === false ? null : $formatted;
    }

    private function decimalSeparator(): string
    {
        $catalog = $this->catalog($this->active()) ?? $this->catalog(Language::FALLBACK);

        return $catalog?->text(self::DECIMAL_SEPARATOR_KEY) ?? '.';
    }

    /**
     * Podstawienia są nazwane (`{path}`), nie pozycyjne: tłumaczenie bywa
     * przestawione względem oryginału, a nazwa to przeżywa.
     *
     * @param array<string, string|int|float> $parameters
     */
    private function substitute(string $text, array $parameters): string
    {
        if ($parameters === []) {
            return $text;
        }

        $replacements = [];

        foreach ($parameters as $name => $value) {
            $replacements['{' . $name . '}'] = is_float($value) ? $this->number($value, 1) : (string) $value;
        }

        return strtr($text, $replacements);
    }

    private function catalog(Language $language): ?Catalog
    {
        if (array_key_exists($language->value, $this->catalogs)) {
            return $this->catalogs[$language->value];
        }

        $catalog = Catalog::load($language, self::directory(), $this->sources);

        if ($catalog === null) {
            return null;
        }

        return $this->catalogs[$language->value] = $catalog;
    }

    /**
     * Pierwsza zmienna, która niesie rozpoznawalny kod języka, wygrywa —
     * kolejność jest ta sama, którą stosuje biblioteka standardowa C.
     */
    private function fromEnvironment(): ?Language
    {
        if ($this->environmentRead) {
            return $this->environment;
        }

        $this->environmentRead = true;

        foreach (['LC_ALL', 'LC_MESSAGES', 'LANG'] as $variable) {
            $value = getenv($variable);

            if (!is_string($value) || $value === '') {
                continue;
            }

            $language = self::fromLocale($value);

            if ($language !== null) {
                return $this->environment = $language;
            }
        }

        return $this->environment;
    }

    /** `pl_PL.UTF-8` i `pl` to ten sam język; `C` i `POSIX` nie są żadnym. */
    private static function fromLocale(string $locale): ?Language
    {
        $code = strtolower((string) preg_replace('/[^A-Za-z].*$/', '', $locale));

        if ($code === '') {
            return null;
        }

        $language = Language::tryFrom($code);

        return $language === Language::Auto ? null : $language;
    }

    /** Katalogi napisów leżą w `lang/` w korzeniu repozytorium. */
    private static function directory(): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'lang';
    }
}
