<?php

declare(strict_types=1);

namespace LightManager\Application\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandLineParser;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;

/**
 * Rozbiór wiersza kwerendy — **nie drugi parser, tylko drugie ujście
 * pierwszego**.
 *
 * Podział wiersza na słowa i związanie ich z deklaracją argumentów robi
 * `CommandLineParser`: składnia `nazwa argument argument` jest ta sama, więc jej
 * druga kopia rozjechałaby się z oryginałem przy pierwszej poprawce (cudzysłowy
 * weszły tam w kroku 20 i musiałyby wejść tutaj drugi raz). Tej klasie zostaje
 * to, czym kwerenda różni się od komendy: skąd wziąć wykonawcę i jak nazwać jego
 * brak.
 */
final class QueryLineParser
{
    public function __construct(
        private readonly CommandLineParser $lines,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function parse(string $line, QueryRegistry $registry): QueryLine
    {
        $words = $this->lines->words($line);

        if ($words === []) {
            return QueryLine::problem(Message::error($this->translator->translate('query.problem.empty')));
        }

        $name = array_shift($words);
        $query = $registry->find($name);

        if ($query === null) {
            return QueryLine::problem(Message::error(
                $this->translator->translate('query.problem.unknown', ['name' => $name]),
            ));
        }

        $bound = $this->lines->bind($query->name(), $query->arguments(), $words);

        return $bound instanceof CommandInput
            ? QueryLine::of($query, $bound)
            : QueryLine::problem($bound);
    }
}
