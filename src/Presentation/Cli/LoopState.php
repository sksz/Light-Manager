<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

use LightManager\Application\Command\CommandRegistry;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Event\AppEvent;
use LightManager\Application\Event\EventRegistry;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Query\QueryRegistry;
use LightManager\Domain\ValueObject\Message;

/**
 * Dane przenoszone między iteracjami pętli: ustawienia, komunikat, okna
 * nakładane, czas bieżącej klatki i kontekst sesji.
 *
 * Czyli **stan powłoki**, a nie stan menadżera plików. Do kroku 20 leżał tu
 * jeszcze bieżący katalog wraz z zaznaczeniem — i to on kazał rdzeniowi znać
 * pojęcie pliku. Krok 21 wyprowadził go do `BrowserState` w module przeglądarki,
 * bo katalog jest stanem jednej konkretnej funkcji, a nie czymś, co przeżywa
 * klatkę niezależnie od tego, kto ją rysuje.
 *
 * Który ekran jest aktywny — **nie** jest częścią tego stanu. Ekrany są od
 * kroku 18 obiektami, a te muszą powstać po `LoopState`, bo z niego czytają;
 * trzymanie ich tutaj wymagałoby ekranu pustego na czas budowy. Aktywnym
 * ekranem zarządza `ScreenStack`.
 */
final class LoopState
{
    /** Ile komunikat musi wisieć, zanim klawisz zdoła go zgasić. */
    private const MINIMUM_MESSAGE_SECONDS = 3.0;

    private const SECONDS_PER_MESSAGE_WORD = 0.5;

    private ?Message $message = null;

    private float $messageDismissableAt = 0.0;

    private readonly OverlayStack $overlays;

    /** Czas rozpoczęcia bieżącej klatki — dla tego, co się zmienia samo z siebie. */
    private float $now = 0.0;

    /**
     * Kontekst sesji dla modułów: gdzie użytkownik stoi i co ma zaznaczone.
     *
     * Stan go **trzyma**, ale nie **wypełnia**: publikuje go ten, kto zna bieżące
     * miejsce — od kroku 21 `BrowserState` modułu przeglądarki. Brak wydawcy daje
     * kontekst pusty, nie `null`: odbiorca ma czytać, a nie sprawdzać istnienie.
     */
    private ModuleContext $context;

    /**
     * Słownik zdarzeń wraz z odbiorcami (krok 46).
     *
     * Stoi tutaj z tego samego powodu, co `ModuleContext` kilka linii wyżej:
     * **stan trzyma, ale nie wypełnia**. Rdzeń publikuje przez niego swoje pięć
     * momentów, moduł publikuje swoje, a kto słucha — wie wyłącznie rejestr.
     * Miejsce jest przy tym rachunkiem, a nie upodobaniem: `LoopState` dostaje
     * **każdy** moduł, więc publikacja nie kosztuje ani jednego argumentu więcej
     * w `Bootstrapie`.
     */
    private readonly EventRegistry $events;

    /**
     * Rejestr kwerend (krok 53) — **jedyna droga odczytu w tej aplikacji**.
     *
     * Miejsce jest tym samym rachunkiem, co przy rejestrze zdarzeń o kilka linii
     * wyżej: `LoopState` dostaje **każdy** moduł i każdy ekran, więc pytanie
     * o dane nie kosztuje ani jednego argumentu więcej w `Bootstrapie`. Stan go
     * **trzyma, ale nie wypełnia** — wpisują się do niego rdzeń i moduły.
     */
    private readonly QueryRegistry $queries;

    /**
     * Rejestr komend (krok 54) — **jedyna droga do cudzej czynności**.
     *
     * Trzeci rejestr w tym miejscu i **z tego samego rachunku**, co dwa poprzednie:
     * `LoopState` dostaje każdy moduł, więc zamówienie cudzej czynności nie
     * kosztuje ani jednego argumentu więcej w `Bootstrapie`.
     *
     * **Powód, dla którego tu trafił, jest luką w planie, a nie nową funkcją.**
     * Reguła 15g mówi od kroku 53: *moduł zna nazwę cudzej komendy (napis), nigdy
     * jej typ* — ale sama nazwa jest bezużyteczna, dopóki nie ma czego o nią
     * zapytać. Do kroku 54 **żaden moduł nie sięgał po rejestr komend** i nikt
     * tego nie zauważył, bo pierwszy odbiorca — `k8s.deploy-image` — powstał
     * dopiero teraz. Zamówienie cudzej czynności było przez to zapisane w regułach
     * i niewykonalne w kodzie.
     *
     * Rejestr **wchodzi tu wypełniony** (inaczej niż dwa poprzednie): składa go
     * `Bootstrap` z komend rdzenia i modułów, a stan wyłącznie go podaje.
     */
    private ?CommandRegistry $commands = null;

    public function __construct(
        private Settings $settings = new Settings(),
        ?EventRegistry $events = null,
        ?QueryRegistry $queries = null,
    ) {
        $this->events = $events ?? new EventRegistry();
        $this->queries = $queries ?? new QueryRegistry();
        $this->overlays = new OverlayStack($this->events);
        $this->context = new ModuleContext();
    }

    public function events(): EventRegistry
    {
        return $this->events;
    }

    public function queries(): QueryRegistry
    {
        return $this->queries;
    }

    /**
     * Rejestr komend — patrz opis pola.
     *
     * Oddaje **rejestr pusty**, dopóki `Bootstrap` go nie poda, a nie `null`:
     * moduł ma pytać o komendę, a nie sprawdzać, czy jest o co pytać. Nieznana
     * nazwa oddaje wtedy `null` z `find()` i wołający mówi zdaniem — dokładnie
     * tak samo, jak przy module wyłączonym.
     */
    public function commands(): CommandRegistry
    {
        return $this->commands ??= new CommandRegistry();
    }

    /** Podaje rejestr złożony w `Bootstrapie` — wołane **raz**, przy starcie. */
    public function useCommands(CommandRegistry $commands): void
    {
        $this->commands = $commands;
    }

    public function context(): ModuleContext
    {
        return $this->context;
    }

    public function publishContext(ModuleContext $context): void
    {
        $this->context = $context;
    }

    public function settings(): Settings
    {
        return $this->settings;
    }

    public function applySettings(Settings $settings): void
    {
        $this->settings = $settings;
    }

    public function message(): ?Message
    {
        return $this->message;
    }

    /**
     * Komunikat wraz z tonem — i **jedyne miejsce, przez które przechodzą
     * wszystkie** komunikaty aplikacji, więc zarazem najtańsze źródło trzech
     * zdarzeń rdzenia (krok 46).
     *
     * Publikacja stoi za nadaniem komunikatu, nie przed nim: odbiorca ma prawo
     * zapytać stan o to, co właśnie zostało powiedziane, a stan pokazujący jeszcze
     * poprzednie zdanie byłby kłamstwem trwającym jedno wywołanie.
     */
    public function report(Message $message, float $now): void
    {
        $this->message = $message;
        $this->messageDismissableAt = $now + self::MINIMUM_MESSAGE_SECONDS
            + self::SECONDS_PER_MESSAGE_WORD * self::countWords($message->text);

        $this->events->publish(AppEvent::ofTone($message->tone)->value);
    }

    public function reportProblem(string $message, float $now): void
    {
        $this->report(Message::error($message), $now);
    }

    /**
     * Komunikat gasi dopiero naciśnięcie klawisza — ale nie wcześniej, niż
     * minie czas potrzebny na jego przeczytanie. Dzięki temu klawisz naciśnięty
     * odruchowo tuż po błędzie nie zdąży schować informacji, której użytkownik
     * jeszcze nie zobaczył.
     */
    public function dismissMessageIfDue(float $now): void
    {
        if ($this->message !== null && $now >= $this->messageDismissableAt) {
            $this->message = null;
        }
    }

    private static function countWords(string $message): int
    {
        $words = preg_split('/\s+/u', trim($message), -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? 1 : max(1, count($words));
    }

    public function overlays(): OverlayStack
    {
        return $this->overlays;
    }

    /**
     * Czas rozpoczęcia klatki, podawany raz na takt przez pętlę.
     *
     * Zegar stoi tutaj, a nie w komponentach, bo do kroku 19 był tu już jeden
     * jego użytkownik — gaszenie komunikatu — a karetka w polu tekstowym jest
     * drugim. Dwa źródła czasu w jednej klatce potrafiłyby się rozjechać
     * o takt, a komponent z własnym `microtime()` przestaje być testowalny.
     */
    public function tick(float $now): void
    {
        $this->now = $now;
    }

    public function now(): float
    {
        return $this->now;
    }
}
