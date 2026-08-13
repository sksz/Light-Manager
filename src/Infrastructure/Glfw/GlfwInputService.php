<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Glfw;

use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Port\InputPort;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Wejście z okna GLFW jako ten sam słownik `KeyPress` — okienna implementacja
 * `InputPort`, z pominięciem `KeySequenceParser`: sekwencje escape są
 * problemem, którego w oknie nie ma (krok 34).
 *
 * Zdarzenia klawiszy i znaków wpadają przez wywołania zwrotne do kolejki,
 * a `readKey()` oddaje je pojedynczo — jedno zdarzenie na klawisz, jak
 * w terminalu. `glfwPollEvents()` pompuje kolejkę przy pierwszym pytaniu
 * o klawisz w takcie, więc wchodzi w rytm pętli bez zmiany `GameLoop`
 * — dokładnie tam, gdzie tor terminalowy robi nieblokujący odczyt STDIN.
 */
final class GlfwInputService extends AbstractSingleton implements InputPort
{
    /** Te same sygnały, po których tor terminalowy kończy pętlę — powłoka nadal je doręcza. */
    private const HANDLED_SIGNALS = [SIGINT, SIGTERM, SIGHUP, SIGQUIT];

    /** @var list<KeyPress> */
    private array $queue = [];

    private bool $shutdownRequested = false;

    /**
     * Czy w tej porcji zdarzeń padło już `Alt`+litera.
     *
     * Znacznik istnieje dla jednej rzeczy (krok 29): `Alt`+`z` bywa doręczane
     * **dwoma** zdarzeniami naraz — klawisza (z bitem `Alt`) i znaku (`z`),
     * bo układ klawiatury tłumaczy literę mimo modyfikatora. Bez znacznika
     * jedno naciśnięcie dawałoby dwa naciśnięcia w słowniku: przełącznik
     * zawijania włączyłby się i natychmiast wyłączył. `Ctrl` tego kłopotu nie
     * ma, bo zdarzenia znaku dla bajtów sterujących nie powstają.
     *
     * Znacznik gaśnie przed każdą kolejną porcją zdarzeń, więc nie ma jak
     * połknąć litery naciśniętej w następnym takcie.
     */
    private bool $altConsumedCharacter = false;

    protected function __construct()
    {
        parent::__construct();

        $mapper = new GlfwKeyMapper();
        $window = GlfwWindowService::getInstance()->handle();

        glfwSetKeyCallback($window, function (int $key, int $scancode, int $action, int $mods) use ($mapper): void {
            $press = $mapper->mapKeyEvent($key, $action, $mods);

            if ($press === null) {
                return;
            }

            $this->altConsumedCharacter = $this->altConsumedCharacter || $press->alt;
            $this->queue[] = $press;
        });

        glfwSetCharCallback($window, function (int $codepoint) use ($mapper): void {
            if ($this->altConsumedCharacter) {
                $this->altConsumedCharacter = false;

                return;
            }

            $press = $mapper->mapCharacter($codepoint);

            if ($press !== null) {
                $this->queue[] = $press;
            }
        });

        // Uchwyt ustawia znacznik i nic więcej — wzorem `TerminalService`
        // (krok 06): pętla ma wyjść przez `break` i posprzątać jedną ścieżką.
        pcntl_async_signals(true);

        foreach (self::HANDLED_SIGNALS as $signal) {
            pcntl_signal($signal, function (): void {
                $this->shutdownRequested = true;
            });
        }
    }

    public function readKey(): ?KeyPress
    {
        if ($this->queue === []) {
            $this->altConsumedCharacter = false;
            glfwPollEvents();
        }

        return array_shift($this->queue);
    }

    /**
     * Sygnał **albo** przycisk zamknięcia okna — obie drogi zbiegają się
     * w jednym miejscu taktu, jak w kroku 09.
     */
    public function shutdownRequested(): bool
    {
        return $this->shutdownRequested || GlfwWindowService::getInstance()->shouldClose();
    }
}
