<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleContext;
use LightManager\Domain\Aggregate\Directory;
use LightManager\Domain\ValueObject\Message;

/**
 * Dane przenoszone między iteracjami pętli: bieżący katalog wraz z zaznaczeniem,
 * ustawienia, komunikat, okna nakładane i czas bieżącej klatki.
 *
 * Który ekran jest aktywny — **nie** jest już częścią tego stanu. Ekrany są od
 * kroku 18 obiektami, a te muszą powstać po `LoopState`, bo z niego czytają;
 * trzymanie ich tutaj wymagałoby ekranu pustego na czas budowy. Aktywnym
 * ekranem zarządza `ScreenStack`.
 *
 * Widoczność wpisów ukrytych nie jest osobnym znacznikiem, bo od kroku 14 jest
 * ustawieniem jak każde inne — jedno miejsce prawdy zamiast dwóch, które
 * musiałyby się pilnować nawzajem.
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
     * Stan go **trzyma**, ale nie **wypełnia**: publikuje go ekran, który zna
     * bieżące miejsce — w tym kroku jeszcze rdzeniowy `BrowserScreen`, od kroku
     * 21 ekran modułu przeglądarki. Brak wydawcy daje kontekst pusty, nie `null`:
     * odbiorca ma czytać, a nie sprawdzać istnienie.
     */
    private ModuleContext $context;

    public function __construct(
        private Directory $directory,
        private Settings $settings = new Settings(),
    ) {
        $this->overlays = new OverlayStack();
        $this->context = new ModuleContext();
    }

    public function context(): ModuleContext
    {
        return $this->context;
    }

    public function publishContext(ModuleContext $context): void
    {
        $this->context = $context;
    }

    public function directory(): Directory
    {
        return $this->directory;
    }

    public function enterDirectory(Directory $directory): void
    {
        $this->directory = $directory;
    }

    public function settings(): Settings
    {
        return $this->settings;
    }

    public function applySettings(Settings $settings): void
    {
        $this->settings = $settings;
    }

    public function showsHiddenEntries(): bool
    {
        return $this->settings->showHiddenEntries;
    }

    public function message(): ?Message
    {
        return $this->message;
    }

    public function report(Message $message, float $now): void
    {
        $this->message = $message;
        $this->messageDismissableAt = $now + self::MINIMUM_MESSAGE_SECONDS
            + self::SECONDS_PER_MESSAGE_WORD * self::countWords($message->text);
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
