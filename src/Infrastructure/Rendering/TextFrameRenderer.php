<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Rendering;

use LightManager\Application\Port\FrameRendererPort;
use LightManager\Application\Ui\Frame;
use LightManager\Application\Ui\FrameText;
use LightManager\Application\Ui\Role;
use LightManager\Infrastructure\Terminal\TerminalService;
use LightManager\Infrastructure\Terminal\TerminalSizeService;

/**
 * Rysuje klatkę zwykłym tekstem z kodami ANSI — używany, gdy terminal albo
 * ImageMagick nie obsługują Sixela.
 *
 * Te same płaszczyzny i te same prymitywy co w trybie graficznym; różnica jest
 * w **degradacji kształtów**, nie w treści. Obwódka staje się znakami
 * rysunkowymi, nawias narożny i suwak znikają (pół kolumny w siatce znakowej nie
 * istnieje), a miniatura zostaje samym podpisem. Do kroku 18 tryb tekstowy miał
 * własną, niezależną ścieżkę rysowania dla każdego elementu klatki i to właśnie
 * tam najczęściej rozjeżdżał się z trybem graficznym.
 *
 * **Od kroku 56 tej degradacji tu nie ma** — jest w `Application\Ui\FrameText`,
 * bo o to samo, co tryb tekstowy, pyta zaznaczanie treści we wszystkich trzech
 * torach: *jaki znak stoi w tej komórce*. Renderer został z tym, co naprawdę
 * jest jego: **paletą i bajtami**. Zysk widać w rachunku, a nie w liczbie linii
 * — dwie kopie odwzorowania prymitywu na znak rozjechałyby się przy pierwszym
 * nowym kształcie, a rozjazd byłby niewidoczny.
 *
 * W przeciwieństwie do wariantu graficznego tekst nie zamalowuje całego okna,
 * więc ekran trzeba wyczyścić jawnie.
 */
final class TextFrameRenderer implements FrameRendererPort
{
    private const CURSOR_HOME = "\e[H";

    private const CLEAR_SCREEN = "\e[2J";

    private Theme $theme;

    public function __construct(
        private readonly AnsiPalette $palette,
    ) {
        $this->theme = ThemeService::getInstance()->active();
    }

    public function render(Frame $frame): void
    {
        // Motyw nie jest wstrzykiwany raz przy budowie, tylko pobierany przy
        // każdej klatce — inaczej zmiana palety na ekranie ustawień wymagałaby
        // restartu. Rozmiar czytany co klatkę z tego samego powodu (reguła 11f).
        $size = TerminalSizeService::getInstance()->size();

        TerminalService::getInstance()->write($this->encode(
            $this->composeBuffer($frame, ThemeService::getInstance()->active(), $size->rows, $size->columns),
        ));
    }

    /**
     * Krok pierwszy: **prymitywy → bufor komórek**.
     *
     * Publiczny, bo tor tekstowy `bin/render-bench` mierzy go osobno od
     * składania bajtów (krok 38, rozstrzygnięcie nr 3). To jedyna zmiana
     * produkcyjna zrobiona w tym kroku dla pomiaru i jest tym samym szwem, co
     * rozbicie `SixelFrameEncoder` w kroku 16: **zegar zostaje po stronie
     * narzędzia**, w rendererze nie ma ani jednego wywołania pomiarowego (D28).
     *
     * Motyw przychodzi parametrem zamiast z singletonu, żeby narzędzie mogło
     * zmierzyć motyw wskazany osią `--theme`, a nie ten akurat ustawiony.
     * Rozmiar też — bo pomiar dzieje się w siatce z osi `--grid`, a nie
     * w oknie terminala, w którym akurat stoi.
     */
    public function composeBuffer(Frame $frame, Theme $theme, int $rows, int $columns): CellBuffer
    {
        $this->theme = $theme;

        return new CellBuffer(FrameText::of($frame, $rows, $columns), $this->colors());
    }

    /**
     * Krok drugi: **bufor komórek → bajty**, dokładnie te, które lecą na
     * wyjście, wraz z ustawieniem kursora i wyczyszczeniem ekranu.
     *
     * Sekwencje sterujące są częścią zwracanego napisu, a nie dopisywane po
     * pomiarze — inaczej mierzony rozmiar bloba różniłby się od tego, co
     * naprawdę idzie do terminala.
     */
    public function encode(CellBuffer $buffer): string
    {
        return self::CURSOR_HOME . self::CLEAR_SCREEN . $buffer->toAnsi($this->palette);
    }

    /**
     * Tabela motywu spisana **raz na klatkę**, a nie pytana przy każdej komórce.
     *
     * Ról jest trzynaście, komórek w dużym oknie kilkanaście tysięcy — mapa
     * kosztuje przez to tyle, co trzynaście gałęzi `match`a, a bufor pyta o kolor
     * wyłącznie tam, gdzie zmienia się rola.
     *
     * @return array<string, string>
     */
    private function colors(): array
    {
        $colors = [];

        foreach (Role::cases() as $role) {
            $colors[$role->name] = $this->colorOf($role);
        }

        return $colors;
    }

    private function colorOf(Role $role): string
    {
        return match ($role) {
            Role::Background => $this->theme->background,
            Role::Surface => $this->theme->surface,
            Role::Border => $this->theme->border,
            Role::Text => $this->theme->text,
            Role::Muted => $this->theme->muted,
            Role::Accent => $this->theme->accent,
            Role::Selection => $this->theme->selection,
            Role::SelectionText => $this->theme->selectionText,
            Role::Marked => $this->theme->marked,
            Role::Marquee => $this->theme->marquee,
            Role::Info => $this->theme->info,
            Role::Warning => $this->theme->warning,
            Role::Danger => $this->theme->danger,
        };
    }
}
