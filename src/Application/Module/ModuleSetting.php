<?php

declare(strict_types=1);

namespace LightManager\Application\Module;

/**
 * Jedna pozycja w zakładce ustawień modułu — **opisana danymi, nie kodem** (P4).
 *
 * Dzięki temu ekran ustawień rysuje zakładkę modułu tym samym kodem, co zakładki
 * rdzenia, moduł nie musi wiedzieć nic o rysowaniu, a zapis wartości ma jedno
 * miejsce w całej aplikacji. Cena jest jedna i świadoma: moduł nie dostaje
 * wywołania zwrotnego przy zmianie, więc nie ma jak sprawdzić wartości po
 * swojemu — sprawdza ją rdzeń, według `pattern` i `maxLength` (P13).
 *
 * Wartość odczytana z pliku konfiguracyjnego przechodzi przez `valueFrom()`:
 * plik ruszony ręcznie może zawierać cokolwiek, a pozycja ma z tego wyjść
 * z wartością z zakresu albo z domyślną, nigdy z wyjątkiem.
 */
final class ModuleSetting
{
    /** Znak zasłony — ten sam, którym maskuje `TextInput` od kroku 48. */
    private const MASK = '*';

    /** Długość zasłony: stała, żeby nie zdradzała długości sekretu. */
    private const MASK_LENGTH = 8;

    /** @param list<string|int> $choices dopuszczalne wartości — dla `Choice` i `Number` */
    public function __construct(
        /** Klucz w podprzestrzeni `modules.<id>`. */
        public readonly string $key,
        /** Klucz katalogu napisów: `module.<id>.setting.<klucz>`. */
        public readonly string $labelKey,
        public readonly ModuleSettingKind $kind,
        public readonly array $choices,
        public readonly bool|int|string $default,
        /** Wzorzec wartości — wyłącznie dla `Text`; sprawdza go rdzeń (P13). */
        public readonly ?string $pattern = null,
        /** Długość maksymalna w znakach — wyłącznie dla `Text`. */
        public readonly ?int $maxLength = null,
        /**
         * Czy wartość zasłaniać przy pokazywaniu — **wyłącznie dla `Text`**
         * (krok 54, D94 nr 7).
         *
         * **Znacznik przy istniejącym rodzaju, a nie piąty rodzaj**, i to jest
         * cała różnica w cenie: rodzaj to osobna droga w ekranie ustawień, więc
         * kosztowałby nową gałąź rysowania, nową gałąź klawiszy i nowy przypadek
         * w `valueFrom()`. Maskowanie nie zmienia **niczego** poza tym, co widać:
         * wartość zapisuje się, sprawdza i przesuwa dokładnie tak samo.
         *
         * **Nie jest szyfrowaniem i nie udaje go.** Wartość leży w pliku
         * konfiguracyjnym jawnie, więc znacznik broni wyłącznie przed spojrzeniem
         * — przed zrzutem ekranu, przed udostępnionym pulpitem, przed sąsiadem
         * przy biurku. Pierwszym użytkownikiem jest token rejestru obrazów
         * w module Dockera, który niesie uprawnienie `write:packages`.
         */
        public readonly bool $masked = false,
    ) {
    }

    /** Przełącznik dwustanowy: dwie wartości, obie strzałki robią to samo. */
    public static function toggle(string $key, string $labelKey, bool $default): self
    {
        return new self($key, $labelKey, ModuleSettingKind::Toggle, [], $default);
    }

    /** @param list<string> $choices */
    public static function choice(string $key, string $labelKey, array $choices, string $default): self
    {
        return new self($key, $labelKey, ModuleSettingKind::Choice, $choices, $default);
    }

    /** @param list<int> $choices */
    public static function number(string $key, string $labelKey, array $choices, int $default): self
    {
        return new self($key, $labelKey, ModuleSettingKind::Number, $choices, $default);
    }

    public static function text(
        string $key,
        string $labelKey,
        string $default = '',
        ?string $pattern = null,
        ?int $maxLength = null,
    ): self {
        return new self($key, $labelKey, ModuleSettingKind::Text, [], $default, $pattern, $maxLength);
    }

    /**
     * Pozycja tekstowa, której wartości **nie widać** — patrz `$masked`.
     *
     * Konstruktor osobny, a nie parametr w `text()`, bo maskowanie ma być
     * **decyzją**, a nie ósmym argumentem, który ktoś przeoczy przy dopisywaniu
     * pozycji z sekretem.
     */
    public static function secret(
        string $key,
        string $labelKey,
        ?string $pattern = null,
        ?int $maxLength = null,
    ): self {
        return new self($key, $labelKey, ModuleSettingKind::Text, [], '', $pattern, $maxLength, masked: true);
    }

    /**
     * Wartość w postaci, w jakiej wolno ją pokazać — zasłonięta, gdy pozycja
     * jest sekretem.
     *
     * Zasłona ma **stałą długość**, a nie długość wartości: liczba gwiazdek
     * równa liczbie znaków mówi o sekrecie tyle, ile mówić nie musi. Pustka
     * zostaje pustką, bo „nic tu nie ma" nie jest sekretem.
     */
    public function shown(bool|int|string $value): bool|int|string
    {
        if (!$this->masked || !is_string($value) || $value === '') {
            return $value;
        }

        return str_repeat(self::MASK, self::MASK_LENGTH);
    }

    /**
     * Wartość gotowa do pokazania i do zapisu, wyprowadzona z tego, co stało
     * w pliku. Wartość nieodpowiedniego typu albo spoza listy wraca do domyślnej.
     */
    public function valueFrom(mixed $stored): bool|int|string
    {
        return match ($this->kind) {
            ModuleSettingKind::Toggle => is_bool($stored) ? $stored : $this->default,
            ModuleSettingKind::Number => is_int($stored) && $this->allows($stored) ? $stored : $this->default,
            ModuleSettingKind::Choice => is_string($stored) && $this->allows($stored) ? $stored : $this->default,
            ModuleSettingKind::Text => is_string($stored) && $this->accepts($stored) ? $stored : $this->default,
        };
    }

    /**
     * Sąsiednia wartość — to, co robi strzałka pozioma. Pozycja tekstowa nie
     * przesuwa się wcale: jej wartości nie da się ustawić w szereg.
     */
    public function shifted(bool|int|string $current, int $direction): bool|int|string
    {
        if ($this->kind === ModuleSettingKind::Toggle) {
            return !(is_bool($current) ? $current : (bool) $this->default);
        }

        if ($this->kind === ModuleSettingKind::Text || $this->choices === []) {
            return $current;
        }

        $position = array_search($current, $this->choices, true);
        $count = count($this->choices);

        if ($position === false) {
            return $this->choices[0];
        }

        return $this->choices[($position + ($direction < 0 ? -1 : 1) + $count) % $count];
    }

    /**
     * Czy napis przechodzi walidację pozycji tekstowej (P13).
     *
     * Pusta wartość przechodzi zawsze — „nie chcę nic tu wpisywać” musi dać się
     * wyrazić także wtedy, gdy wzorzec żąda konkretnego kształtu.
     */
    public function accepts(string $value): bool
    {
        if ($value === '') {
            return true;
        }

        if ($this->maxLength !== null && mb_strlen($value) > $this->maxLength) {
            return false;
        }

        return $this->pattern === null || preg_match($this->pattern, $value) === 1;
    }

    private function allows(int|string $value): bool
    {
        return in_array($value, $this->choices, true);
    }
}
