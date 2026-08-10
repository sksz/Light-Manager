<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Rendering;

use LightManager\Domain\ValueObject\MessageTone;

/**
 * Kolory interfejsu opisane rolami, nie miejscami użycia.
 *
 * Do kroku 13 wartości siedziały jako stałe w enkoderze Sixela, a tryb tekstowy
 * miał własny, niezależny zestaw kodów ANSI — ten sam błękit pełnił naraz rolę
 * nagłówka, katalogu, obwódki okienka i tła zaznaczenia. Rola rozstrzyga to raz:
 * renderery pytają o `accent`, nie o „ten niebieski”.
 */
final class Theme
{
    private function __construct(
        /** Tło całej klatki. */
        public readonly string $background,
        /** Wypełnienie paneli i belki stanu — o stopień jaśniejsze od tła. */
        public readonly string $surface,
        /**
         * Obwódki paneli, linie rozdzielające, szyna paska przewijania.
         *
         * Ton jest jaśniejszy, niż wynikałoby z projektu na ekranie: obwódka ma
         * grubość jednego piksela i przechodzi przez kwantyzację palety, więc
         * odcień „ledwie jaśniejszy od tła” po prostu z klatki znika
         * (zmierzone na renderze, [00-decyzje.md](../../../docs/plans/00-decyzje.md), D27).
         */
        public readonly string $border,
        /** Nazwy plików i treść. */
        public readonly string $text,
        /** Rozmiary, podpisy, etykiety stref, podpowiedzi klawiszy. */
        public readonly string $muted,
        /** Katalogi, nawiasy narożne, suwak, krawędź zaznaczenia. */
        public readonly string $accent,
        /** Tło zaznaczonego wiersza. */
        public readonly string $selection,
        /** Tekst zaznaczonego wiersza. */
        public readonly string $selectionText,
        public readonly string $info,
        public readonly string $warning,
        public readonly string $danger,
    ) {
    }

    /**
     * Neutralne, chłodne szarości z jednym ciepłym akcentem. Akcent jest tu
     * jedynym nasyconym kolorem, więc niesie hierarchię sam z siebie, a paleta
     * Sixela idzie na półcienie liter zamiast na barwy
     * ([00-decyzje.md](../../../docs/plans/00-decyzje.md), D25).
     */
    public static function grafit(): self
    {
        return new self(
            background: '#16181c',
            surface: '#1f2228',
            border: '#4a515e',
            text: '#dcdfe4',
            muted: '#8d939d',
            accent: '#d9a441',
            selection: '#313845',
            selectionText: '#f2f4f7',
            info: '#8d939d',
            warning: '#d9a441',
            danger: '#e0645c',
        );
    }

    /**
     * Chłodna, niskokontrastowa — odcienie nocnego nieba z lodowatym akcentem.
     * Dla kogoś, komu Grafit wydaje się zbyt surowy; kontrast tekstu wobec tła
     * jest tu niższy, więc paleta męczy oczy mniej, ale i mniej krzyczy.
     */
    public static function nordyk(): self
    {
        return new self(
            background: '#2e3440',
            surface: '#3b4252',
            border: '#5d6b85',
            text: '#e5e9f0',
            muted: '#9aa5b8',
            accent: '#88c0d0',
            selection: '#434c5e',
            selectionText: '#eceff4',
            info: '#81a1c1',
            warning: '#ebcb8b',
            danger: '#bf616a',
        );
    }

    /**
     * Jasny motyw dzienny — jedyny w katalogu, w którym tło jest jaśniejsze od
     * tekstu. Role zostają te same, odwraca się wyłącznie kierunek jasności:
     * obwódka musi być **ciemniejsza** od tła, inaczej ginie tak samo, jak
     * ginęła zbyt ciemna obwódka na tle Grafitu (D27).
     */
    public static function papier(): self
    {
        return new self(
            background: '#f4f1ea',
            surface: '#e9e4d9',
            border: '#a89e8c',
            text: '#2f2b26',
            muted: '#6f6759',
            accent: '#b06a2c',
            selection: '#ddd5c4',
            selectionText: '#1b1814',
            info: '#55606e',
            warning: '#9a6c10',
            danger: '#a8352b',
        );
    }

    /**
     * Kierunek sprzed kroku 13 — granat z błękitem `#8ab4f8` — ale z rolami
     * rozdzielonymi, zamiast jednego błękitu pełniącego cztery naraz.
     */
    public static function indygo(): self
    {
        return new self(
            background: '#151a2e',
            surface: '#1e2540',
            border: '#4c5885',
            text: '#dde3f4',
            muted: '#8b95b8',
            accent: '#8ab4f8',
            selection: '#2a3358',
            selectionText: '#f0f4ff',
            info: '#8b95b8',
            warning: '#f2c14e',
            danger: '#f28b82',
        );
    }

    public function colorForTone(MessageTone $tone): string
    {
        return match ($tone) {
            MessageTone::Info => $this->info,
            MessageTone::Warning => $this->warning,
            MessageTone::Error => $this->danger,
        };
    }
}
