<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\ListView;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScreenZone;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Ekran zastępczy dla toru taktu pętli (krok 38): lista plików bez systemu
 * plików.
 *
 * Sąsiaduje z `ScenarioFactory` i podlega tym samym trzem regułom —
 * **determinizm** (nazwy z licznika), **wierność** (te same komponenty rdzenia,
 * co w aplikacji) i **brak katalogu napisów** (długość napisu w znakach wchodzi
 * do wyniku, D33). Różnica jest jedna, za to zasadnicza: fabryka oddaje gotowe
 * prymitywy, a ten ekran przechodzi **całą drogę składania klatki** —
 * `FrameComposer` pyta go o strefy, dzieli okno, oprawia panele i dopiero potem
 * woła `draw()`. To właśnie ta droga jest tu mierzona.
 *
 * Ekran mieszka w `Infrastructure\Diagnostics`, choć implementuje interfejs
 * z `Presentation` — tym samym precedensem, co `ScenarioFactory`, która sięga po
 * komponenty rdzenia. Powód jest ten sam: pomiar ma iść przez **prawdziwe**
 * klocki, a nie przez ich kopię.
 *
 * Klawisz naprawdę zmienia stan (przesuwa zaznaczenie i okno przewijania), bo
 * faza „stan” mierzona na ekranie, który klawisz ignoruje, byłaby kolumną zer.
 */
final class LoopScenarioScreen implements ScreenInterface
{
    private const PATH = '/home/uzytkownik/projekty/light-manager/src/Infrastructure';

    /** Tyle wpisów ma udawany katalog — więcej, niż zmieści się w oknie. */
    private const ENTRIES = 240;

    private int $selected = 0;

    private readonly ScrollWindow $window;

    public function __construct()
    {
        $this->window = new ScrollWindow();
    }

    public function id(): string
    {
        return 'diagnostics.loop';
    }

    public function labelKey(): string
    {
        return 'layout.zone.path';
    }

    public function header(): ScreenZone
    {
        return new ScreenZone('layout.zone.path', new Label(self::PATH . '  —  12/240'));
    }

    public function draw(Rect $bounds): array
    {
        $rows = [];
        $offset = $this->window->keepVisible($this->selected, self::ENTRIES, $bounds->rows);

        for ($index = $offset; $index < min(self::ENTRIES, $offset + max(1, $bounds->rows)); ++$index) {
            $directory = $index % 6 === 0;
            $rows[] = new ListRow(
                sprintf('%s-%04d%s', $directory ? 'katalog' : 'plik', $index, $directory ? '/' : '.txt'),
                $directory ? '' : sprintf('%d,%d kB', 1 + $index % 900, $index % 10),
                $directory ? Role::Accent : Role::Text,
            );
        }

        return (new ListView(
            $rows,
            $this->selected - $offset,
            $this->window->position(self::ENTRIES, min($bounds->rows, count($rows))),
        ))->draw($bounds);
    }

    public function bindings(): array
    {
        return [KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move')];
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        $this->selected = match ($key->key) {
            Key::ArrowDown => min(self::ENTRIES - 1, $this->selected + 1),
            Key::ArrowUp => max(0, $this->selected - 1),
            default => $this->selected,
        };

        return ScreenOutcome::stay();
    }
}
