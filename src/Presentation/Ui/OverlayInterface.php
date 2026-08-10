<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Rect;

/**
 * Okno nakładane nad aktywnym ekranem.
 *
 * Do kroku 19 „okno modalne” było napisem na wierzchu: `LoopState` trzymał
 * pojedynczy `Dialog`, a `InputHandler` zamykał go pierwszym dowolnym klawiszem.
 * Krok 18 zapowiedział płaszczyznę, która **przejmuje klawisze i nie oddaje ich
 * niżej**, ale nie miał na czym tej zapowiedzi sprawdzić — jedyny jej użytkownik,
 * okienko z opisem pliku, klawiszy nie potrzebował. Okno komend potrzebuje.
 *
 * Okno samo mówi, ile miejsca chce i gdzie: opis pliku staje pośrodku, a wiersz
 * komend przy dolnej krawędzi. Bez tego reguła umieszczania musiałaby wiedzieć,
 * które okno rysuje — czyli znać je wszystkie.
 */
interface OverlayInterface extends ComponentInterface
{
    /** Identyfikator okna — unikalny w całym uruchomieniu. */
    public function id(): string;

    /** Prostokąt, jakiego okno chce w terminalu o podanym rozmiarze. */
    public function bounds(int $rows, int $columns): Rect;

    /**
     * Wiązania klawiszy okna — źródło podpowiedzi i spisu w oknie pomocy.
     *
     * @return list<KeyBinding>
     */
    public function bindings(): array;

    public function handle(KeyPress $key): OverlayOutcome;
}
