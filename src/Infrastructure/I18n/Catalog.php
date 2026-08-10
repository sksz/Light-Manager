<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\I18n;

use LightManager\Application\Dto\Language;

/**
 * Napisy jednego języka, wczytane z pliku `lang/<kod>.php`.
 *
 * Plik zwraca zwykłą tablicę PHP, więc odczyt to jedno `require` — bez
 * parsowania i bez ryzyka błędu składni w czasie działania (ten wyszedłby już
 * przy analizie statycznej). Wpis jest napisem albo listą form mnogich;
 * cokolwiek innego w pliku zostaje pominięte, bo katalog ma wytrzymać własną
 * literówkę, a nie wywrócić na niej rysowanie klatki.
 *
 * Katalog niczego nie podstawia i nie wybiera formy — oddaje surową treść.
 * Składaniem napisu zajmuje się `TranslatorService`, żeby reguła liczby mnogiej
 * i podstawienia miały jedno miejsce niezależnie od języka.
 *
 * Od kroku 20 katalog **scala źródła**: po pliku rdzenia wchodzą pliki modułów,
 * po jednym na moduł. Z pliku modułu przyjmowane są wyłącznie klucze zaczynające
 * się od `module.<id>.`; pozostałe są pomijane i wracają przez `ignored()`.
 * Kolizja z kluczem rdzenia staje się przez to **niemożliwa z konstrukcji**,
 * a źródło każdego napisu widać po samej nazwie klucza.
 */
final class Catalog
{
    /**
     * @param array<string, string>       $texts
     * @param array<string, list<string>> $plurals
     * @param list<string>                $ignored klucze pominięte, bo wyszły poza przedrostek modułu
     */
    private function __construct(
        public readonly Language $language,
        private array $texts,
        private array $plurals,
        private array $ignored = [],
    ) {
    }

    /**
     * `null`, gdy języka nie ma w katalogu napisów — wtedy wchodzi język zapasowy.
     *
     * @param array<string, string> $sources źródła modułów: `id modułu` → katalog z plikami
     */
    public static function load(Language $language, string $directory, array $sources = []): ?self
    {
        $entries = self::read($directory, $language);

        if ($entries === null) {
            return null;
        }

        [$texts, $plurals] = self::split($entries);
        $catalog = new self($language, $texts, $plurals);

        foreach ($sources as $id => $source) {
            $catalog->merge($id, self::read($source, $language) ?? []);
        }

        return $catalog;
    }

    /**
     * Dokłada napisy jednego modułu, odsiewając klucze spoza jego przedrostka.
     *
     * @param array<string, mixed> $entries
     */
    private function merge(string $id, array $entries): void
    {
        $prefix = 'module.' . $id . '.';

        /** @var mixed $entry */
        foreach ($entries as $key => $entry) {
            if (!str_starts_with($key, $prefix)) {
                $this->ignored[] = $key;

                continue;
            }

            [$texts, $plurals] = self::split([$key => $entry]);

            $this->texts = array_merge($this->texts, $texts);
            $this->plurals = array_merge($this->plurals, $plurals);
        }
    }

    /**
     * Zawartość jednego pliku napisów; `null`, gdy pliku nie ma albo nie zwraca
     * tablicy.
     *
     * Odczyt to jedno `require` — bez parsowania i bez ryzyka błędu składni
     * w czasie działania (ten wyszedłby już przy analizie statycznej).
     *
     * @return array<string, mixed>|null
     */
    private static function read(string $directory, Language $language): ?array
    {
        $file = $directory . DIRECTORY_SEPARATOR . $language->value . '.php';

        if (!is_file($file)) {
            return null;
        }

        /** @var mixed $loaded */
        $loaded = require $file;

        if (!is_array($loaded)) {
            return null;
        }

        $entries = [];

        /** @var mixed $entry */
        foreach ($loaded as $key => $entry) {
            if (is_string($key)) {
                $entries[$key] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Rozdziela wpisy na napisy i formy mnogie. Wpis, który nie jest ani jednym,
     * ani drugim, znika bez słowa: katalog ma wytrzymać własną literówkę, a nie
     * wywrócić na niej rysowanie klatki.
     *
     * @param array<string, mixed> $entries
     *
     * @return array{array<string, string>, array<string, list<string>>}
     */
    private static function split(array $entries): array
    {
        $texts = [];
        $plurals = [];

        /** @var mixed $entry */
        foreach ($entries as $key => $entry) {
            if (is_string($entry)) {
                $texts[$key] = $entry;

                continue;
            }

            $forms = self::formsFrom($entry);

            if ($forms !== null) {
                $plurals[$key] = $forms;
            }
        }

        return [$texts, $plurals];
    }

    /**
     * Klucze pominięte przy scalaniu — materiał na komunikat o module, który
     * próbował wyjść poza swój przedrostek.
     *
     * @return list<string>
     */
    public function ignored(): array
    {
        return $this->ignored;
    }

    /**
     * Wszystkie klucze katalogu — do porównania kompletności języków w teście.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = array_merge(array_keys($this->texts), array_keys($this->plurals));
        sort($keys);

        return $keys;
    }

    public function text(string $key): ?string
    {
        return $this->texts[$key] ?? null;
    }

    /** @return list<string>|null */
    public function forms(string $key): ?array
    {
        return $this->plurals[$key] ?? null;
    }

    /**
     * @return list<string>|null `null`, gdy wpis nie jest niepustą listą samych napisów
     */
    private static function formsFrom(mixed $entry): ?array
    {
        if (!is_array($entry) || $entry === []) {
            return null;
        }

        $forms = [];

        /** @var mixed $form */
        foreach ($entry as $form) {
            if (!is_string($form)) {
                return null;
            }

            $forms[] = $form;
        }

        return $forms;
    }
}
