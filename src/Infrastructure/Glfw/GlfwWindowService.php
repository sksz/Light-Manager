<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Glfw;

use GLFWwindow;
use LightManager\Infrastructure\Config\SettingsService;
use LightManager\Infrastructure\I18n\TranslatorService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use Throwable;

/**
 * Otwiera natywne okno z kontekstem OpenGL — okienny odpowiednik efektu
 * ubocznego `TerminalService` (tam: tryb surowy, tu: `glfwInit()` i okno),
 * dlatego usługa stoi pierwsza w okienkowej sekwencji bootstrapu (krok 34).
 *
 * Kontekst to 3.3 core (D53): wystarcza obu technikom rysowania rozważanym
 * w kroku 35, a Mesa pod X11 odda kontekst nowszy i zgodny wstecz.
 *
 * Zamknięcie idzie dwiema drogami naraz, wzorem procesu tłowego (krok 26):
 * jawnie w `Bootstrap::shutdown()` i przez funkcję zamknięcia procesu
 * rejestrowaną w konstruktorze — jedna jest czytelna, druga łapie wyjście
 * wyjątkiem i błędem krytycznym. `close()` jest przez to idempotentne.
 */
final class GlfwWindowService extends AbstractSingleton
{
    /**
     * Komórka tymczasowa na czas tworzenia okna — okno rodzi się **ukryte**
     * (krok 35) i zanim ktokolwiek je zobaczy, `Bootstrap` wymiaruje je na
     * nowo komórką z metryk fontu (`VgContextService`) i dopiero pokaże.
     * Te stałe rządzą więc wyłącznie rozmiarem, którego nikt nie ogląda.
     */
    private const PROVISIONAL_CELL_WIDTH_PIXELS = 10;

    private const PROVISIONAL_CELL_HEIGHT_PIXELS = 20;

    private ?GLFWwindow $window;

    private bool $terminated = false;

    protected function __construct()
    {
        parent::__construct();

        // Preflight w `bin/light-manager` sprawdza to samo wcześniej i z lepszym
        // komunikatem; ta linia broni przed wywołaniem usługi inną drogą.
        if (!extension_loaded('glfw')) {
            throw GlfwException::forMissingExtension();
        }

        // Porównanie z zerem zamiast z `GLFW_TRUE`: rozszerzenie oddaje int,
        // a stuby `phpgl/ide-stubs` definiują tę stałą błędnie jako bool —
        // literał czyta się tak samo na maszynie z rozszerzeniem i bez niego.
        if (glfwInit() === 0) {
            throw GlfwException::forInitFailure();
        }

        glfwWindowHint(GLFW_CONTEXT_VERSION_MAJOR, 3);
        glfwWindowHint(GLFW_CONTEXT_VERSION_MINOR, 3);
        glfwWindowHint(GLFW_OPENGL_PROFILE, GLFW_OPENGL_CORE_PROFILE);

        // Okno rodzi się ukryte (krok 35): rozmiar startowy zależy od komórki
        // z metryk fontu, a te wymagają kontekstu, który wymaga okna. Zamiast
        // pokazywać okno w złym rozmiarze i je szarpać, pokazuje się je raz,
        // już zwymiarowane (`showAtGrid()`). Narzędzie pomiarowe nie pokazuje
        // go wcale.
        glfwWindowHint(GLFW_VISIBLE, 0);

        $settings = SettingsService::getInstance()->current();

        try {
            $this->window = glfwCreateWindow(
                $settings->windowColumns * self::PROVISIONAL_CELL_WIDTH_PIXELS,
                $settings->windowRows * self::PROVISIONAL_CELL_HEIGHT_PIXELS,
                TranslatorService::getInstance()->translate('window.title'),
            );
        } catch (Throwable) {
            // Rozszerzenie zgłasza nieudane utworzenie okna po swojemu; na
            // zewnątrz ma wyjść nasza hierarchia, bo na niej stoi `bin/`.
            glfwTerminate();
            $this->terminated = true;

            throw GlfwException::forWindowFailure();
        }

        glfwMakeContextCurrent($this->window);

        // Stały takt pętli zostaje jedynym zegarem (D53) — vsync wyłączony,
        // żeby oba tory zachowywały się identycznie.
        glfwSwapInterval(0);

        register_shutdown_function(function (): void {
            $this->close();
        });
    }

    public function handle(): GLFWwindow
    {
        return $this->window ?? throw GlfwException::forWindowFailure();
    }

    /**
     * Rozmiar framebuffera w pikselach — tanie wywołanie w procesie, dlatego
     * czyta się przy każdym pytaniu, bez pamięci i bez znacznika (uproszczenie
     * wzorca z kroku 33, nie odstępstwo od niego).
     *
     * @return array{width: int, height: int}
     */
    public function framebufferSize(): array
    {
        $width = 0;
        $height = 0;

        glfwGetFramebufferSize($this->handle(), $width, $height);

        return ['width' => $width, 'height' => $height];
    }

    /**
     * Wymiaruje okno siatką ustawień × komórką z metryk fontu i dopiero
     * wtedy je pokazuje — użytkownik nigdy nie widzi rozmiaru tymczasowego.
     */
    public function showAtGrid(int $columns, int $rows, int $cellWidthPixels, int $cellHeightPixels): void
    {
        glfwSetWindowSize(
            $this->handle(),
            max(1, $columns) * $cellWidthPixels,
            max(1, $rows) * $cellHeightPixels,
        );
        glfwShowWindow($this->handle());
    }

    /** Rozmiar okna w pikselach bez pokazywania — dla pomiaru w oknie ukrytym (krok 35). */
    public function resizeContent(int $widthPixels, int $heightPixels): void
    {
        glfwSetWindowSize($this->handle(), max(1, $widthPixels), max(1, $heightPixels));
    }

    /** Czy użytkownik zamknął okno (przycisk zamknięcia) — druga droga do `break` pętli. */
    public function shouldClose(): bool
    {
        return $this->window !== null && glfwWindowShouldClose($this->window) !== 0;
    }

    public function swapBuffers(): void
    {
        glfwSwapBuffers($this->handle());
    }

    /** Idempotentne — bezpieczne z każdej ścieżki wyjścia (normalnej, wyjątku, sygnału). */
    public function close(): void
    {
        if ($this->window !== null) {
            glfwDestroyWindow($this->window);
            $this->window = null;
        }

        if (!$this->terminated) {
            glfwTerminate();
            $this->terminated = true;
        }
    }
}
