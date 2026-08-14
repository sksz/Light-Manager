<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Presentation\Command;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\Port\AudioPort;
use LightManager\Presentation\Cli\LoopState;

/**
 * `audio.music` — muzyka rusza i staje (krok 36).
 *
 * Jedna komenda-przełącznik zamiast pary „graj” i „zatrzymaj”, i to nie jest
 * oszczędność nazw: silnik **pauzuje**, a nie przewija (sprawdzone na starcie
 * kroku), więc drugie naciśnięcie wznawia w tym samym miejscu. Para komend
 * obiecywałaby rozróżnienie, którego pod spodem nie ma. Wzorem jest
 * `core.fullscreen` — też przełącznik bez argumentu.
 *
 * Komenda jest **jedynym sposobem uruchomienia muzyki** i to jest rozstrzygnięcie
 * ze startu kroku (D70): kontrakt modułu nie zna cyklu życia, więc autostartu nie
 * ma — moduł nie miałby od kogo dostać momentu startu, a dokładanie rdzeniowi
 * zdolności „obudź mnie” dla jednego użytkownika byłoby rozszerzeniem rdzenia dla
 * wygody modułu.
 *
 * Ustawienia czyta ze stanu pętli, a nie z portu konfiguracji, bo zakładka
 * ustawień zmienia je **w trakcie uruchomienia** — moduł czytający plik
 * pokazywałby wartość sprzed zmiany (ta sama zależność i z tego samego powodu
 * stoi w `BrowserModule` od kroku 21).
 */
final class MusicCommand implements CommandInterface
{
    public function __construct(
        private readonly AudioPort $audio,
        private readonly LoopState $state,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return AudioSettings::ID . '.music';
    }

    public function descriptionKey(): string
    {
        return 'module.' . AudioSettings::ID . '.command.music';
    }

    public function arguments(): array
    {
        return [];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        if ($this->audio->isPlaying()) {
            $this->audio->stop();

            return CommandOutcome::done(Message::info($this->text('stopped')));
        }

        $settings = $this->state->settings();
        $track = AudioSettings::track($settings);

        $problem = $this->audio->play(
            $track,
            AudioSettings::volume($settings),
            AudioSettings::loops($settings),
        );

        if ($problem !== null) {
            return CommandOutcome::done(Message::error($problem));
        }

        // Nazwa pliku, a nie cała ścieżka: pasek stanu ma jeden wiersz, a
        // użytkownik i tak wie, gdzie trzyma muzykę.
        return CommandOutcome::done(Message::info($this->text('playing', ['track' => basename($track)])));
    }

    /** @param array<string, string> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate('module.' . AudioSettings::ID . '.' . $key, $parameters);
    }
}
