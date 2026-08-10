<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Module;

use LightManager\Application\Module\ModuleContext;

/**
 * Ekran, który chce wiedzieć, gdzie użytkownik stoi i co ma zaznaczone.
 *
 * **Implementuje ją ekran, nie moduł** (P5). To ekran korzysta z kontekstu; moduł
 * byłby wyłącznie posłańcem do własnego ekranu. Rdzeń sprawdza `instanceof` na
 * obiekcie zwróconym przez `ProvidesScreen::screen()` i podaje mu kontekst przed
 * złożeniem klatki — w tym samym miejscu, w którym `FrameComposer` pyta ekran
 * o treść.
 *
 * Kontekst przychodzi **co klatkę**, a nie po zmianie: jest niezmienny i płytki,
 * więc podanie go kosztuje samo wywołanie, a rdzeń nie musi wiedzieć, że coś się
 * zmieniło. Drugie źródło prawdy o aktualności danych byłoby droższe od tego
 * wywołania.
 *
 * Ekran musi wytrzymać kontekst **pusty** — `selection` równe `null` to zwykły
 * stan (katalog pusty albo nieczytelny), a nie awaria.
 */
interface ReadsContext
{
    public function useContext(ModuleContext $context): void;
}
