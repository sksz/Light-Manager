<?php

declare(strict_types=1);

namespace LightManager\Application\Command;

/**
 * Wszystkie komendy uruchomienia — jeden zbiór, niezależny od aktywnego ekranu.
 *
 * Zbiór jest globalny (D39, P18), bo komenda ma być czynnością wywoływaną po
 * nazwie, a nie drugim zestawem klawiszy: użytkownik, który zna nazwę, nie
 * powinien musieć wiedzieć, na którym ekranie wolno jej użyć.
 *
 * **Przestrzeń nazw jest wymuszona**: rdzeń wnosi wyłącznie `core.*`, a moduł
 * z kroku 20 — wyłącznie `<własne id>.*`. Dzięki temu kolizja między dwoma
 * modułami jest niemożliwa z konstrukcji (identyfikatorów pilnuje rejestr
 * modułów), a zostaje wyłącznie kolizja modułu z samym sobą — i tę łapie test.
 *
 * Rejestr jest zwykłym obiektem, nie Singletonem: składa go `Bootstrap`, tak
 * samo jak ekrany.
 */
final class CommandRegistry
{
    /** Przestrzeń nazw komend rdzenia. */
    public const CORE = 'core';

    /** @var array<string, CommandInterface> */
    private array $commands = [];

    /** @var list<CommandRejection> */
    private array $rejections = [];

    /**
     * Dokłada komendy jednego właściciela, odsiewając te, które wyszły poza jego
     * przestrzeń nazw albo powtarzają nazwę już zajętą.
     *
     * @param list<CommandInterface> $commands
     */
    public function add(string $owner, array $commands): void
    {
        $prefix = $owner . '.';

        foreach ($commands as $command) {
            $name = $command->name();

            if (!str_starts_with($name, $prefix) || $name === $prefix) {
                $this->rejections[] = new CommandRejection($owner, $name, 'command.rejected.namespace');

                continue;
            }

            if (isset($this->commands[$name])) {
                $this->rejections[] = new CommandRejection($owner, $name, 'command.rejected.duplicate');

                continue;
            }

            $this->commands[$name] = $command;
        }

        ksort($this->commands);
    }

    public function find(string $name): ?CommandInterface
    {
        return $this->commands[$name] ?? null;
    }

    /** @return list<CommandInterface> w kolejności alfabetycznej */
    public function all(): array
    {
        return array_values($this->commands);
    }

    /** @return list<CommandInterface> komendy, których nazwa zaczyna się od przedrostka */
    public function matching(string $prefix): array
    {
        if ($prefix === '') {
            return $this->all();
        }

        return array_values(array_filter(
            $this->commands,
            static fn (CommandInterface $command): bool => str_starts_with($command->name(), $prefix),
        ));
    }

    /**
     * Najdłuższy wspólny przedrostek nazw pasujących do wpisanego — to, co
     * dopisuje `Tab`.
     *
     * Gdy nic nie pasuje, oddaje wpisany przedrostek bez zmian: uzupełnianie
     * nie ma prawa odebrać użytkownikowi tego, co napisał.
     */
    public function commonPrefix(string $prefix): string
    {
        $matching = $this->matching($prefix);

        if ($matching === []) {
            return $prefix;
        }

        return Prefix::shared(array_map(
            static fn (CommandInterface $command): string => $command->name(),
            $matching,
        ));
    }

    /** @return list<CommandRejection> */
    public function rejections(): array
    {
        return $this->rejections;
    }
}
