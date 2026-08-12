<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Glfw;

/**
 * Wskazuje plik TTF fontu o stałej szerokości dla renderera okienkowego —
 * odpowiednik listy preferencji z kroku 08 (`ImagickCapabilityService`),
 * tyle że w ścieżkach plików, bo API wektorowe ładuje font z pliku,
 * a nie po nazwie (krok 35, D54).
 *
 * Kolejność: najpierw jawna lista preferencji (te same kroje, które preferuje
 * tor sixelowy), potem `fc-match` jako ostatnia szansa — fontconfig wie
 * o fontach użytkownika więcej niż jakakolwiek lista wpisana w kod.
 */
final class GlfwFontLocator
{
    private const PREFERRED_TTF_PATHS = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationMono-Regular.ttf',
        '/usr/share/fonts/truetype/noto/NotoSansMono-Regular.ttf',
        '/usr/share/fonts/truetype/ubuntu/UbuntuMono-R.ttf',
        '/usr/share/fonts/truetype/freefont/FreeMono.ttf',
    ];

    /** @param list<string> $candidates lista preferencji — parametr dla testów */
    public function __construct(
        private readonly array $candidates = self::PREFERRED_TTF_PATHS,
    ) {
    }

    /** Ścieżka pliku TTF albo `null`, gdy w systemie nie ma czego załadować. */
    public function locate(): ?string
    {
        foreach ($this->candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return $this->fromFontconfig();
    }

    /**
     * `fc-match` oddaje plik najlepszego dopasowania wzorca `monospace` —
     * zawsze coś, o ile fontconfig w ogóle jest. Wynik przechodzi przez
     * `is_file()`, bo odpowiedź na maszynie bez fontów potrafi być pusta.
     */
    private function fromFontconfig(): ?string
    {
        if (!function_exists('exec')) {
            return null;
        }

        $output = [];
        $exitCode = 0;

        exec('fc-match --format=%{file} monospace 2>/dev/null', $output, $exitCode);

        $path = trim(implode('', $output));

        return $exitCode === 0 && $path !== '' && is_file($path) ? $path : null;
    }
}
