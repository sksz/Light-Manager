<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Imagick;

use Imagick;
use ImagickDraw;
use ImagickPixel;
use LightManager\Infrastructure\Rendering\Theme;

/**
 * Paleta Sixela zbudowana z góry z kolorów motywu — wejście do `remapImage()`.
 *
 * Klatka bez bitmapy zawiera **wyłącznie** kolory motywu i ich półcienie, więc
 * paleta jest znana, zanim cokolwiek narysujemy. Analizowanie obrazu po fakcie
 * (`quantizeImage()`) jest tu robotą wykonywaną na próżno: kosztuje 47 ms na
 * każdą klatkę i — co gorsza — **przesuwa kolory, które sami wybraliśmy**.
 * Zmierzone na klatce listy: tło ciemniało, a akcent `#d9a441` lądował o 95
 * jednostek RGB od zaprojektowanego odcienia, i to niezależnie od tego, czy
 * budżet palety wynosił 16 czy 128 kolorów.
 *
 * **Półcienie muszą być w palecie.** `remapImage()` przyciąga każdy piksel do
 * najbliższego wpisu, więc paleta złożona z samych jedenastu ról zamieniłaby
 * wygładzone łuki narożników w schodki — czyli cofnęłaby decyzję D27, która
 * wygładzanie obrysów włączyła właśnie po to, by narożniki były zaokrąglone.
 * Stąd rampy: dla każdej pary „podłoże + kreska”, która w klatce naprawdę
 * występuje, paleta niesie kilka kroków pośrednich.
 *
 * Budżet z konfiguracji (`paletteColors`) nadal coś znaczy: role mieszczą się
 * w nim zawsze, a to, co zostanie, idzie na rampy — najpierw na półcienie
 * środkowe, potem na skrajne. Dzięki temu mniejsza paleta degraduje wygładzanie
 * stopniowo, zamiast wywracać kolory interfejsu.
 *
 * **Klatka z miniaturą dostaje paletę hybrydową** (`forThemeWithImage()`): te
 * same wpisy motywu co zawsze plus barwy policzone z samego zdjęcia. Do tego
 * miejsca podgląd szedł osobną drogą — kwantyzacją adaptacyjną całego płótna —
 * i to ona przemalowywała interfejs w chwili najechania na plik graficzny.
 * Dziś obie drogi kończą się tym samym `remapImage()`, a różnią wyłącznie tym,
 * czy do palety dopisane są kolory zdjęcia.
 */
final class ThemePalette
{
    /**
     * Kroki rampy w kolejności ważności: półcień środkowy niesie najwięcej
     * kształtu, więc przy ciasnym budżecie zostaje jako ostatni.
     *
     * @var list<float>
     */
    private const RAMP_STEPS = [0.5, 0.25, 0.75];

    /** Ile wpisów palety zajmują same role motywu. */
    private const ROLE_COUNT = 11;

    /** @var array<string, Imagick> gotowe palety, klucz: podpis motywu i budżetu */
    private array $palettes = [];

    /** @var array<string, list<string>> wpisy motywu, klucz jak wyżej */
    private array $entries = [];

    /**
     * Ostatnia paleta hybrydowa. Jedna, nie mapa: podgląd pokazuje w danej
     * chwili jeden plik, więc paleta poprzedniego nie ma po co żyć — tak samo
     * jak zapamiętana płaszczyzna spodnia w enkoderze.
     */
    private ?Imagick $hybrid = null;

    private string $hybridKey = '';

    /**
     * Obraz palety dla motywu i budżetu kolorów.
     *
     * Wynik jest pamiętany: paleta zmienia się wyłącznie razem z motywem albo
     * ustawieniem, a klatka powstaje trzydzieści razy na sekundę.
     */
    public function forTheme(Theme $theme, int $budget): Imagick
    {
        return $this->palettes[self::signatureOf($theme) . '|' . $budget] ??= self::build(
            $this->entriesOf($theme, $budget),
        );
    }

    /**
     * Paleta dla klatki z miniaturą: wpisy motywu **bez zmiany** plus barwy
     * policzone z samego zdjęcia.
     *
     * Kolejność jest tu istotna. Wpisy motywu idą pierwsze i żaden z nich nie
     * ustępuje kolorowi zdjęcia — dzięki temu `remapImage()` odwzorowuje każdą
     * rolę na siebie samą, z odległością zero, niezależnie od tego, co pokazuje
     * podgląd. Barwy zdjęcia dopisują się za nimi i przy okazji gubią te, które
     * motyw już niesie, więc paleta nie przekracza sufitu, na jaki miniatura
     * była kwantyzowana.
     *
     * @param list<string> $imageColors barwy miniatury, w zapisie `#rrggbb`
     */
    public function forThemeWithImage(Theme $theme, int $budget, array $imageColors): Imagick
    {
        $key = self::signatureOf($theme) . '|' . $budget . '|' . implode(',', $imageColors);

        if ($this->hybrid === null || $this->hybridKey !== $key) {
            $this->hybrid?->clear();

            $this->hybrid = self::build(array_values(array_unique(
                array_merge($this->entriesOf($theme, $budget), $imageColors),
            )));
            $this->hybridKey = $key;
        }

        return $this->hybrid;
    }

    /**
     * Ile wpisów zostaje miniaturze, gdy motyw weźmie swoje.
     *
     * `$total` to sufit palety całej klatki z podglądem. Miniatura kwantyzowana
     * jest dokładnie na tyle kolorów, więc paleta hybrydowa mieści się w suficie
     * bez obcinania czegokolwiek po fakcie.
     */
    public function roomForImage(Theme $theme, int $budget, int $total): int
    {
        return max(1, $total - count($this->entriesOf($theme, $budget)));
    }

    /** @return list<string> */
    private function entriesOf(Theme $theme, int $budget): array
    {
        return $this->entries[self::signatureOf($theme) . '|' . $budget] ??= self::entriesFor($theme, $budget);
    }

    /**
     * Kolory palety w kolejności, w jakiej trafiają do obrazu.
     *
     * Wydzielone z budowania obrazu, żeby dało się sprawdzić testem, że role
     * mieszczą się w budżecie zawsze, a rampy ustępują po kolei.
     *
     * @return list<string>
     */
    public static function entriesFor(Theme $theme, int $budget): array
    {
        $roles = self::rolesOf($theme);
        $room = max(0, $budget - count($roles));

        if ($room === 0) {
            return $roles;
        }

        $ramps = [];

        // Kolejność pętli decyduje o tym, co ginie przy ciasnym budżecie:
        // najpierw każda para dostaje półcień środkowy, dopiero potem skrajne.
        foreach (self::RAMP_STEPS as $step) {
            foreach (self::grounds($theme) as $ground) {
                foreach (self::inks($theme) as $ink) {
                    $ramps[] = self::blend($ground, $ink, $step);
                }
            }
        }

        $ramps = array_values(array_diff(array_unique($ramps), $roles));

        return array_merge($roles, array_slice($ramps, 0, $room));
    }

    /** @return list<string> */
    private static function rolesOf(Theme $theme): array
    {
        return array_values(array_unique([
            $theme->background,
            $theme->surface,
            $theme->border,
            $theme->text,
            $theme->muted,
            $theme->accent,
            $theme->selection,
            $theme->selectionText,
            $theme->info,
            $theme->warning,
            $theme->danger,
        ]));
    }

    /**
     * Kolory, na których cokolwiek leży — półcień powstaje między nimi a kreską.
     *
     * @return list<string>
     */
    private static function grounds(Theme $theme): array
    {
        return [$theme->background, $theme->selection, $theme->surface];
    }

    /**
     * Kolory, którymi się rysuje. `info`, `warning` i `danger` są tu pominięte:
     * komunikat jest tekstem, a wygładzanie tekstu domyślnie nie działa.
     *
     * @return list<string>
     */
    private static function inks(Theme $theme): array
    {
        return [$theme->border, $theme->accent, $theme->text, $theme->muted, $theme->selectionText];
    }

    private static function blend(string $from, string $to, float $ratio): string
    {
        [$fromRed, $fromGreen, $fromBlue] = self::channelsOf($from);
        [$toRed, $toGreen, $toBlue] = self::channelsOf($to);

        return sprintf(
            '#%02x%02x%02x',
            (int) round($fromRed + ($toRed - $fromRed) * $ratio),
            (int) round($fromGreen + ($toGreen - $fromGreen) * $ratio),
            (int) round($fromBlue + ($toBlue - $fromBlue) * $ratio),
        );
    }

    /** @return array{int, int, int} */
    private static function channelsOf(string $hex): array
    {
        $value = (int) hexdec(ltrim($hex, '#'));

        return [($value >> 16) & 0xFF, ($value >> 8) & 0xFF, $value & 0xFF];
    }

    /** @param list<string> $entries */
    private static function build(array $entries): Imagick
    {
        $palette = new Imagick();
        $palette->newImage(max(1, count($entries)), 1, new ImagickPixel($entries[0] ?? '#000000'));

        $draw = new ImagickDraw();

        foreach ($entries as $column => $hex) {
            $draw->setFillColor(new ImagickPixel($hex));
            $draw->point($column, 0);
        }

        $palette->drawImage($draw);

        return $palette;
    }

    private static function signatureOf(Theme $theme): string
    {
        return implode(',', self::rolesOf($theme));
    }

    /** Liczba ról — do testów sprawdzających, ile budżetu zostaje na rampy. */
    public static function roleCount(): int
    {
        return self::ROLE_COUNT;
    }
}
