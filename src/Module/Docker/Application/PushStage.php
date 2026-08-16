<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/**
 * Etapy wypychania obrazu do rejestru (krok 54).
 *
 * **Etapów jest pięć, choć plan widział jeden** — i pierwszy z nich wyszedł
 * dopiero z próby na żywym demonie: `push` **nie przyjmuje nazwy, której obraz
 * lokalnie nie nosi**. Odmawia zdaniem „an image does not exist locally with the
 * tag", i to z kodem HTTP 200, bo z punktu widzenia protokołu wszystko poszło
 * dobrze (ta sama pułapka, co przy budowie, 11t). Obraz zbudowany jako
 * `lm/proba:1` trzeba więc najpierw **oznaczyć** jako `ghcr.io/kto/lm/proba:1`,
 * a dopiero potem wypychać — nazwa w rejestrze jest drugą nazwą tego samego
 * obrazu, nie jego przeniesieniem.
 */
enum PushStage
{
    case Idle;

    /** Nadawanie nazwy docelowej — krótkie wywołanie, bez sieci poza demonem. */
    case Tagging;

    /** Demon rozmawia z rejestrem — trwa tyle, ile trwa sieć. */
    case Pushing;

    case Done;

    case Failed;
}
