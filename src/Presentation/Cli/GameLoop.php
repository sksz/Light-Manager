<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

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
        int $framesPerSecond = self::DEFAULT_FRAMES_PER_SECOND,
    ) {
        $this->frameBudgetMicroseconds = (int) (self::MICROSECONDS_PER_SECOND / $framesPerSecond);
    }

    public function run(): void
    {
        $state = $this->state;

        while (true) {
            $startedAt = microtime(true);

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

            // Kawałek pracy prowadzonej przez okno nakładane (krok 41): usuwanie
            // katalogu wraz z zawartością posuwa się po jednym kawałku na takt.
            // Stoi **tutaj**, a nie w składaniu klatki, i to jest cała różnica
            // wobec pracy kawałkowej z kroku 25: tamta czyta plik, ta zmienia
            // dysk, a rysowanie nie ma prawa mieć skutków ubocznych.
            $this->input->advanceWork($state, $startedAt);

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
     * Zbiera wszystkie klawisze, które zdążyły przyjść od poprzedniego taktu —
     * przy stałym takcie odczyt pojedynczego klawisza gubiłby wejście szybciej
     * piszącego użytkownika.
     *
     * @return bool czy padł klawisz wyjścia
     */
    private function consumeInput(LoopState $state, float $now): bool
    {
        while (($key = $this->source->readKey()) !== null) {
            if ($this->input->handle($key, $state, $now)) {
                return true;
            }
        }

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
