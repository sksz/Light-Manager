<?php

declare(strict_types=1);

namespace LightManager\Application\Module;

use LightManager\Application\Event\EventDeclaration;

/**
 * Moduł, który wnosi do słownika własne zdarzenia (krok 46, D83).
 *
 * Plan kroku zaczynał się od zdania „rdzeń publikuje, moduł odbiera", a
 * zdarzenia publikowane przez moduły miał w sekcji *Poza zakresem*. Zdanie to
 * jest **odwołane rozstrzygnięciem użytkownika** i powód jest wymierny: wszystkie
 * komunikaty modułów schodzą się w `LoopState::report()` z tonem, więc trzema
 * zdarzeniami rdzenia da się odróżnić powodzenie od awarii — ale **nie da się
 * odróżnić kopiowania od usunięcia** ani ruchu kursora od czegokolwiek. Efekt
 * przypisany do „zakończonego kopiowania" wymaga, żeby to kopiowanie samo o sobie
 * powiedziało.
 *
 * Reguła zamkniętego słownika zostaje w mocy i przenosi się na moduł:
 * **każdy publikujący ma swój zamknięty zbiór**, wyliczony z enumu, a nie
 * składany z napisów w miejscach wywołania. Deklaracja i publikacja nie mają
 * dzięki temu jak się rozjechać — spis w oknie odbiorcy nie pokaże wiersza,
 * którego nikt nie publikuje, ani nie przemilczy zdarzenia, które pada.
 *
 * Nazwy muszą stać w przestrzeni identyfikatora modułu (`browser.*`), tak samo
 * jak nazwy komend i klucze napisów; nazwy spoza niej odsiewa `EventRegistry`.
 */
interface DeclaresEvents
{
    /** @return list<EventDeclaration> */
    public function events(): array;
}
