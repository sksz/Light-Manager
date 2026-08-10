<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use LightManager\Application\Dto\KeyPress;

/**
 * Komponent, który przyjmuje kursor, a wraz z nim klawisze.
 *
 * Klawisz wędruje: komponent z kursorem → płaszczyzna → ekran → rdzeń.
 * Nieobsłużony idzie wyżej, obsłużony zatrzymuje się — stąd `bool` zamiast
 * `void`. Bez tego każdy poziom musiałby wiedzieć, co obsługuje poziom niższy,
 * czyli dokładnie to, przed czym `InputHandler` uginał się do kroku 18.
 *
 * `bindings()` jest przy okazji jedynym źródłem podpowiedzi w pasku stanu i
 * spisu klawiszy w oknie pomocy. Komponent, który obsługuje klawisz, ale go nie
 * deklaruje, jest z punktu widzenia użytkownika niewidoczny — i to jest
 * właściwa kara za pominięcie deklaracji.
 */
interface FocusableInterface extends ComponentInterface
{
    /** @return list<KeyBinding> */
    public function bindings(): array;

    /** @return bool czy klawisz został zużyty */
    public function handle(KeyPress $key): bool;
}
