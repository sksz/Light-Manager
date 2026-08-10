<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * Język interfejsu — zarazem wartość klucza `language` w pliku konfiguracyjnym.
 *
 * `Auto` nie jest językiem, tylko poleceniem „ustal go ze środowiska”: wartość
 * domyślna, dzięki której pierwsze uruchomienie mówi tym językiem, którym mówi
 * system, a nie tym, który akurat wybrał autor. Rozstrzyganie należy do
 * `Infrastructure\I18n\TranslatorService` — to ono sięga po `LANG`, bo zmienne
 * środowiskowe są mechanizmem dostarczania, nie pojęciem aplikacji.
 *
 * Angielski jest ostatnią deską ratunku: gdy ani konfiguracja, ani środowisko
 * nie wskazują niczego rozpoznawalnego, a także gdy w katalogu wybranego języka
 * zabraknie klucza.
 */
enum Language: string
{
    case Auto = 'auto';
    case Polish = 'pl';
    case English = 'en';

    /** Język, na który schodzi każda nierozstrzygnięta ścieżka wyboru. */
    public const FALLBACK = self::English;

    /**
     * Języki mające własny katalog napisów — w odróżnieniu od `Auto`, który
     * żadnych napisów nie niesie.
     *
     * @return list<self>
     */
    public static function catalogued(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $case): bool => $case !== self::Auto));
    }

    /** Klucz napisu z nazwą tej pozycji na ekranie ustawień. */
    public function labelKey(): string
    {
        return 'settings.language.' . $this->value;
    }
}
