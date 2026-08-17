<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Glfw;

use LightManager\Application\Dto\ClipboardText;
use LightManager\Application\Port\ClipboardPort;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Schowek okna GLFW — dwie funkcje rozszerzenia za tym samym portem (krok 57).
 *
 * **Tor okienkowy jest tu najłatwiejszy z trzech, odwrotnie niż zwykle.**
 * `glfwGetClipboardString()` oddaje treść z wywołania: bez protokołu, bez
 * zgody terminala, bez czekania na odpowiedź, która ma prawo nie przyjść. Cała
 * asynchroniczność, o którą rozbija się ten krok, jest własnością **terminala**,
 * a nie schowka.
 *
 * Mimo to treść **nie wraca z `requestText()`**, tylko wchodzi do kolejki
 * zdarzeń — i to jest cały sens portu. Gdyby wracała, wołający musiałby
 * rozstrzygać, w którym torze działa, żeby wiedzieć, gdzie szukać odpowiedzi:
 * raz w wyniku wywołania, raz w kolejce. Różnica jest przez to niewidoczna,
 * a `ClipboardText` powstaje po prostu w **tym samym takcie**, w którym padła
 * prośba, zamiast klatkę albo dwie później.
 *
 * Funkcje są sprawdzone `function_exists()` przy planowaniu kroku i obie są
 * w PHP-GLFW 2.2 obecne; brak którejkolwiek znaczy schowek nieosiągalny —
 * odmowę ze zdaniem, a nie awarię (reguła 8).
 */
final class GlfwClipboardService extends AbstractSingleton implements ClipboardPort
{
    public function put(string $text): ?string
    {
        if ($text === '') {
            return 'clipboard.problem.empty';
        }

        if (!function_exists('glfwSetClipboardString')) {
            return 'clipboard.problem.unavailable';
        }

        // Limitu długości nie ma i nie ma go czym zastąpić: GLFW przekazuje napis
        // serwerowi okien, a ten nie zna progu, na którym `OSC 52` się urywa.
        // Próg z toru terminalowego byłby tu zawężeniem bez powodu — czyli
        // różnicą między torami wprowadzoną „dla symetrii”.
        glfwSetClipboardString(GlfwWindowService::getInstance()->handle(), $text);

        return null;
    }

    public function requestText(): bool
    {
        if (!function_exists('glfwGetClipboardString')) {
            return false;
        }

        // Schowek pusty i schowek niedostępny to w GLFW ta sama odpowiedź (pusty
        // napis), więc pustą treść oddajemy jako zdarzenie: o tym, że nie ma
        // czego wkleić, powie odbiorca — on jeden wie, czy pusty napis coś dla
        // niego znaczy.
        GlfwInputService::getInstance()->enqueue(
            new ClipboardText(glfwGetClipboardString(GlfwWindowService::getInstance()->handle())),
        );

        return true;
    }
}
