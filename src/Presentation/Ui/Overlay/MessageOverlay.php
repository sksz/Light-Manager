<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Overlay;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Rect;
use LightManager\Presentation\Ui\Component\Dialog;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * Okno z treścią do przeczytania — opis pliku i wszystko, co po nim przyjdzie.
 *
 * Zachowanie jest dokładnie takie, jak przed krokiem 19: **dowolny klawisz
 * zamyka**, łącznie z `F10`. Wygląda to na przeoczenie, a jest regułą: okno
 * niosące jedno zdanie nie ma czego robić z klawiszem, a użytkownik, który
 * właśnie je zobaczył, chce się go pozbyć — nie zastanawiać się, który klawisz
 * to zrobi.
 *
 * Krok 19 nie zmienia tu niczego poza opakowaniem: `Dialog` był dotąd trzymany
 * wprost w stanie pętli, a dziś jest oknem nakładanym jak każde inne.
 */
final class MessageOverlay implements OverlayInterface
{
    /** Oddech wokół okna: dwa wiersze i cztery kolumny na oprawę klatki. */
    private const MARGIN_ROWS = 2;

    private const MARGIN_COLUMNS = 4;

    public function __construct(
        private readonly Dialog $dialog,
    ) {
    }

    public function id(): string
    {
        return 'message';
    }

    /**
     * Okno staje pośrodku, w rozmiarze, o który poprosiło — ale nie większym niż
     * okno terminala. Wyśrodkowanie liczy się w komórkach, więc tryb tekstowy
     * stawia je dokładnie tam, gdzie graficzny.
     */
    public function bounds(int $rows, int $columns): Rect
    {
        $size = $this->dialog->size();
        $height = min($size->rows, max(1, $rows - self::MARGIN_ROWS));
        $width = min($size->columns, max(1, $columns - self::MARGIN_COLUMNS));

        return new Rect(
            max(0, intdiv($rows - $height, 2)),
            max(0, intdiv($columns - $width, 2)),
            $height,
            $width,
        );
    }

    public function draw(Rect $bounds): array
    {
        return $this->dialog->draw($bounds);
    }

    public function bindings(): array
    {
        return [KeyBinding::of([Key::Escape, Key::Enter], 'command.key.dismiss')];
    }

    public function handle(KeyPress $key): OverlayOutcome
    {
        return OverlayOutcome::close();
    }
}
