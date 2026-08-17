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
        /**
         * Wpis zaznaczony wielokrotnie (krok 43).
         *
         * Dwunasta rola i pierwsza dołożona od kroku 13. Powód jest w każdej
         * palecie ten sam i wynikł z obejrzenia klatki, a nie z projektu:
         * zaznaczenie potrzebuje koloru **odróżnialnego naraz od trzech** —
         * od tekstu (bo inaczej znacznik zostaje jedynym sygnałem), od akcentu
         * (bo akcentem są katalogi) i od czerwieni (bo ta znaczy „nieodwracalne”).
         * We wszystkich czterech paletach jest nim **zieleń**: kolor, którego
         * projekt nie używał dotąd do niczego, więc nie odbiera znaczenia
         * niczemu innemu.
         */
        public readonly string $marked,
        /**
         * Tło prostokąta zaznaczonego wskaźnikiem (krok 56).
         *
         * Trzynasta rola i druga dołożona od kroku 13. Kolor musi się różnić
         * naraz od **dwóch** rzeczy, i to one, a nie upodobanie, wyznaczyły go
         * w każdej palecie: od tła wiersza pod kursorem (`selection`), bo inaczej
         * prostokąt wygląda jak kilka kursorów naraz, i od tła paneli
         * (`surface`), bo inaczej nie widać go w ogóle. Wybrany został fiolet —
         * jedyna rodzina barw, której projekt nie używał dotąd do niczego, tak
         * jak w kroku 43 zieleń.
         *
         * Pismo w prostokącie bierze `selectionText`, więc kontrast liczy się
         * wobec **tej jednej wartości** i tylko wobec niej — inaczej niż przy
         * `marked`, gdzie kolorem jest samo pismo.
         */
        public readonly string $marquee,
        public readonly string $info,
        public readonly string $warning,
        public readonly string $danger,
    ) {
    }

    /**
     * Neutralne, chłodne szarości z jednym ciepłym akcentem. Akcent niesie
     * hierarchię sam z siebie, a paleta Sixela idzie na półcienie liter zamiast
     * na barwy ([00-decyzje.md](../../../docs/plans/00-decyzje.md), D25).
     *
     * **Od kroku 43 nasycone kolory są dwa, nie jeden** (D80, rozstrzygnięcie
     * 5a), i jest to świadome odstępstwo od zasady, na której ten motyw stał.
     * Powód: zaznaczenie wielokrotne potrzebuje koloru odróżnialnego naraz od
     * tekstu, od akcentu (którym są katalogi) i od czerwieni (która znaczy
     * „nieodwracalne”) — a przy jednym nasyconym kolorze takiego koloru po prostu
     * nie ma. Zieleń jest przygaszona, żeby nie konkurowała z akcentem o uwagę:
     * ma odróżniać, a nie krzyczeć.
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
            // Zasada „jeden nasycony kolor” (D25) ustępuje tu **drugiemu**
            // i jest to świadoma cena rozstrzygnięcia 5a: zieleń jest przy tym
            // przygaszona, żeby nie konkurowała z akcentem o uwagę — ma
            // odróżniać, a nie krzyczeć.
            marked: '#7fb069',
            // Fiolet jest tu ciemny i nienasycony: prostokąt zaznaczenia bywa
            // wielkości panelu, więc kolor mocny zalewałby pół klatki. Odróżnia
            // go od kursora (`#313845`) hue, a od powierzchni (`#1f2228`) —
            // jasność.
            marquee: '#4a3f6b',
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
            // Zieleń z kanonicznej palety Nord — ta sama rodzina, co reszta motywu.
            marked: '#a3be8c',
            // Fiolet z kanonicznej palety Nord (`#b48ead`) przygaszony do tła —
            // ta sama rodzina, co reszta motywu.
            marquee: '#574b6b',
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
            // Jedyny motyw, w którym kolor musi być **ciemniejszy** od tła.
            marked: '#4f7a2e',
            // Jedyny motyw, w którym prostokąt musi być **jaśniejszy** od tła —
            // i jedyny, w którym odróżnia się od kursora nie jasnością, tylko
            // ciepłotą: `selection` jest tu beżowe, zaznaczenie chłodne.
            marquee: '#cbc2dd',
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
            marked: '#81c995',
            // Motyw jest w całości niebieski, więc sam fiolet by nie wystarczył:
            // prostokąt jest tu wyraźnie **jaśniejszy** od kursora (`#2a3358`),
            // bo w tej palecie to jasność, a nie hue, niesie różnicę.
            marquee: '#473a6e',
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
