<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Command\SuggestionSource;
use LightManager\Application\Command\SuggestsArguments;
use LightManager\Application\Dto\SettingKey;
use LightManager\Application\UseCase\ChangeSettingUseCase;
use LightManager\Domain\ValueObject\MessageTone;
use LightManager\Presentation\Cli\LoopState;

/**
 * Komenda ustawiająca jedno ustawienie na wartość wskazaną — `core.theme grafit`,
 * `core.language pl`.
 *
 * Pierwsze w projekcie komendy z argumentem, a przy okazji jedyni w kroku 19
 * użytkownicy podpowiedzi **stałych**: lista motywów i lista języków nie
 * zmieniają się przez całe uruchomienie, więc rdzeń pyta o nie raz, przy starcie.
 *
 * Zmiana idzie tą samą drogą co na ekranie ustawień (`ChangeSettingUseCase`),
 * więc zapisuje się na dysk i obowiązuje od następnej klatki. Wartość spoza
 * listy **nie zamyka okna**: użytkownik wpisał ją ręcznie, więc ma ją gdzie
 * poprawić.
 */
final class SettingCommand implements CommandInterface, SuggestsArguments
{
    /** @param list<string> $choices dopuszczalne wartości — one też są podpowiedziami */
    public function __construct(
        private readonly string $name,
        private readonly SettingKey $key,
        private readonly string $argumentName,
        private readonly array $choices,
        private readonly LoopState $state,
        private readonly ChangeSettingUseCase $change,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function descriptionKey(): string
    {
        return 'command.' . $this->name;
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                $this->argumentName,
                'command.argument.' . $this->argumentName,
                suggestions: SuggestionSource::Fixed,
            ),
        ];
    }

    public function suggestions(string $argument, string $prefix): array
    {
        return $argument === $this->argumentName ? $this->choices : [];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $current = $this->state->settings();
        [$settings, $message] = $this->change->set($current, $this->key, $input->text($this->argumentName));

        $this->state->applySettings($settings);

        // Wartość odrzucona zostawia okno otwarte wraz z wpisanym wierszem —
        // tak samo, jak zostawia je nieznana nazwa komendy.
        if ($settings->equals($current) && $message?->tone === MessageTone::Error) {
            return CommandOutcome::stay($message);
        }

        return CommandOutcome::done($message);
    }
}
