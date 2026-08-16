<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandArgumentKind;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Command\SuggestionSource;
use LightManager\Application\Command\SuggestsArguments;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\Port\AudioPort;
use LightManager\Module\Audio\Application\UseCase\ChangeVolumeUseCase;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Cli\Query\CoreReader;

/**
 * `audio.volume <0–100>` — głośność muzyki (krok 36).
 *
 * Pierwsza w projekcie komenda ustawiająca wartość **liczbową**, i to ona
 * odpowiada na pytanie odłożone w kroku 19 (`ChangeSettingUseCase`: „klucze
 * liczbowe nie mają dziś komendy, a ich brzmienie w wierszu nie jest niczym
 * ustalone”). Odpowiedź brzmi: **liczba tak, jak się ją mówi** — `audio.volume 60`,
 * bez jednostki i bez znaku procentu, a rodzaj `Number` sprawia, że wiersz
 * z literami odsiewa parser rdzenia, zanim komenda cokolwiek zobaczy.
 *
 * Przyjmuje **przystanki co dziesięć**, a nie dowolną liczbę z zakresu, i wynika
 * to z kontraktu ustawień modułu, nie z gustu: wartość spoza listy deklaracji
 * wróciłaby z pliku jako domyślna (`ModuleSetting::valueFrom()`), więc zapisane
 * 63 przepadłoby przy pierwszym odczycie. Lista jest zarazem podpowiedzią
 * w oknie komend, więc użytkownik widzi, co wolno, zanim wpisze.
 *
 * Zmiana obowiązuje **natychmiast**, także w trakcie grania, i zapisuje się na
 * dysk — jak każde ustawienie zmienione komendą.
 */
final class VolumeCommand implements CommandInterface, SuggestsArguments
{
    private const ARGUMENT = 'level';

    public function __construct(
        private readonly AudioPort $audio,
        private readonly ChangeVolumeUseCase $changeVolume,
        private readonly LoopState $state,
        /** Odczyt ustawień rdzenia — przez rejestr kwerend (krok 53, D92 nr 3). */
        private readonly CoreReader $core,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return AudioSettings::ID . '.volume';
    }

    public function descriptionKey(): string
    {
        return 'module.' . AudioSettings::ID . '.command.volume';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ARGUMENT,
                'module.' . AudioSettings::ID . '.argument.' . self::ARGUMENT,
                CommandArgumentKind::Number,
                suggestions: SuggestionSource::Fixed,
            ),
        ];
    }

    /** @return list<string> */
    public function suggestions(string $argument, string $prefix): array
    {
        return $argument === self::ARGUMENT
            ? array_map(static fn (int $level): string => (string) $level, AudioSettings::VOLUME_CHOICES)
            : [];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $level = $input->number(self::ARGUMENT, AudioSettings::DEFAULT_VOLUME);

        if (!in_array($level, AudioSettings::VOLUME_CHOICES, true)) {
            // Wartość spoza listy **nie zamyka okna**: użytkownik wpisał ją
            // ręcznie, więc ma ją gdzie poprawić — tak samo, jak przy nazwie
            // motywu spoza listy.
            return CommandOutcome::stay(Message::error($this->text('volume.rejected', [
                'levels' => implode(', ', AudioSettings::VOLUME_CHOICES),
            ])));
        }

        [$settings, $problem] = $this->changeVolume->execute($this->core->settings(), $level);

        $this->state->applySettings($settings);
        $this->audio->useVolume($level);

        return $problem === null
            ? CommandOutcome::done(Message::info($this->text('volume.set', ['level' => (string) $level])))
            : CommandOutcome::done(Message::error($problem));
    }

    /** @param array<string, string> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate('module.' . AudioSettings::ID . '.' . $key, $parameters);
    }
}
