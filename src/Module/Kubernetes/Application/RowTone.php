<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

/**
 * Czy z zasobem jest coś nie tak — **na tyle, na ile widać to z listy**
 * (krok 52).
 *
 * Trzy stopnie, nie pięć, i pochodzą z pytania, jakie zadaje się patrząc na
 * listę podów: „czy coś wymaga mojej uwagi?”. Odpowiedź „pod czeka na przydział”
 * i odpowiedź „pod wstaje w kółko i nie wstanie” to dwie różne wiadomości, ale
 * obie mieszczą się w jednym z tych trzech stopni.
 *
 * Ton **nie jest rolą motywu** i tłumaczy się na nią dopiero w warstwie
 * rysującej. Powód jest ten sam, dla którego `Message` niesie `MessageTone`,
 * a nie kolor: warstwa aplikacji nie wie, jaka paleta jest włączona, a rola
 * dobrana bez sprawdzenia palety bywa rolą bez koloru (nauczka z kroku 43,
 * reguła 15c).
 */
enum RowTone
{
    case Normal;

    /** Stan przejściowy: zasób jeszcze nie działa, ale nic złego się nie stało. */
    case Waiting;

    /** Stan, w którym zasób sam z siebie nie wyjdzie. */
    case Broken;
}
