<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\I18n;

use LightManager\Application\Dto\Language;

/**
 * Która forma napisu przypada danej liczbie.
 *
 * Reguła jest własnością języka, nie napisu, więc mieszka tutaj, a nie w pliku
 * katalogu: gdyby siedziała przy każdym napisie w postaci funkcji, każdy nowy
 * tekst mnogi powtarzałby ten sam warunek, a plik napisów przestałby być czystą
 * tablicą, którą da się sprawdzić PHPStanem.
 */
enum PluralRule
{
    /** Dwie formy: pojedyncza dla jedynki, mnoga dla całej reszty. */
    case Germanic;

    /** Trzy formy: 1; 2–4 (poza 12–14); reszta, w tym zero. */
    case Slavic;

    public static function forLanguage(Language $language): self
    {
        return match ($language) {
            Language::Polish => self::Slavic,
            default => self::Germanic,
        };
    }

    public function forms(): int
    {
        return match ($this) {
            self::Germanic => 2,
            self::Slavic => 3,
        };
    }

    /** Indeks formy na liście napisów; liczby ujemne traktujemy jak ich wartość bezwzględną. */
    public function formFor(int $count): int
    {
        $count = abs($count);

        return match ($this) {
            self::Germanic => $count === 1 ? 0 : 1,
            self::Slavic => $this->slavicForm($count),
        };
    }

    private function slavicForm(int $count): int
    {
        if ($count === 1) {
            return 0;
        }

        $tens = $count % 10;
        $hundreds = $count % 100;

        // 12–14 wypada z grupy „2, 3, 4” mimo końcówki: dwanaście plików, nie pliki.
        if ($tens >= 2 && $tens <= 4 && ($hundreds < 12 || $hundreds > 14)) {
            return 1;
        }

        return 2;
    }
}
