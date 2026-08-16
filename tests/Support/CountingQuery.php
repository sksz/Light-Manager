<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryRegistry;
use LightManager\Application\Query\QueryResult;
use RuntimeException;

/**
 * Kwerenda, która **liczy, ile razy ją zapytano** — atrapa dla routingu z pamięcią
 * pokoleń (krok 53).
 *
 * Licznik jest tu jedynym uczciwym sposobem sprawdzenia, czy pamięć rejestru
 * działa: czas mierzony zegarem mówiłby o maszynie, a nie o tym, czy wynik został
 * przeliczony (reguła 16b i wzorzec z kroku 26).
 */
final class CountingQuery implements QueryInterface
{
    public int $asked = 0;

    public int $generation = 1;

    public function __construct(
        private readonly string $name,
        private readonly bool $volatile = false,
        private readonly ?QueryRegistry $nested = null,
        private readonly string $nestedName = '',
        private readonly bool $throws = false,
    ) {
    }

    public static function volatile(string $name): self
    {
        return new self($name, volatile: true);
    }

    /** Kwerenda, która próbuje zapytać drugą — sprawdzian reguły nr 3. */
    public static function asking(string $name, QueryRegistry $registry, string $nested): self
    {
        return new self($name, nested: $registry, nestedName: $nested);
    }

    public static function throwing(string $name): self
    {
        return new self($name, throws: true);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function descriptionKey(): string
    {
        return 'query.' . $this->name;
    }

    /** @return list<CommandArgument> */
    public function arguments(): array
    {
        return [new CommandArgument('pane', 'query.argument.pane', required: false)];
    }

    public function generation(): int
    {
        return $this->volatile ? self::VOLATILE : $this->generation;
    }

    public function ask(CommandInput $input): QueryResult
    {
        ++$this->asked;

        if ($this->throws) {
            throw new RuntimeException('kwerenda nie powinna rzucać');
        }

        if ($this->nested !== null) {
            return $this->nested->ask($this->nestedName);
        }

        return QueryResult::value('name', $this->name);
    }
}
