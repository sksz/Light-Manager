<?php

declare(strict_types=1);

namespace LightManager\Application\Ui;

/**
 * Rola koloru w motywie — to, czym posługuje się komponent zamiast wartości.
 *
 * Prymityw niesie rolę, nie kolor. Gdyby niósł kolor, komponent musiałby znać
 * paletę, a zmiana motywu w locie (krok 14) wymagałaby przebudowania całego
 * drzewa zamiast wymiany jednej tablicy w rendererze.
 *
 * Lista pokrywa się z rolami motywu z kroku 13 — jest jego odpowiednikiem po
 * stronie, która o Imagicku nic nie wie.
 */
enum Role
{
    case Background;
    case Surface;
    case Border;
    case Text;
    case Muted;
    case Accent;
    case Selection;
    case SelectionText;
    /**
     * Wpis zaznaczony wielokrotnie (krok 43, D80 rozstrzygnięcie 5a).
     *
     * Dwunasta rola i pierwsza dołożona od kroku 13 — a stanęła tu, bo
     * wszystkie jedenaście były już **zajęte przez inne znaczenie**: kursor
     * bierze `Selection`, katalogi `Accent`, wpisy ukryte `Muted`, a `Warning`
     * jest w motywie Grafit **tym samym kolorem, co akcent** (jeden nasycony
     * kolor, D25), więc zaznaczony plik wyglądał w domyślnym motywie jak
     * katalog. Rola bez własnego koloru nie jest rolą.
     */
    case Marked;
    case Info;
    case Warning;
    case Danger;
}
