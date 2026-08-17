<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Glfw;

use Closure;
use GLFWwindow;
use LightManager\Application\Dto\Settings;
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
 *
 * Krok 37 dokłada trzy rzeczy i wszystkie trzy są **stanem okna**, a nie nowym
 * mechanizmem: pamięć rozmiaru (zapisywaną dopiero po uspokojeniu zmian),
 * pełny ekran wraz z granicami, do których ma wrócić, oraz odczyt skali treści.
 * Pamiętanie rozmiaru **włącza się jawnie** (`rememberSize()`), bo narzędzie
 * pomiarowe zmienia rozmiar okna dziesiątki razy w jednym przebiegu i nie ma
 * prawa zapisać żadnego z nich do ustawień użytkownika.
 */
final class GlfwWindowService extends AbstractSingleton
{
    /**
     * Klasa okna w rozumieniu X11 (`WM_CLASS`) — po niej pulpit dopasowuje
     * do okna wpis `.desktop`, a wraz z nim ikonę na pasku zadań.
     *
     * To jedyna droga do ikony, jaką ma dziś ta aplikacja: rozszerzenie
     * PHP-GLFW 2.2 **nie wystawia `glfwSetWindowIcon`** (krok 37), więc bitmapy
     * nie ma jak podać wprost. Ta sama nazwa stoi w polu `StartupWMClass` wpisu
     * zakładanego przez `bin/install-desktop-entry`; rozejście się ich znaczy
     * okno bez ikony.
     */
    public const WINDOW_CLASS = 'light-manager';

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

    /**
     * Zasoby OpenGL do zwolnienia, zanim zniknie kontekst — patrz
     * `releaseBeforeClose()`.
     *
     * @var list<Closure(): void>
     */
    private array $releases = [];

    /**
     * Czekanie na uspokojenie zmian rozmiaru — `null` znaczy „nie zapamiętujemy
     * rozmiaru w ogóle”, czyli stan narzędzia pomiarowego i każdej innej drogi
     * poza aplikacją.
     */
    private ?WindowSizeSettle $settle = null;

    private int $cellWidthPixels = 1;

    private int $cellHeightPixels = 1;

    private bool $fullscreen = false;

    /**
     * Ile taktów pilnować powrotu z pełnego ekranu — sekunda przy 30 klatkach.
     *
     * Liczba istnieje, bo menedżer okien odpowiada **po swojemu i z opóźnieniem**
     * (patrz `restoreAfterFullscreen()`); gdyby uparł się przy swoim rozmiarze,
     * dopominanie się bez końca byłoby szarpaniem okna, a nie naprawą.
     */
    private const RESTORE_TICKS = 30;

    /**
     * Położenie i rozmiar okna sprzed wejścia w pełny ekran — jedyny stan, jaki
     * krok 37 dokłada ponad pamięć rozmiaru. Bez niego powrót z pełnego ekranu
     * musiałby zgadywać, a kryterium kroku mówi „co do piksela”.
     *
     * @var array{x: int, y: int, width: int, height: int}|null
     */
    private ?array $windowedBounds = null;

    /**
     * Granice, w które okno ma wrócić po pełnym ekranie — dopóki tam nie trafi.
     *
     * @var array{x: int, y: int, width: int, height: int}|null
     */
    private ?array $restoreTarget = null;

    private int $restoreTicksLeft = 0;

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

        // Klasa okna dla pulpitu (krok 37). Podpowiedź jest x11-owa i na innych
        // platformach GLFW po prostu ją pomija — a tam, gdzie działa, jest
        // jedynym sposobem, żeby okno dostało ikonę bez `glfwSetWindowIcon`.
        glfwWindowHintString(GLFW_X11_CLASS_NAME, self::WINDOW_CLASS);
        glfwWindowHintString(GLFW_X11_INSTANCE_NAME, self::WINDOW_CLASS);

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
        $this->useTextCursor();
        glfwShowWindow($this->handle());
    }

    /**
     * Kursor tekstowy nad całym oknem (krok 56, D100 rozstrzygnięcie 4).
     *
     * Kształt jest tu **prawdziwy, a nie ozdobny**: zaznaczyć da się każdą
     * komórkę klatki, więc granicy „tu treść, a tu nie” po prostu nie ma — i tak
     * samo zachowuje się każdy terminal, w którym wskaźnik nad treścią jest
     * belką, a nie strzałką. Wchodzi w tym kroku, a nie w 55, bo dopiero teraz
     * ma co oznaczać.
     *
     * Kursor jest obiektem rozszerzenia żyjącym do końca procesu, więc jego
     * zwolnienie zamawia się **przed** zamknięciem okna (reguła 11h): destruktor
     * wołany po `glfwTerminate()` kończy proces naruszeniem ochrony pamięci już
     * po ostatniej linii kodu. Zamawiającym jest ten, kto tworzy — czyli to
     * miejsce.
     *
     * Tory terminalowe nie mają czym tego zrobić i **nie udają**, że mają:
     * kształt wskaźnika należy tam do emulatora, a aplikacja nie ma o nim zdania.
     */
    private function useTextCursor(): void
    {
        $cursor = glfwCreateStandardCursor(GLFW_IBEAM_CURSOR);
        glfwSetCursor($this->handle(), $cursor);

        // Zniszczenie kursora przypiętego do okna przywraca oknu kursor domyślny
        // — GLFW nie wymaga odpinania go osobno.
        $this->releaseBeforeClose(static function () use ($cursor): void {
            glfwDestroyCursor($cursor);
        });
    }

    /** Rozmiar okna w pikselach bez pokazywania — dla pomiaru w oknie ukrytym (krok 35). */
    public function resizeContent(int $widthPixels, int $heightPixels): void
    {
        glfwSetWindowSize($this->handle(), max(1, $widthPixels), max(1, $heightPixels));
    }

    /**
     * Od tej chwili rozmiar nadany oknu przez użytkownika wraca przy następnym
     * starcie (krok 37): zmiany trafiają do kluczy `windowColumns`/`windowRows`,
     * czyli tam, skąd `Bootstrap` bierze rozmiar startowy.
     *
     * Włącza się **jawnie i dopiero po pokazaniu okna**, bo droga do tej usługi
     * jest więcej niż jedna: `bin/render-bench --window` przemierza w jednym
     * przebiegu kilkanaście rozmiarów okna ukrytego i żaden z nich nie jest
     * wyborem użytkownika.
     *
     * Komórka przychodzi z zewnątrz, a nie z `VgContextService`, bo wołający
     * (`Bootstrap`) i tak ją ma — a usługa okna nie ma powodu poznawać metryk
     * fontu tylko po to, żeby podzielić przez nie piksele.
     */
    public function rememberSize(int $cellWidthPixels, int $cellHeightPixels): void
    {
        $this->cellWidthPixels = max(1, $cellWidthPixels);
        $this->cellHeightPixels = max(1, $cellHeightPixels);
        $this->settle = new WindowSizeSettle();

        // Wywołanie zwrotne odnotowuje **chwilę**, a nie rozmiar: rozmiar czyta
        // się i tak przy zapisie, a przeciągnięty róg zdąży się do tego czasu
        // zmienić jeszcze wiele razy.
        glfwSetWindowSizeCallback($this->handle(), function (): void {
            $this->settle?->noteChange(microtime(true));
        });
    }

    /**
     * Czynności okna przypadające **raz na takt**, zaraz po pompowaniu zdarzeń
     * (`GlfwInputService`) — bo dopiero wtedy wiadomo, co menedżer okien zrobił
     * z poprzednim żądaniem.
     *
     * Obie są tanie i obie kończą się niczym, dopóki nikt nie ruszył okna.
     */
    public function afterPollEvents(): void
    {
        $this->restoreAfterFullscreen();
        $this->saveSettledSize();
    }

    /**
     * Dopilnowanie, żeby powrót z pełnego ekranu trafił w te same piksele.
     *
     * **Zmierzone, nie przewidziane** (krok 37): `glfwSetWindowMonitor()` z
     * granicami okna oddaje obszar treści **niższy o pasek tytułu** — menedżer
     * okien liczy podaną geometrię jako geometrię ramki, więc 900×600 wraca jako
     * 900×563, a okno zjeżdża o te 37 pikseli w dół. Dostawienie rozmiaru
     * osobnym `glfwSetWindowSize()` naprawia to w całości, ale **tylko wtedy, gdy
     * pada po zakończeniu przejścia** — wołane w tym samym takcie nie zmienia
     * nic, bo menedżer okien jest jeszcze w trakcie odpełnoekranowiania.
     *
     * Stąd dopominanie się z taktu na takt zamiast jednego wywołania: przy
     * pierwszym takcie, w którym rozmiar wciąż nie ten, żądanie idzie ponownie;
     * gdy trafi — pilnowanie się kończy.
     */
    private function restoreAfterFullscreen(): void
    {
        if ($this->restoreTarget === null) {
            return;
        }

        $width = 0;
        $height = 0;
        glfwGetWindowSize($this->handle(), $width, $height);

        if (($width === $this->restoreTarget['width'] && $height === $this->restoreTarget['height'])
            || --$this->restoreTicksLeft <= 0
        ) {
            $this->restoreTarget = null;

            return;
        }

        glfwSetWindowSize($this->handle(), $this->restoreTarget['width'], $this->restoreTarget['height']);
        glfwSetWindowPos($this->handle(), $this->restoreTarget['x'], $this->restoreTarget['y']);
    }

    /**
     * Zapisuje rozmiar okna, jeśli zmiany się uspokoiły.
     *
     * Cztery powody, dla których zapis nie następuje, i każdy jest zamierzony:
     * pamiętanie wyłączone (pomiar), pełny ekran (rozmiar monitora nie jest
     * wyborem rozmiaru okna), trwający powrót z pełnego ekranu (rozmiar
     * przejściowy nie jest niczyim wyborem tym bardziej) i siatka niezmieniona
     * (przeciągnięcie rogu o kilka pikseli mieści się w tej samej komórce).
     *
     * Niepowodzenie zapisu zbywamy milczeniem, choć `SettingsService` je opisuje:
     * użytkownik o ten zapis nie prosił, a pasek stanu ma jedno miejsce i niesie
     * to, o co prosił. O niezapisywalnym pliku powie mu pierwsza jawna zmiana
     * ustawienia.
     */
    private function saveSettledSize(): void
    {
        if ($this->settle === null
            || $this->fullscreen
            || $this->restoreTarget !== null
            || !$this->settle->settled(microtime(true))
        ) {
            return;
        }

        $this->saveCurrentSize();
    }

    /**
     * Zapis zmiany, która nie zdążyła się uspokoić — jedna linia w
     * `Bootstrap::shutdown()`.
     *
     * Bez niej rozmiar nadany oknu i porzucony w tej samej pół sekundy, w której
     * użytkownik nacisnął `F10`, przepadałby — a to jest dokładnie ta chwila,
     * w której ktoś ustawia okno „na następny raz”.
     */
    public function saveSizeIfPending(): void
    {
        if ($this->settle === null || $this->fullscreen || $this->restoreTarget !== null || !$this->settle->pending()) {
            return;
        }

        $this->settle->forget();
        $this->saveCurrentSize();
    }

    private function saveCurrentSize(): void
    {
        $framebuffer = $this->framebufferSize();
        $columns = GlfwViewportService::cells($framebuffer['width'], $this->cellWidthPixels);
        $rows = GlfwViewportService::cells($framebuffer['height'], $this->cellHeightPixels);

        if (!Settings::allowsWindowColumns($columns) || !Settings::allowsWindowRows($rows)) {
            return;
        }

        $settings = SettingsService::getInstance();
        $current = $settings->current();

        if ($current->windowColumns === $columns && $current->windowRows === $rows) {
            return;
        }

        $settings->save($current->withWindowColumns($columns)->withWindowRows($rows));
    }

    /**
     * Przełącza pełny ekran i oddaje stan **po** przełączeniu.
     *
     * Wejście zapamiętuje położenie i rozmiar okna, bo `glfwSetWindowMonitor()`
     * ich nie przechowuje — powrót bez tej pary trafiałby w rozmiar wymyślony,
     * a nie w ten, z którego użytkownik wyszedł.
     */
    public function toggleFullscreen(): bool
    {
        if ($this->fullscreen) {
            $this->leaveFullscreen();
        } else {
            $this->enterFullscreen();
        }

        // Zmiana rozmiaru wywołana pełnym ekranem nie jest wyborem rozmiaru okna,
        // więc czekanie na jej uspokojenie zaczyna się i kończy tutaj.
        $this->settle?->forget();

        return $this->fullscreen;
    }

    public function isFullscreen(): bool
    {
        return $this->fullscreen;
    }

    /**
     * Ile pikseli fizycznych przypada na piksel logiczny (`glfwGetWindowContentScale`).
     *
     * Wartość jest dziś **czytana i pokazywana, a nie stosowana** (krok 37,
     * rozstrzygnięcie nr 4): maszyna projektu ma skalę 1.0, więc przeliczanie
     * komórki byłoby kodem, którego nie da się tu rzetelnie sprawdzić. Pomoc
     * pokazuje odczyt, żeby użytkownik na sprzęcie o gęstości innej niż 1.0
     * mógł powiedzieć, co widzi.
     *
     * @return array{x: float, y: float}
     */
    public function contentScale(): array
    {
        $x = 0.0;
        $y = 0.0;

        glfwGetWindowContentScale($this->handle(), $x, $y);

        return ['x' => $x, 'y' => $y];
    }

    private function enterFullscreen(): void
    {
        $x = 0;
        $y = 0;
        $width = 0;
        $height = 0;

        glfwGetWindowPos($this->handle(), $x, $y);
        glfwGetWindowSize($this->handle(), $width, $height);

        $this->windowedBounds = ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height];

        // Monitor podstawowy, bo wybór monitora jest poza zakresem kroku 37.
        $monitor = glfwGetPrimaryMonitor();
        $mode = glfwGetVideoMode($monitor);

        glfwSetWindowMonitor($this->handle(), $monitor, 0, 0, $mode->width, $mode->height, $mode->refreshRate);

        $this->fullscreen = true;
    }

    private function leaveFullscreen(): void
    {
        // Granic może nie być wyłącznie wtedy, gdy aplikacja wystartowała w pełnym
        // ekranie — dziś nie ma takiej drogi, ale wymyślanie rozmiaru w tym miejscu
        // byłoby gorsze niż siatka z ustawień, którą użytkownik sam wybrał.
        $bounds = $this->windowedBounds ?? $this->boundsFromSettings();

        glfwSetWindowMonitor(
            $this->handle(),
            null,
            $bounds['x'],
            $bounds['y'],
            $bounds['width'],
            $bounds['height'],
            GLFW_DONT_CARE,
        );

        // Same te granice nie wystarczą — dopilnowaniem zajmuje się takt pętli
        // (`restoreAfterFullscreen()`), bo menedżer okien odpowiada z opóźnieniem.
        $this->windowedBounds = null;
        $this->restoreTarget = $bounds;
        $this->restoreTicksLeft = self::RESTORE_TICKS;
        $this->fullscreen = false;
    }

    /** @return array{x: int, y: int, width: int, height: int} */
    private function boundsFromSettings(): array
    {
        $settings = SettingsService::getInstance()->current();

        return [
            'x' => 0,
            'y' => 0,
            'width' => $settings->windowColumns * $this->cellWidthPixels,
            'height' => $settings->windowRows * $this->cellHeightPixels,
        ];
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

    /**
     * Zamawia zwolnienie zasobu OpenGL **przed** zamknięciem okna (poprawka
     * z kroku 39). Zamawiającym jest ten, kto zasób tworzy — dziś jedynym jest
     * `VgContextService` ze swoim `VGContext`.
     *
     * Dlaczego to nie jest ozdoba: obiekty rozszerzenia zwalniają zasoby GL
     * w destruktorach, a te wołają się przy sprzątaniu procesu — czyli **po**
     * `glfwTerminate()`, kiedy kontekstu już nie ma. Skutek jest za każdym
     * razem ten sam: proces kończy się naruszeniem ochrony pamięci już po
     * ostatniej linii kodu. `bin/render-bench --window` robił tak od kroku 35
     * i nikt tego nie widział, bo tabela zdążyła się wypisać, a kodu wyjścia
     * nikt nie czytał — dopiero `make bench-window` przestał to przemilczać.
     */
    public function releaseBeforeClose(Closure $release): void
    {
        $this->releases[] = $release;
    }

    /** Idempotentne — bezpieczne z każdej ścieżki wyjścia (normalnej, wyjątku, sygnału). */
    public function close(): void
    {
        // Odwrotna kolejność zamawiania, wzorem stosu: zasób powstały później
        // mógł stanąć na wcześniejszym.
        foreach (array_reverse($this->releases) as $release) {
            $release();
        }

        $this->releases = [];

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
