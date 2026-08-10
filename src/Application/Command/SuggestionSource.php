<?php

declare(strict_types=1);

namespace LightManager\Application\Command;

/**
 * Skąd biorą się podpowiedzi wartości argumentu.
 *
 * Podział jest wymuszony kosztem: lista motywów nie zmienia się przez całe
 * uruchomienie, więc wolno ją policzyć raz; lista katalogów na dysku zmienia się
 * pod ręką użytkownika, więc policzona z góry byłaby kłamstwem — i tak czy owak
 * nie dałoby się jej zmieścić w pamięci.
 */
enum SuggestionSource
{
    /** Argument nie ma podpowiedzi — dowolny tekst. */
    case None;

    /** Rdzeń pyta komendę **raz**, przy starcie, i zapamiętuje odpowiedź. */
    case Fixed;

    /**
     * Rdzeń pyta komendę przy każdej zmianie wpisanego przedrostka.
     *
     * Zadeklarowane w kroku 19, implementowane w kroku 20 przez `file-info.jump`:
     * żadna komenda rdzenia nie przyjmuje ścieżki, więc mechanizm powstałby tu
     * bez użytkownika (zasada P5 kroku 18).
     */
    case OnDemand;
}
