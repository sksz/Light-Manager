<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

use LightManager\Application\Dto\ClipboardText;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\PointerEvent;
use LightManager\Application\Port\BackgroundPumpPort;
use LightManager\Application\Port\InputPort;

/**
 * Pętla główna: odczytaj wejście → zaktualizuj stan → przerysuj klatkę.
 *
 * Rysowanie idzie w stałym takcie, niezależnie od tego, czy coś się zmieniło.
 * Gdy klatka nie zmieści się w budżecie taktu, pętla nie nadrabia zaległości
 * ani nie pomija klatek, tylko zwalnia do tego, co sprzęt wyrabia.
 *
 * Do kroku 17 przekroczenie budżetu było regułą: klatka trwała ~112 ms przy
 * budżecie 50 ms, więc `usleep()` poniżej nie wykonywał się nigdy i pętla
 * zajmowała rdzeń w całości. Po optymalizacji klatka listy kosztuje ~20 ms
 * w typowym oknie i ~31 ms w dużym, więc takt podniesiono do 30 kl./s —
 * zapas idzie w płynność przewijania. Klatka **z miniaturą** nadal budżetu nie
 * mieści (37–75 ms, w większości kwantyzacja adaptacyjna, której klatka
 * z bitmapą wymaga — D24), więc na zaznaczonym obrazie pętla dalej nie zasypia.
 *
 * Wyjście zawsze przez `break`: klawiszem `F10` albo sygnałem (Ctrl+C, SIGTERM),
 * który usługa terminala zamienia na znacznik zamiast ubijać proces.
 */
final class GameLoop
{
    private const DEFAULT_FRAMES_PER_SECOND = 30;

    private const MICROSECONDS_PER_SECOND = 1000000;

    private readonly int $frameBudgetMicroseconds;

    public function __construct(
        private readonly InputPort $source,
        private readonly FrameComposer $frames,
        private readonly ScreenStack $screens,
        private readonly InputHandler $input,
        private readonly LoopState $state,
        private readonly ?ModuleTicker $modules = null,
        private readonly ?BackgroundPumpPort $processes = null,
        int $framesPerSecond = self::DEFAULT_FRAMES_PER_SECOND,
    ) {
        $this->frameBudgetMicroseconds = (int) (self::MICROSECONDS_PER_SECOND / $framesPerSecond);
    }

    public function run(): void
    {
        $state = $this->state;

        while (true) {
            $startedAt = microtime(true);

            // Przełącznik myszy działa **w locie** (krok 55): pytamy źródło raz
            // na takt, zamiast szukać miejsca, w którym ustawienie się zmienia.
            // Pytanie jest tanie — obie implementacje wychodzą od razu, gdy stan
            // jest już taki, jakiego chcemy — a jedno miejsce w pętli znosi całą
            // klasę pomyłek „ustawienie zmienione trzecią drogą, o której nikt
            // nie pamiętał”.
            $this->source->useMouseReporting($state->settings()->mouse);

            if ($this->consumeInput($state, $startedAt)) {
                break;
            }

            if ($this->source->shutdownRequested()) {
                break;
            }

            // Czas klatki podaje pętla, bo tylko ona go zna. Korzysta z niego
            // wszystko, co zmienia się samo z siebie — dziś karetka w polu
            // tekstowym, wcześniej gaszenie komunikatów.
            $state->tick($startedAt);

            // Potoki prac tłowych (krok 51): jedno przejście po wszystkich, raz
            // na klatkę. Do tego kroku prace były prowadzone po jednej i karmił
            // je jej właściciel przy zaglądaniu; odkąd jest ich kilka, karmienie
            // przestało być sprawą właściciela — potomek, którego nikt nie czyta,
            // zatrzymuje się na pełnym potoku, a jego limitu czasu też nie ma kto
            // sprawdzić. Faza stoi przed taktem modułów, żeby moduł oglądał w tej
            // klatce stan już posunięty, a nie stan sprzed klatki.
            $this->processes?->pump();

            // Kawałek pracy prowadzonej przez okno nakładane (krok 41): usuwanie
            // katalogu wraz z zawartością posuwa się po jednym kawałku na takt.
            // Stoi **tutaj**, a nie w składaniu klatki, i to jest cała różnica
            // wobec pracy kawałkowej z kroku 25: tamta czyta plik, ta zmienia
            // dysk, a rysowanie nie ma prawa mieć skutków ubocznych.
            $this->input->advanceWork($state, $startedAt);

            // Termin prośby o schowek (krok 57): terminal bez obsługi `OSC 52`
            // nie odpowiada **nic**, więc ktoś musi zauważyć, że odpowiedź już
            // nie przyjdzie. Stoi w fazie „aktualizuj stan”, bo zdanie w pasku
            // stanu jest zmianą stanu, a nie skutkiem rysowania — i tuż za
            // kawałkiem pracy, bo oba pytania mają tę samą postać: „czy coś
            // przestało być aktualne”.
            $this->input->expireClipboardRequest($state, $startedAt);

            // Takt modułów (krok 45): raz na klatkę, dla każdego przyjętego
            // modułu, który o niego poprosił — **niezależnie od tego, co jest na
            // wierzchu**. To jest cała różnica wobec `NeedsTime` kilka linii
            // niżej: o czas klatki pyta `FrameComposer` ekran i okno nakładane,
            // czyli to, co widać, a moduł ma pracować wtedy, gdy nie widać nic.
            // Stoi tu, a nie w rysowaniu, z tego samego powodu, co kawałek pracy
            // powyżej: to jest faza „aktualizuj stan”.
            $this->modules?->tick($state, $startedAt);

            // Pętla nie wie, który ekran jest aktywny, i nie ma powodu
            // wiedzieć: składanie klatki dostaje go w argumencie, a różnice
            // między ekranami kończą się na tym, co narysują w środku panelu.
            $this->frames->render($this->screens->current(), $state);

            $this->waitForNextTick($startedAt);
        }
    }

    /**
     * Zbiera wszystkie zdarzenia, które zdążyły przyjść od poprzedniego taktu —
     * przy stałym takcie odczyt pojedynczego klawisza gubiłby wejście szybciej
     * piszącego użytkownika.
     *
     * Od kroku 55 zdarzenia są **dwojakie i stoją w jednej kolejce**, więc
     * kolejność kliknięcia wobec klawisza jest tą, w jakiej padły u użytkownika.
     * Od kroku 57 są **trojakie**: trzecią postacią jest treść schowka, która
     * w torze terminalowym przychodzi klatkę albo dwie po tym, jak o nią
     * poproszono. Rozdzielenie na drogi pada tutaj i jest jedynym miejscem,
     * w którym pętla o tej trojakości wie.
     *
     * Schowek jest przy tym jedyną postacią, która **nie kończy aplikacji** —
     * treść wklejona do pola nie ma jak być klawiszem wyjścia — więc jego droga
     * nie oddaje niczego do `match`a.
     *
     * @return bool czy padł klawisz wyjścia
     */
    private function consumeInput(LoopState $state, float $now): bool
    {
        while (($event = $this->source->readEvent()) !== null) {
            $quits = match (true) {
                $event instanceof PointerEvent => $this->input->pointer($event, $state, $now),
                $event instanceof KeyPress => $this->input->handle($event, $state, $now),
                $event instanceof ClipboardText => $this->clipboard($event, $state, $now),
                default => false,
            };

            if ($quits) {
                return true;
            }
        }

        return false;
    }

    /** Treść schowka nie kończy aplikacji — stąd stałe `false` (krok 57). */
    private function clipboard(ClipboardText $event, LoopState $state, float $now): bool
    {
        $this->input->clipboard($event, $state, $now);

        return false;
    }

    private function waitForNextTick(float $startedAt): void
    {
        $elapsed = (int) ((microtime(true) - $startedAt) * self::MICROSECONDS_PER_SECOND);
        $remaining = $this->frameBudgetMicroseconds - $elapsed;

        if ($remaining > 0) {
            usleep($remaining);
        }
    }
}
