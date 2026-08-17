<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

use Closure;
use LightManager\Application\Dto\ClipboardText;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\PointerAction;
use LightManager\Application\Dto\PointerButton;
use LightManager\Application\Dto\PointerEvent;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Port\ClipboardPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\Exception\DomainException;
use LightManager\Domain\ValueObject\Message;
use LightManager\Presentation\Ui\AcceptsPaste;
use LightManager\Presentation\Ui\AcceptsPointer;
use LightManager\Presentation\Ui\AcceptsPointerInOverlay;
use LightManager\Presentation\Ui\CopiesContent;
use LightManager\Presentation\Ui\CopyContent;
use LightManager\Presentation\Ui\DragsOwnContent;
use LightManager\Presentation\Ui\HintTarget;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Module\ProvidesScreen;
use LightManager\Presentation\Ui\Overlay\MenuOverlay;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\RunsWork;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\SelectionState;
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
     * Litery schowka: `Alt`+`c` kopiuje, `Alt`+`v` wkleja (krok 57, D95 nr 8).
     *
     * Publiczne, bo komenda schowka wraca tutaj **udając naciśnięcie** — tak jak
     * kliknięcie w podpowiedź stopki wraca klawiszem tej podpowiedzi (krok 55).
     * Dwie drogi do tej samej czynności rozjeżdżają się przy pierwszej poprawce
     * (krok 32), więc droga jest jedna, a litera stoi w jednym miejscu.
     *
     * `Ctrl`+`c` odpada **dwukrotnie i oba powody są w kodzie**: `Ctrl`+litera
     * należy w całości do skrótów modułów (krok 20), a tryb surowy zostawia
     * włączone `isig`, więc `^C` staje się SIGINT-em, **zanim aplikacja
     * cokolwiek przeczyta**. `Ctrl`+`Alt`+`v` dokłada trzeci: `^V` to `lnext`,
     * który przy włączonym `iexten` połyka następny bajt.
     */
    public const COPY_CHARACTER = 'c';

    public const PASTE_CHARACTER = 'v';

    /**
     * Rozpoznanie pary kliknięć — jedyna rzecz w obsłudze wskaźnika pytająca
     * o czas, więc stoi w jednym miejscu, a nie w każdym ekranie z osobna.
     */
    private readonly PointerGestures $gestures;

    /**
     * @param array<string, ScreenInterface> $modules    litera skrótu → ekran modułu;
     *                                                   mapę składa `Bootstrap`
     *                                                   z rejestru modułów
     * @param ?Closure(): bool               $fullscreen przełącznik pełnego ekranu
     *                                                   albo `null` w trybach
     *                                                   terminalowych, gdzie `F11`
     *                                                   nie ma czego przełączać
     * @param ?MenuOverlay                   $menu       menu kontekstowe (krok 32);
     *                                                   typ jest konkretny, bo rdzeń
     *                                                   podaje mu zaznaczenie przed
     *                                                   otwarciem — `OverlayInterface`
     *                                                   o zaznaczeniu nie wie i wiedzieć
     *                                                   nie ma
     */
    public function __construct(
        private readonly ScreenStack $screens,
        private readonly ScreenInterface $help,
        private readonly ScreenInterface $settings,
        private readonly ProblemPresenter $problems,
        /**
         * Napis o liczbie zaznaczonych wierszy (krok 56) — jedyne, co ta klasa
         * mówi użytkownikowi wprost, a nie skutkiem czynności.
         */
        private readonly TranslatorPort $translator,
        private readonly ?OverlayInterface $commands = null,
        private readonly array $modules = [],
        private readonly ?Closure $fullscreen = null,
        private readonly ?MenuOverlay $menu = null,
        /**
         * Schowek środowiska graficznego albo `null`, gdy tor go nie ma (krok 57).
         * Implementację wybiera `Bootstrap`, jak przy pozostałych portach toru.
         */
        private readonly ?ClipboardPort $clipboard = null,
    ) {
        $this->gestures = new PointerGestures();
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
     * Od kroku 37 lista **zależy od trybu** i jest to pierwszy taki przypadek:
     * `F11` wchodzi wyłącznie w torze okienkowym, bo pełny ekran nie znaczy nic
     * w terminalu, a spis klawiszy w oknie pomocy i w pasku stanu ma pokazywać
     * to, co działa tu i teraz (precedens kroku 30). Wołający zna tryb —
     * `Bootstrap` wybrał go flagą, zanim cokolwiek powstało.
     *
     * @return list<KeyBinding>
     */
    public static function globalBindings(bool $windowed = false): array
    {
        $bindings = [
            KeyBinding::of([Key::F1], 'help.key.help'),
            KeyBinding::of([Key::F2], 'help.key.settings'),
            KeyBinding::of([Key::F9], 'help.key.menu'),
            KeyBinding::of([Key::F12], 'help.key.commands'),
            KeyBinding::of([Key::F10], 'help.key.quit'),
            // Kopiowanie jest klawiszem **rdzenia**, bo zaznaczenie klatki jest
            // własnością rdzenia (reguła 11ź) i pierwsze źródło treści leży
            // w `LoopState`, nie w ekranie. Wklejanie stoi za to w spisie
            // **miejsca** (`TextInput::bindings()`), bo bez pola tekstowego nie
            // ma czego zrobić, a reguła 11p obowiązuje w obie strony — krok 57,
            // D101 nr 3.
            KeyBinding::alt(self::COPY_CHARACTER, 'clipboard.key.copy'),
        ];

        if ($windowed) {
            $bindings[] = KeyBinding::of([Key::F11], 'help.key.fullscreen');
        }

        return $bindings;
    }

    /**
     * Skróty modułów jako wiązania **do pokazania**, nie tylko do obsługi.
     *
     * `Ctrl`+litera otwiera moduł niezależnie od tego, co jest na wierzchu — czyli
     * jest dokładnie tym samym rodzajem klawisza, co `F1` i `F2` — a mimo to do
     * kroku 40 nigdy nie stał w pasku stanu. Powód był techniczny: skróty powstają
     * z rejestru modułów w `Bootstrapie`, a `globalBindings()` jest stałą listą
     * rdzenia, która o modułach nie wie i wiedzieć nie ma.
     *
     * Opisem jest **nazwa modułu** (`Ctrl+D  Opis pliku`), a nie zdanie „otwórz
     * okno modułu”: w oknie pomocy skrót stoi w zakładce swojego modułu, więc
     * wiadomo, czyj jest; w stopce stoją obok siebie i nazwa jest jedyną rzeczą,
     * która je rozróżnia.
     *
     * Do spisu w oknie pomocy te wiązania **nie idą** — tam mają już swoje
     * miejsce, a drugie byłoby powtórzeniem.
     *
     * @param array<string, ModuleInterface> $shortcuts litera skrótu → moduł
     *
     * @return list<KeyBinding>
     */
    public static function moduleBindings(array $shortcuts): array
    {
        $bindings = [];

        foreach ($shortcuts as $character => $module) {
            if ($module instanceof ProvidesScreen) {
                $bindings[] = KeyBinding::ctrl($character, $module->nameKey());
            }
        }

        return $bindings;
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

        // Klawisze schowka stoją **przed** oknem nakładanym, a nie za nim, i nie
        // jest to kwestia porządku, tylko konieczność: klawisz przepuszczony
        // przez okno trafia do klawiszy globalnych, a te **zamykają okno**
        // (`toOverlay()`, krok 19). `Alt`+`v` w polu filtra zamykałby przez to
        // filtr, do którego miał wkleić wzorzec. Piętra dla nich nie ma, bo nie
        // ma czego rozstrzygać: żadne okno i żaden ekran nie chce tych dwóch
        // liter dla siebie, a wklejanie i tak wraca do tego, kto ma ognisko.
        if ($this->toClipboard($key, $state, $now)) {
            return false;
        }

        $overlay = $state->overlays()->current();

        if ($overlay !== null) {
            try {
                $outcome = $overlay->handle($key);
            } catch (DomainException $exception) {
                // Okno **zostaje otwarte** i to jest różnica wobec drogi przez
                // ekran: klawisz, który się nie udał, nie zmienił niczego, a pole
                // z wpisaną nazwą jest dokładnie tym, co użytkownik chce poprawić
                // po zdaniu „nazwa jest już zajęta” (krok 41). Domknięcie, które
                // woli zamknąć okno, ma wyjątek złapać samo i oddać komunikat —
                // tak robi pytanie przed usunięciem.
                $state->reportProblem($this->problems->text($exception), $now);

                return false;
            }

            return $this->toOverlay($outcome, $key, $state, $now);
        }

        if ($this->global($key, $state, $now)) {
            return $key->key === Key::F10;
        }

        return $this->toScreen($key, $state, $now);
    }

    /**
     * Wskaźnik przechodzi przez **te same trzy piętra**, co klawisz, i w tej
     * samej kolejności: okno nakładane → rdzeń → ekran (krok 55).
     *
     * Trzy czynności rdzenia zamieniają się przy tym z powrotem w naciśnięcie
     * i wracają do `handle()` — czyli wykonują się **tą samą drogą co klawisz**,
     * a nie drugą, równoległą. Kliknięcie w podpowiedź stopki to jej klawisz,
     * podwójne kliknięcie to `Enter`, prawy przycisk to `F9`. Dwie drogi do tej
     * samej czynności rozjeżdżają się przy pierwszej poprawce — to zdanie
     * zapisał krok 32 o menu kontekstowym i obowiązuje ono dalej.
     *
     * @return bool czy użytkownik poprosił o zakończenie aplikacji
     */
    public function pointer(PointerEvent $event, LoopState $state, float $now): bool
    {
        $state->dismissMessageIfDue($now);

        $overlay = $state->overlays()->current();

        if ($overlay !== null) {
            return $this->toOverlayPointer($overlay, $event, $state, $now);
        }

        $binding = $this->hintAt($state, $event);

        if ($binding !== null) {
            $press = $binding->press();

            // Podpowiedź, która nie wisi na niczym naciskalnym, po prostu nic
            // nie robi — kliknięcie w nią **nie schodzi** do ekranu pod spodem,
            // bo trafiło w pasek stanu, a nie w treść.
            return $press !== null && $this->handle($press, $state, $now);
        }

        if ($this->toScreenPointer($event, $state, $now)) {
            return true;
        }

        $this->select($event, $state, $now);

        if ($event->action === PointerAction::Press && $event->button === PointerButton::Right) {
            // Kursor stanął już wyżej, w drodze przez ekran — menu dostaje
            // zaznaczenie takie, jakie użytkownik właśnie wskazał.
            $this->toMenu($state, $now);

            return false;
        }

        if ($this->gestures->isDoubleClick($event, $now)) {
            return $this->handle(KeyPress::special(Key::Enter, ''), $state, $now);
        }

        return false;
    }

    /**
     * Treść schowka doręczana temu, kto o nią prosił — **trzecia postać
     * zdarzenia wejściowego** (krok 57).
     *
     * Metoda stoi obok `handle()` i `pointer()`, bo jest trzecią drogą tej samej
     * kolejki. Różni się od nich jedną rzeczą: nie ma pięter. Treść schowka nie
     * wędruje przez okno, rdzeń i ekran, bo nie jest niczyim klawiszem — idzie
     * wprost do tego, kto zadeklarował `AcceptsPaste`, i do nikogo poza nim.
     *
     * Dwa warunki, oba obowiązkowe i oba porzucają treść bez śladu:
     * **ktoś musiał poprosić** (`takeClipboardRequest()` — odpowiedź niezamówiona
     * nie ma prawa nigdzie wejść) i **musi być komu doręczyć** (pole zamknięte
     * w międzyczasie znaczy treść porzuconą, nie treść wstawioną gdziekolwiek
     * indziej).
     */
    public function clipboard(ClipboardText $event, LoopState $state, float $now): void
    {
        if (!$state->takeClipboardRequest()) {
            return;
        }

        if ($event->isEmpty()) {
            $state->report(Message::warning($this->translator->translate('clipboard.empty')), $now);

            return;
        }

        $this->pasteTarget($state)?->paste($event->text);
    }

    /**
     * Wygasła prośba o schowek — pytanie zadawane **raz na takt** z `GameLoop`,
     * wzorem `advanceWork()` (krok 57).
     *
     * Stoi tutaj, a nie w pętli, bo zdanie do powiedzenia bierze się z katalogu
     * napisów, a pętla katalogu nie zna. Powód, dla którego to pytanie w ogóle
     * istnieje: terminal bez obsługi `OSC 52` **milczy** — więc bez terminu
     * użytkownik zobaczyłby klawisz, po którym nic się nie stało, i nie miałby
     * skąd wiedzieć, że to nie aplikacja zawiniła.
     */
    public function expireClipboardRequest(LoopState $state, float $now): void
    {
        if ($state->clipboardRequestExpired($now)) {
            $state->report(Message::warning($this->translator->translate('clipboard.unreachable')), $now);
        }
    }

    /**
     * `Alt`+`c` i `Alt`+`v` — dwie litery, które nie schodzą do okna ani do
     * ekranu.
     *
     * @return bool czy klawisz był klawiszem schowka
     */
    private function toClipboard(KeyPress $key, LoopState $state, float $now): bool
    {
        if ($this->clipboard === null || $key->key !== Key::Character || !$key->alt) {
            return false;
        }

        return match ($key->raw) {
            self::COPY_CHARACTER => $this->copy($state, $now),
            self::PASTE_CHARACTER => $this->askForClipboard($state, $now),
            default => false,
        };
    }

    /**
     * Kopiowanie: **trzy źródła w ustalonej kolejności i trzy różne zdania**
     * (punkt 5 zakresu kroku, D101 nr 1).
     *
     * Kolejność rozstrzyga rdzeń, bo tylko on widzi wszystkie trzy naraz:
     * **zaznaczenie klatki** (krok 56) → **to, co ekran albo okno uzna za swoją
     * treść** (`CopiesContent`) → **ścieżka wpisu pod kursorem** z kontekstu
     * sesji (krok 49). Dwa skrajne źródła są rdzeniowe i darmowe; środkowe jest
     * zdolnością, bo nazw zaznaczonych wpisów kontekst nie niesie — ma o nich
     * trzy liczby i ani jednej nazwy.
     *
     * Zdanie mówi **co** skopiowano, a nie „skopiowano”: trzy różne źródła po tym
     * samym klawiszu są dla użytkownika nierozróżnialne, dopóki zdanie jest jedno.
     */
    private function copy(LoopState $state, float $now): bool
    {
        $content = $this->copyable($state);

        if ($content === null) {
            $state->report(Message::warning($this->translator->translate('clipboard.nothing')), $now);

            return true;
        }

        $problem = $this->clipboard?->put($content->text);

        $state->report(
            $problem === null ? $content->announcement : Message::error($this->translator->translate($problem)),
            $now,
        );

        return true;
    }

    private function copyable(LoopState $state): ?CopyContent
    {
        $selection = $state->selectionText();

        if ($selection !== []) {
            return new CopyContent(
                implode("\n", $selection),
                Message::info($this->translator->plural('clipboard.copied.selection', count($selection))),
            );
        }

        $own = $this->topmost($state);
        $content = $own instanceof CopiesContent ? $own->copyable() : null;

        if ($content !== null) {
            return $content;
        }

        $path = $state->context()->selectionPath();

        return $path === null ? null : new CopyContent(
            $path,
            Message::info($this->translator->translate('clipboard.copied.path', ['path' => $path])),
        );
    }

    /**
     * Prośba o zawartość schowka — **wyłącznie stąd i z komendy**, czyli
     * wyłącznie z polecenia użytkownika (pierwsze z trzech zobowiązań D95 nr 5).
     */
    private function askForClipboard(LoopState $state, float $now): bool
    {
        if ($this->pasteTarget($state) === null) {
            // Bez pola tekstowego nie ma po co pytać — a nawet nie wolno:
            // odczytana treść ma **jedno** miejsce docelowe, więc pytanie zadane
            // w liście plików byłoby czytaniem cudzego schowka bez odbiorcy.
            $state->report(Message::warning($this->translator->translate('clipboard.no-target')), $now);

            return true;
        }

        if ($this->clipboard?->requestText() !== true) {
            $state->report(Message::error($this->translator->translate('clipboard.problem.unavailable')), $now);

            return true;
        }

        $state->requestClipboard($now);

        return true;
    }

    private function pasteTarget(LoopState $state): ?AcceptsPaste
    {
        $top = $this->topmost($state);

        return $top instanceof AcceptsPaste ? $top : null;
    }

    /**
     * Okno nakładane, a pod jego brak — ekran. Ta sama kolejność, którą chodzi
     * klawisz i wskaźnik: okno **wypiera** ekran, bo klawisze do niego nie
     * schodzą.
     */
    private function topmost(LoopState $state): OverlayInterface|ScreenInterface
    {
        return $state->overlays()->current() ?? $this->screens->current();
    }

    /**
     * Zaznaczanie treści: kotwica, prostokąt, zdanie po zwolnieniu (krok 56).
     *
     * Stoi **za** drogą przez ekran, a nie przed nią, i kolejność jest tu
     * regułą: naciśnięcie ma najpierw postawić kursor (krok 55), a granica
     * podziału — zdążyć się chwycić. Dopiero potem rdzeń pyta, czy to
     * przeciągnięcie jest jeszcze wolne.
     *
     * Pierwszeństwo granicy rozstrzyga się **w tym jednym miejscu**, a nie
     * w każdym ekranie z osobna: ekran prowadzący własne przeciągnięcie mówi to
     * zdolnością `DragsOwnContent`, a wszystkie pozostałe milczą (D100 nr 2).
     *
     * Zdanie o liczbie wierszy pada **po zwolnieniu przycisku**, a nie przy
     * każdym drgnięciu ręki — tą samą regułą, którą krok 55 zapisał dla zapisu
     * proporcji podziału, i z tego samego powodu: przeciągnięcie daje
     * kilkadziesiąt zdarzeń na sekundę, a komunikat wyświetlany tyle razy
     * migotałby zamiast mówić.
     */
    private function select(PointerEvent $event, LoopState $state, float $now): void
    {
        if ($event->button !== PointerButton::Left) {
            return;
        }

        $selection = $state->selection();

        match ($event->action) {
            PointerAction::Press => $selection->begin($event->row, $event->column),
            PointerAction::Drag => $this->dragsOwn()
                ? null
                : $selection->extendTo($event->row, $event->column),
            PointerAction::Release => $this->released($selection, $state, $now),
            default => null,
        };
    }

    /** Czy ekran na wierzchu prowadzi własne przeciągnięcie — dziś: granicę podziału. */
    private function dragsOwn(): bool
    {
        $screen = $this->screens->current();

        return $screen instanceof DragsOwnContent && $screen->isDraggingOwn();
    }

    private function released(SelectionState $selection, LoopState $state, float $now): void
    {
        $rows = $selection->rows();
        $selection->release();

        if ($rows > 0) {
            $state->report(Message::info($this->translator->plural('selection.rows', $rows)), $now);
        }
    }

    /**
     * Wskaźnik oddany oknu nakładanemu.
     *
     * Kliknięcie **poza** oknem jest połykane niezależnie od tego, czy okno
     * zdolność deklaruje: okno jest modalne, a kliknięcie w listę pod spodem
     * zmieniałoby zaznaczenie, którego użytkownik w tej chwili nie widzi. Jest
     * to ta sama reguła pierwszeństwa, którą klawisz chodzi od kroku 19 —
     * z jedną różnicą: klawisz nieprzyjęty próbuje jeszcze klawiszy globalnych,
     * a kliknięcie nie ma czego próbować, bo klawiszy globalnych nie da się
     * kliknąć poza stopką, a stopka jest pod oknem.
     */
    private function toOverlayPointer(
        OverlayInterface $overlay,
        PointerEvent $event,
        LoopState $state,
        float $now,
    ): bool {
        if (!$overlay instanceof AcceptsPointerInOverlay) {
            return false;
        }

        try {
            $outcome = $overlay->pointer($event);
        } catch (DomainException $exception) {
            $state->reportProblem($this->problems->text($exception), $now);

            return false;
        }

        if (!$outcome->handled) {
            return false;
        }

        return $this->applyOverlayOutcome($outcome, $state, $now);
    }

    /** Wiązanie podpowiedzi pod wskaźnikiem — wyłącznie przy naciśnięciu lewym przyciskiem. */
    private function hintAt(LoopState $state, PointerEvent $event): ?KeyBinding
    {
        if ($event->action !== PointerAction::Press || $event->button !== PointerButton::Left) {
            return null;
        }

        return HintTarget::at($state->hintTargets(), $event);
    }

    private function toScreenPointer(PointerEvent $event, LoopState $state, float $now): bool
    {
        $screen = $this->screens->current();

        if (!$screen instanceof AcceptsPointer) {
            return false;
        }

        try {
            $outcome = $screen->pointer($event);
        } catch (DomainException $exception) {
            $state->reportProblem($this->problems->text($exception), $now);

            return false;
        }

        return $this->applyScreenOutcome($outcome, $state, $now);
    }

    /**
     * Skutek klawisza oddanego oknu nakładanemu. Klawisz nieprzyjęty próbuje
     * jeszcze klawiszy globalnych — i wtedy okno się zamyka, bo `F1` znaczy
     * „pokaż pomoc”, a nie „pokaż pomoc pod spodem”.
     */
    private function toOverlay(OverlayOutcome $outcome, KeyPress $key, LoopState $state, float $now): bool
    {
        if (!$outcome->handled) {
            if (!$this->global($key, $state, $now)) {
                return false;
            }

            $state->overlays()->close();

            return $key->key === Key::F10;
        }

        return $this->applyOverlayOutcome($outcome, $state, $now);
    }

    /**
     * Kawałek pracy prowadzonej przez okno nakładane — **raz na takt** (krok 41).
     *
     * Metoda leży tutaj, choć klawisza w niej nie ma, i powód jest jeden: to
     * jedyne miejsce, które stosuje `OverlayOutcome`. Drugie takie miejsce
     * rozjechałoby się z tym przy pierwszej zmianie kontraktu okna — a kontrakt
     * zmienił się właśnie w tym kroku, o wskazanie następnego okna.
     *
     * `DomainException` łapiemy tą samą regułą, co w drodze przez ekran: domknięcie
     * kończące pracę odświeża listę plików, a ponowny odczyt katalogu ma prawo się
     * nie udać — i wtedy użytkownik ma zobaczyć zdanie, a nie ślad stosu.
     */
    public function advanceWork(LoopState $state, float $now): void
    {
        $overlay = $state->overlays()->current();

        if (!$overlay instanceof RunsWork) {
            return;
        }

        try {
            $outcome = $overlay->advance();
        } catch (DomainException $exception) {
            $state->overlays()->close();
            $state->reportProblem($this->problems->text($exception), $now);

            return;
        }

        $this->applyOverlayOutcome($outcome, $state, $now);
    }

    /**
     * @return bool czy skutek kończy aplikację
     */
    private function applyOverlayOutcome(OverlayOutcome $outcome, LoopState $state, float $now): bool
    {
        if ($outcome->message !== null) {
            $state->report($outcome->message, $now);
        }

        if ($outcome->closes) {
            $state->overlays()->close();
        }

        // Następne okno otwiera się **po** zamknięciu poprzedniego, bo stos ma
        // jedno piętro: usuwanie katalogu prowadzi przez liczenie, pytanie
        // i usuwanie, a każde z nich jest osobnym oknem (krok 41).
        if ($outcome->next !== null) {
            $state->overlays()->open($outcome->next);
        }

        if ($outcome->screenId !== null) {
            $this->openById($outcome->screenId);
        }

        return $outcome->quits;
    }

    /**
     * @return bool czy klawisz należał do rdzenia albo do skrótu modułu
     */
    private function global(KeyPress $key, LoopState $state, float $now): bool
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
            case Key::F9:
                return $this->toMenu($state, $now);
            case Key::F11:
                // Pełny ekran nie melduje się komunikatem, choć komenda
                // `core.fullscreen` to robi: skutek naciśnięcia klawisza widać
                // w tej samej klatce, a pasek stanu ma jedno miejsce i szkoda go
                // na opisanie tego, co użytkownik właśnie zobaczył. Komenda mówi,
                // bo okno komend zamyka się razem z wykonaniem.
                if ($this->fullscreen === null) {
                    return false;
                }

                ($this->fullscreen)();

                return true;
            case Key::Character:
                return $this->toModule($key);
            default:
                return false;
        }
    }

    /**
     * Menu kontekstowe: zaznaczenie najpierw, otwarcie potem (krok 32).
     *
     * Kontekst sesji podaje **rdzeń**, bo to on go trzyma — okno dostaje migawkę
     * i już się o nią nie dopytuje. Menu bez ani jednej pozycji **nie otwiera
     * się wcale**: mówi zdaniem w pasku stanu, zamiast prosić o zamknięcie
     * pustego prostokąta.
     */
    private function toMenu(LoopState $state, float $now): bool
    {
        if ($this->menu === null) {
            return false;
        }

        $this->menu->useContext($state->context());

        if ($this->menu->isEmpty()) {
            $state->report($this->menu->emptyMessage(), $now);

            return true;
        }

        $state->overlays()->toggle($this->menu);

        return true;
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

        return $this->applyScreenOutcome($outcome, $state, $now);
    }

    /**
     * @return bool czy skutek kończy aplikację
     */
    private function applyScreenOutcome(ScreenOutcome $outcome, LoopState $state, float $now): bool
    {
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
