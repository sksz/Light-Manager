<?php

declare(strict_types=1);

namespace LightManager\Application\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Domain\ValueObject\Message;

/**
 * Wynik rozbioru wiersza kwerendy: kwerenda gotowa do zapytania albo powód,
 * dla którego zapytać się nie da.
 *
 * Bliźniak `CommandLine` i **nie jest to powtórzenie mechanizmu**: rozbiór
 * wiersza robi jeden parser dla obu (`CommandLineParser::bind()`), a te dwie
 * klasy są wyłącznie kształtem odpowiedzi — komenda wraca z jednym typem,
 * kwerenda z drugim.
 */
final class QueryLine
{
    private function __construct(
        public readonly ?QueryInterface $query,
        public readonly ?CommandInput $input,
        public readonly ?Message $problem,
    ) {
    }

    public static function of(QueryInterface $query, CommandInput $input): self
    {
        return new self($query, $input, null);
    }

    public static function problem(Message $message): self
    {
        return new self(null, null, $message);
    }

    public function isValid(): bool
    {
        return $this->query !== null && $this->input !== null;
    }
}
