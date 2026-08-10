<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Co dokładnie znajdzie się na mierzonej klatce.
 *
 * Scenariusze są dobrane tak, żeby koszt dawało się **odjąć**: `Text` bez
 * chromu, `Chrome` bez wierszy listy, `ChromeWithText` jako pełna klatka. Krok
 * 13 pomylił się właśnie na braku takiego rozdziału — koszt wygładzania podano
 * łącznie dla tekstu i obrysów, więc obrysy wyłączono niepotrzebnie (D27).
 *
 * Wartość przypadku jest tokenem wiersza poleceń (`--scenariusz=text`), a nie
 * napisem dla użytkownika — ten idzie przez katalog pod `labelKey()`.
 */
enum Scenario: string
{
    /** Koszt bazowy: alokacja płótna, tło, kwantyzacja, kodowanie. */
    case Empty = 'empty';

    /** Sama rasteryzacja liter, bez jednej kreski chromu. */
    case Text = 'text';

    /** Cztery panele z łukami, nawiasami i etykietami — bez treści. */
    case Chrome = 'chrome';

    /** Pełna klatka przeglądarki plików: panele plus wiersze listy. */
    case ChromeWithText = 'chrome-text';

    /** Jak `Text`, ale każdy wiersz zaznaczony — różnica to koszt pasków. */
    case Selection = 'selection';

    /** Sama szyna z suwakiem, bez wierszy. */
    case Scrollbar = 'scrollbar';

    /** Pełna klatka z miniaturą w pasie podglądu i paletą 256 kolorów. */
    case Thumbnail = 'thumbnail';

    /** Pełna klatka z okienkiem modalnym narysowanym na wierzchu. */
    case Popup = 'popup';

    /** Pełna klatka z otwartym oknem komend: pasek podpowiedzi i pole wpisywania. */
    case Command = 'command';

    /** @return list<self> kolejność wydruku: od najtańszego do najbogatszego */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * @param list<string> $names
     *
     * @return list<self>
     *
     * @throws DiagnosticsException gdy któraś nazwa nie ma pokrycia w enumie
     */
    public static function fromNames(array $names): array
    {
        $scenarios = [];

        foreach ($names as $name) {
            $scenario = self::tryFrom($name);

            if ($scenario === null) {
                throw DiagnosticsException::forUnknownScenario($name);
            }

            $scenarios[] = $scenario;
        }

        return $scenarios;
    }

    public function labelKey(): string
    {
        return 'bench.scenario.' . $this->value;
    }

    /** Czy scenariusz wymaga układu panelowego (a nie gołego płótna). */
    public function needsChrome(): bool
    {
        return match ($this) {
            self::Chrome, self::ChromeWithText, self::Thumbnail, self::Popup, self::Command => true,
            default => false,
        };
    }
}
