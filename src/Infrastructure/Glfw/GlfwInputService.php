<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Glfw;

use LightManager\Application\Dto\InputEvent;
use LightManager\Application\Dto\PointerButton;
use LightManager\Application\Port\InputPort;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Wejście z okna GLFW jako ten sam słownik `InputEvent` — okienna implementacja
 * `InputPort`, z pominięciem `KeySequenceParser`: sekwencje escape są
 * problemem, którego w oknie nie ma (krok 34).
 *
 * Zdarzenia klawiszy, znaków i wskaźnika wpadają przez wywołania zwrotne do
 * **jednej** kolejki, a `readEvent()` oddaje je pojedynczo. Kolejność
 * kliknięcia wobec klawisza zachowuje się przez to sama, bez ani jednej zmiany
 * w `GameLoop` (krok 55). `glfwPollEvents()` pompuje kolejkę przy pierwszym
 * pytaniu w takcie, więc wchodzi w rytm pętli — dokładnie tam, gdzie tor
 * terminalowy robi nieblokujący odczyt STDIN.
 */
final class GlfwInputService extends AbstractSingleton implements InputPort
{
    /** Te same sygnały, po których tor terminalowy kończy pętlę — powłoka nadal je doręcza. */
    private const HANDLED_SIGNALS = [SIGINT, SIGTERM, SIGHUP, SIGQUIT];

    /** @var list<InputEvent> */
    private array $queue = [];

    private bool $shutdownRequested = false;

    /**
     * Ostatnie znane położenie kursora w komórkach.
     *
     * Pamięta je usługa, a nie mapper, bo GLFW podaje położenie **osobnym**
     * wywołaniem zwrotnym: zdarzenie przycisku i obrót kółka przychodzą bez
     * współrzędnych. W terminalu tego kłopotu nie ma — tam każda sekwencja SGR
     * niesie komórkę ze sobą.
     *
     * @var array{row: int, column: int}
     */
    private array $cursor = ['row' => 0, 'column' => 0];

    /** Przycisk trzymany w tej chwili — bez niego ruch nie jest przeciągnięciem. */
    private ?PointerButton $held = null;

    /**
     * Czy raportowanie wskaźnika jest włączone.
     *
     * W oknie nie ma czego zdejmować — wywołania zwrotne są już przypięte —
     * więc przełącznik ustawień **odpina zdarzenia od kolejki**. Zachowanie ma
     * być takie samo jak w terminalu, a nie podobne (krok 55, punkt 6).
     */
    private bool $mouseEnabled = true;

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

        $this->registerPointerCallbacks($window, new GlfwPointerMapper());

        // Uchwyt ustawia znacznik i nic więcej — wzorem `TerminalService`
        // (krok 06): pętla ma wyjść przez `break` i posprzątać jedną ścieżką.
        pcntl_async_signals(true);

        foreach (self::HANDLED_SIGNALS as $signal) {
            pcntl_signal($signal, function (): void {
                $this->shutdownRequested = true;
            });
        }
    }

    /**
     * Przełącznik myszy w torze okienkowym: odpina zdarzenia od kolejki.
     *
     * Odpięcie samych wywołań zwrotnych byłoby czystsze i zostało odrzucone —
     * `glfwSetCursorPosCallback($window, null)` gubiłoby wtedy także pamięć
     * położenia kursora, więc pierwsze kliknięcie po ponownym włączeniu myszy
     * trafiłoby w komórkę sprzed wyłączenia.
     */
    public function useMouseReporting(bool $enabled): void
    {
        $this->mouseEnabled = $enabled;

        if (!$enabled) {
            $this->held = null;
        }
    }

    /**
     * Trzy wywołania zwrotne wskaźnika, wszystkie do **tej samej** kolejki, co
     * klawisze.
     *
     * Kolejność zdarzeń zachowuje się dzięki temu sama: GLFW doręcza je
     * w jednym `glfwPollEvents()`, a kolejka jest jedna, więc kliknięcie
     * stawiające ognisko nigdy nie wyminie litery wpisanej zaraz po nim.
     */
    private function registerPointerCallbacks(\GLFWwindow $window, GlfwPointerMapper $mapper): void
    {
        glfwSetCursorPosCallback($window, function (float $x, float $y) use ($mapper, $window): void {
            $context = VgContextService::getInstance();
            $this->cursor = $mapper->cell(
                $x,
                $y,
                $context->cellWidthPixels(),
                $context->cellHeightPixels(),
            );

            // Ruch bez wciśniętego przycisku jest odrzucany **tutaj**, a nie
            // u odbiorcy: gdyby wpadał do kolejki, okno zalewałoby pętlę
            // zdarzeniami, których tor terminalowy nie wysyła w ogóle.
            $this->enqueuePointer($mapper->mapMotion($this->held, $this->modifiers($window), $this->cursor));
        });

        glfwSetMouseButtonCallback($window, function (int $button, int $action, int $mods) use ($mapper): void {
            $pressed = $action === GLFW_PRESS;
            $mapped = $mapper->button($button);

            // Przycisk trzymany pamiętamy **niezależnie od kolejki**: gdyby
            // zapisywało go dopiero przyjęte zdarzenie, wyłączenie myszy
            // w trakcie przeciągania zostawiałoby wciśnięty przycisk na zawsze.
            $this->held = $pressed ? $mapped : null;

            $this->enqueuePointer($mapper->mapButton($button, $action, $mods, $this->cursor));
        });

        glfwSetScrollCallback($window, function (float $xOffset, float $yOffset) use ($mapper, $window): void {
            $this->enqueuePointer($mapper->mapScroll($yOffset, $this->modifiers($window), $this->cursor));
        });
    }

    /** Zdarzenie wskaźnika trafia do kolejki tylko wtedy, gdy mysz jest włączona. */
    private function enqueuePointer(?InputEvent $event): void
    {
        if ($event === null || !$this->mouseEnabled) {
            return;
        }

        $this->queue[] = $event;
    }

    /**
     * Modyfikatory dla zdarzeń, które ich nie niosą.
     *
     * Wywołanie zwrotne ruchu i kółka dostaje w PHP-GLFW same współrzędne, więc
     * stan klawiszy trzeba dobrać pytaniem — tanim, bo GLFW trzyma go
     * w pamięci procesu i nie chodzi po nic do serwera okien.
     */
    private function modifiers(\GLFWwindow $window): int
    {
        $mods = 0;

        foreach ([
            [GLFW_KEY_LEFT_SHIFT, GLFW_KEY_RIGHT_SHIFT, GLFW_MOD_SHIFT],
            [GLFW_KEY_LEFT_CONTROL, GLFW_KEY_RIGHT_CONTROL, GLFW_MOD_CONTROL],
            [GLFW_KEY_LEFT_ALT, GLFW_KEY_RIGHT_ALT, GLFW_MOD_ALT],
        ] as [$left, $right, $flag]) {
            if (glfwGetKey($window, $left) === GLFW_PRESS || glfwGetKey($window, $right) === GLFW_PRESS) {
                $mods |= $flag;
            }
        }

        return $mods;
    }

    public function readEvent(): ?InputEvent
    {
        if ($this->queue === []) {
            $this->altConsumedCharacter = false;
            glfwPollEvents();

            // Zdarzenia zmiany rozmiaru wpadają dokładnie tutaj, bo GLFW doręcza
            // je w środku pompowania kolejki. Czynności okna zależne od tego, co
            // menedżer okien zdążył zrobić, należą więc zaraz za nim — i jest to
            // jedyne miejsce w takcie, w którym da się je postawić bez dotykania
            // pętli (krok 37).
            GlfwWindowService::getInstance()->afterPollEvents();
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
