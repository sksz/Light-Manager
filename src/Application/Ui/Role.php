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
    case Info;
    case Warning;
    case Danger;
}
