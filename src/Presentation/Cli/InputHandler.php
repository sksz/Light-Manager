<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Domain\Exception\DomainException;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\Transition;

/**
 * Rozdaje klawisze: najpierw okno nakładane, potem klawisze rdzenia, na końcu
 * aktywny ekran.
 *
 * Do kroku 18 była tu cała wiedza o tym, co robi każdy klawisz na każdym z
 * trzech ekranów — dwa zagnieżdżone `match`-e, po jednym na ekran i na klawisz.
 * Dziś zostają wyłącznie **klawisze globalne**, bo tylko one nie należą do
 * żadnego ekranu z osobna. Reszta wędruje do ekranu i wraca jako `ScreenOutcome`.
 *
 * Krok 19 dokłada do tej wędrówki jedno piętro i jedną regułę. Piętro to okno
 * nakładane, które klawisz **zużywa albo przepuszcza** — do kroku 18 zamykało
 * się przy pierwszym dowolnym. Reguła: klawisz przepuszczony przez okno próbuje
 * jeszcze klawiszy globalnych, ale **nigdy nie schodzi do ekranu**. Okno jest
 * modalne; gdyby litera wpisywana w oknie komend trafiała pod spodem do listy
 * plików, zmieniałaby zaznaczenie, którego użytkownik w tej chwili nie widzi.
 *
 * Wyjątki domenowe (katalog zniknął, brak uprawnień) są tu przechwytywane i
 * zamieniane na komunikat w stanie — nawigacja zostaje wtedy tam, gdzie była.
 * Sam napis składa `ProblemPresenter`: treść wyjątku jest techniczna i po
 * angielsku, więc nie nadaje się do pokazania wprost.
 */
final class InputHandler
{
    /**
     * @param array<string, ScreenInterface> $modules litera skrótu → ekran modułu;
     *                                                mapę składa `Bootstrap`
     *                                                z rejestru modułów
     */
    public function __construct(
        private readonly ScreenStack $screens,
        private readonly ScreenInterface $help,
        private readonly ScreenInterface $settings,
        private readonly ProblemPresenter $problems,
        private readonly ?OverlayInterface $commands = null,
        private readonly array $modules = [],
    ) {
    }

    /**
     * Wiązania rdzenia — jedno źródło dla obsługi, dla podpowiedzi w stopce i
     * dla spisu w oknie pomocy.
     *
     * Wyjście wisi na `F10`, a nie na literze: od kroku 19 aplikacja ma pole
     * tekstowe, a klawisz kończący pracę nie może być znakiem, który użytkownik
     * właśnie wpisuje. Skutek uboczny jest dla kroku 20 cenniejszy niż sama
     * zmiana — rdzeń nie rezerwuje odtąd **ani jednej litery**.
     *
     * @return list<KeyBinding>
     */
    public static function globalBindings(): array
    {
        return [
            KeyBinding::of([Key::F1], 'help.key.help'),
            KeyBinding::of([Key::F2], 'help.key.settings'),
            KeyBinding::of([Key::F12], 'help.key.commands'),
            KeyBinding::of([Key::F10], 'help.key.quit'),
        ];
    }

    /**
     * @param float $now bieżący czas w sekundach — decyduje, czy komunikat
     *                   wisi już wystarczająco długo, by go zgasić
     *
     * @return bool czy użytkownik poprosił o zakończenie aplikacji
     */
    public function handle(KeyPress $key, LoopState $state, float $now): bool
    {
        $state->dismissMessageIfDue($now);

        $overlay = $state->overlays()->current();

        if ($overlay !== null) {
            return $this->toOverlay($overlay->handle($key), $key, $state, $now);
        }

        if ($this->global($key, $state)) {
            return $key->key === Key::F10;
        }

        return $this->toScreen($key, $state, $now);
    }

    /**
     * Skutek klawisza oddanego oknu nakładanemu. Klawisz nieprzyjęty próbuje
     * jeszcze klawiszy globalnych — i wtedy okno się zamyka, bo `F1` znaczy
     * „pokaż pomoc”, a nie „pokaż pomoc pod spodem”.
     */
    private function toOverlay(OverlayOutcome $outcome, KeyPress $key, LoopState $state, float $now): bool
    {
        if (!$outcome->handled) {
            if (!$this->global($key, $state)) {
                return false;
            }

            $state->overlays()->close();

            return $key->key === Key::F10;
        }

        if ($outcome->message !== null) {
            $state->report($outcome->message, $now);
        }

        if ($outcome->closes) {
            $state->overlays()->close();
        }

        if ($outcome->screenId !== null) {
            $this->openById($outcome->screenId);
        }

        return $outcome->quits;
    }

    /**
     * @return bool czy klawisz należał do rdzenia albo do skrótu modułu
     */
    private function global(KeyPress $key, LoopState $state): bool
    {
        switch ($key->key) {
            case Key::F10:
                return true;
            case Key::F1:
                $this->screens->toggle($this->help);

                return true;
            case Key::F2:
                $this->screens->toggle($this->settings);

                return true;
            case Key::F12:
                if ($this->commands === null) {
                    return false;
                }

                $state->overlays()->toggle($this->commands);

                return true;
            case Key::Character:
                return $this->toModule($key);
            default:
                return false;
        }
    }

    /**
     * Skrót modułu: `Ctrl` plus litera.
     *
     * Skróty modułów stoją **obok klawiszy globalnych**, a nie za ekranem, bo są
     * tym samym rodzajem klawisza co `F1` i `F2` — otwierają okno niezależnie od
     * tego, co jest teraz na wierzchu. Ten sam skrót zamyka moduł, bo `toggle()`
     * znaczy tu dokładnie to, co przy ekranach rdzenia.
     */
    private function toModule(KeyPress $key): bool
    {
        if (!$key->ctrl) {
            return false;
        }

        $screen = $this->modules[$key->raw] ?? null;

        if ($screen === null) {
            return false;
        }

        $this->screens->toggle($screen);

        return true;
    }

    /**
     * Ekran wskazany przez komendę. Wiązanie idzie po identyfikatorze, bo
     * kontrakt komendy leży w `Application` i obiektu ekranu zobaczyć nie może;
     * tłumaczenie napisu na ekran jest więc tutaj i tylko tutaj.
     */
    private function openById(string $screenId): void
    {
        foreach ([$this->help, $this->settings, ...array_values($this->modules)] as $screen) {
            if ($screen->id() === $screenId) {
                $this->screens->open($screen);

                return;
            }
        }

        $this->screens->close();
    }

    private function toScreen(KeyPress $key, LoopState $state, float $now): bool
    {
        try {
            $outcome = $this->screens->current()->handle($key);
        } catch (DomainException $exception) {
            $state->reportProblem($this->problems->text($exception), $now);

            return false;
        }

        if ($outcome->message !== null) {
            $state->report($outcome->message, $now);
        }

        if ($outcome->overlay !== null) {
            $state->overlays()->open($outcome->overlay);
        }

        if ($outcome->transition === Transition::Close) {
            $this->screens->close();
        }

        return $outcome->transition === Transition::Quit;
    }
}
