<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Desktop;

use Imagick;
use ImagickDraw;
use ImagickPixel;
use LightManager\Infrastructure\Glfw\GlfwWindowService;
use LightManager\Infrastructure\Rendering\Theme;

/**
 * Ikona okna i wpis pulpitu (krok 37).
 *
 * **Dlaczego okrężną drogą.** Rozszerzenie PHP-GLFW 2.2 nie wystawia
 * `glfwSetWindowIcon`, więc bitmapy nie ma jak podać oknu wprost. Zostaje droga
 * standardowa na X11 i Waylandzie: okno przedstawia się klasą (`WM_CLASS`,
 * ustawianą w `GlfwWindowService`), a pulpit dopasowuje do niej wpis `.desktop`
 * i bierze ikonę stamtąd. Ta klasa zakłada obie połowy tej pary.
 *
 * **Dlaczego ikona rysuje się w kodzie.** Repozytorium nie nosi ani jednego
 * pliku binarnego, a ikona z ról motywu zmienia się razem z motywem —
 * zainstalowana przy włączonym Nordyku jest chłodna, przy Grafitcie ciepła.
 * Kod rysujący ikonę nie ma nic wspólnego z resztą aplikacji i **nie jest jej
 * częścią**: nie stoi w ścieżce klatki, nie zna prymitywów i uruchamia go
 * wyłącznie `bin/install-desktop-entry`, nigdy pętla.
 *
 * Ścieżki przychodzą z zewnątrz (katalog domowy, plik uruchamialny), więc
 * instalację da się sprawdzić w katalogu tymczasowym, nie tykając prawdziwego
 * pulpitu.
 */
final class DesktopEntryInstaller
{
    /** Nazwa pliku ikony i wpisu — zarazem nazwa, po którą pulpit sięga w `Icon=`. */
    public const ENTRY_NAME = 'light-manager';

    /** Rozmiary w katalogu `hicolor`. Pasek zadań bierze ten, który mu pasuje. */
    private const ICON_SIZES = [48, 64, 128, 256];

    private const CANVAS_SIZE = 256;

    /** Kafelek: wcięcie od krawędzi, promień narożnika i grubość obwódki. */
    private const TILE_INSET = 12;

    private const TILE_RADIUS = 40;

    private const TILE_STROKE = 6;

    /**
     * Wiersze listy na kafelku: pierwszy piksel, odstęp, wysokość i szerokości.
     *
     * Ikona mówi „lista z zaznaczonym wierszem”, bo to jest jedyne zdanie,
     * które da się przeczytać z kwadratu o boku 48 pikseli. Drugi wiersz jest
     * zaznaczeniem i rysuje się akcentem — jedynym nasyconym kolorem motywu.
     */
    private const ROW_TOP = 56;

    private const ROW_STEP = 42;

    private const ROW_HEIGHT = 20;

    private const ROW_LEFT = 44;

    private const ROW_WIDTHS = [168, 168, 132, 100];

    private const SELECTED_ROW = 1;

    private const DIRECTORY_MODE = 0o755;

    private const FILE_MODE = 0o644;

    public function __construct(
        private readonly Theme $theme,
        private readonly string $home,
        private readonly string $executable,
        private readonly string $name,
        private readonly string $comment,
    ) {
    }

    /**
     * Zakłada ikonę we wszystkich rozmiarach i wpis pulpitu.
     *
     * @return list<string> ścieżki zapisanych plików, w kolejności zapisu
     */
    public function install(): array
    {
        $written = [];
        $icon = $this->paintIcon();

        try {
            foreach (self::ICON_SIZES as $size) {
                $written[] = $this->writeIcon($icon, $size);
            }
        } finally {
            $icon->clear();
        }

        $written[] = $this->writeEntry();

        return $written;
    }

    /** Ścieżka wpisu pulpitu — także wtedy, gdy jeszcze nie powstał. */
    public function entryPath(): string
    {
        return $this->path(['.local', 'share', 'applications'], self::ENTRY_NAME . '.desktop');
    }

    private function paintIcon(): Imagick
    {
        $icon = new Imagick();
        $icon->newImage(self::CANVAS_SIZE, self::CANVAS_SIZE, new ImagickPixel('none'));
        $icon->setImageFormat('png');

        $tile = new ImagickDraw();
        $tile->setFillColor(new ImagickPixel($this->theme->surface));
        $tile->setStrokeColor(new ImagickPixel($this->theme->border));
        $tile->setStrokeWidth(self::TILE_STROKE);
        $tile->roundRectangle(
            self::TILE_INSET,
            self::TILE_INSET,
            self::CANVAS_SIZE - self::TILE_INSET,
            self::CANVAS_SIZE - self::TILE_INSET,
            self::TILE_RADIUS,
            self::TILE_RADIUS,
        );

        $icon->drawImage($tile);
        $tile->clear();

        $rows = new ImagickDraw();
        $rows->setStrokeColor(new ImagickPixel('none'));

        foreach (self::ROW_WIDTHS as $index => $width) {
            $top = self::ROW_TOP + $index * self::ROW_STEP;

            $rows->setFillColor(new ImagickPixel(
                $index === self::SELECTED_ROW ? $this->theme->accent : $this->theme->muted,
            ));
            $rows->roundRectangle(
                self::ROW_LEFT,
                $top,
                self::ROW_LEFT + $width,
                $top + self::ROW_HEIGHT,
                self::ROW_HEIGHT / 2,
                self::ROW_HEIGHT / 2,
            );
        }

        $icon->drawImage($rows);
        $rows->clear();

        return $icon;
    }

    private function writeIcon(Imagick $icon, int $size): string
    {
        $scaled = clone $icon;
        $scaled->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1.0);

        $path = $this->path(
            ['.local', 'share', 'icons', 'hicolor', $size . 'x' . $size, 'apps'],
            self::ENTRY_NAME . '.png',
        );

        $contents = $scaled->getImageBlob();
        $scaled->clear();

        $this->write($path, $contents);

        return $path;
    }

    /**
     * Wpis pulpitu.
     *
     * `StartupWMClass` musi się zgadzać z `WM_CLASS` okna — bez tej pary pulpit
     * pokaże ikonę w spisie programów, ale nie skojarzy z nią otwartego okna,
     * więc na pasku zadań zostanie ikona zastępcza. Stąd nazwa klasy bierze się
     * ze stałej usługi okna, a nie z powtórzonego napisu.
     */
    private function writeEntry(): string
    {
        $lines = [
            '[Desktop Entry]',
            'Type=Application',
            'Version=1.0',
            'Name=' . self::oneLine($this->name),
            'Comment=' . self::oneLine($this->comment),
            'Exec="' . str_replace('"', '\"', $this->executable) . '" --window',
            'Icon=' . self::ENTRY_NAME,
            'Terminal=false',
            'Categories=Utility;FileTools;',
            'StartupWMClass=' . GlfwWindowService::WINDOW_CLASS,
        ];

        $path = $this->entryPath();

        $this->write($path, implode("\n", $lines) . "\n");

        return $path;
    }

    private function write(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, self::DIRECTORY_MODE, true) && !is_dir($directory)) {
            throw DesktopException::forUnwritableDirectory($directory);
        }

        if (@file_put_contents($path, $contents) === false) {
            throw DesktopException::forUnwritableFile($path);
        }

        @chmod($path, self::FILE_MODE);
    }

    /** @param list<string> $directories */
    private function path(array $directories, string $file): string
    {
        return rtrim($this->home, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . implode(DIRECTORY_SEPARATOR, $directories)
            . DIRECTORY_SEPARATOR
            . $file;
    }

    /** Wpis pulpitu jest plikiem wierszowym, więc wartość z nową linią rozbiłaby go. */
    private static function oneLine(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }
}
