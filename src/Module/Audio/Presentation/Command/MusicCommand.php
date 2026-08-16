<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Presentation\Command;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\PlaylistPlayer;
use LightManager\Module\Audio\Presentation\AudioQueries;

/**
 * `audio.music` — muzyka rusza i staje (krok 36).
 *
 * Jedna komenda-przełącznik zamiast pary „graj” i „zatrzymaj”, i to nie jest
 * oszczędność nazw: silnik **pauzuje**, a nie przewija (sprawdzone na starcie
 * kroku), więc drugie naciśnięcie wznawia w tym samym miejscu. Para komend
 * obiecywałaby rozróżnienie, którego pod spodem nie ma. Wzorem jest
 * `core.fullscreen` — też przełącznik bez argumentu.
 *
 * **Krok 45 zabiera jej dwie rzeczy i to jest cała zmiana.** Utworu już nie
 * wybiera — gra to, co wskazuje playlista — i nie jest już jedynym sposobem
 * uruchomienia muzyki: ruszają ją także `Enter` w oknie modułu i autostart, bo
 * kontrakt modułu poznał takt (`NeedsTick`, D82 nr 1). Zdanie z kroku 36
 * o „jedynym sposobie” zostaje odwołane wraz z powodem, który je uzasadniał.
 *
 * Co się nie zmieniło: komenda pozostaje **przełącznikiem**, bo silnik pauzuje,
 * a nie przewija (krok 36), więc drugie naciśnięcie wznawia w tym samym miejscu.
 */
final class MusicCommand implements CommandInterface
{
    public function __construct(
        private readonly PlaylistPlayer $player,
        private readonly AudioQueries $queries,
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
        if ($this->queries->nowPlaying()->playing) {
            $this->player->pause();

            return CommandOutcome::done(Message::info($this->text('stopped')));
        }

        $problem = $this->player->resume();

        if ($problem !== null) {
            return CommandOutcome::done(Message::error($problem));
        }

        // Nazwa pozycji, a nie cała ścieżka: pasek stanu ma jeden wiersz, a
        // użytkownik i tak wie, gdzie trzyma muzykę.
        $playing = $this->queries->nowPlaying()->entry;

        return CommandOutcome::done(Message::info($this->text('playing', [
            'track' => $playing === null ? '' : $playing->name,
        ])));
    }

    /** @param array<string, string> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate('module.' . AudioSettings::ID . '.' . $key, $parameters);
    }
}
