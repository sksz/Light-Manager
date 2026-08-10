<?php

declare(strict_types=1);

namespace LightManager\Application\Command;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;

/**
 * Zamienia wpisany wiersz w komendę wraz z argumentami — jedyne miejsce
 * w aplikacji, które to robi.
 *
 * Gdyby rozbiór należał do komendy, każda robiłaby go po swojemu: jedna dzieliła
 * po spacjach, druga rozumiała cudzysłowy, trzecia milczała przy braku
 * argumentu. Tutaj reguła jest jedna, a komenda dostaje wartości pod nazwami
 * z własnej deklaracji.
 *
 * **Cudzysłowy są w parserze**, bo pierwsza komenda z argumentem to skok do
 * ścieżki (krok 20), a ścieżka bywa ze spacją. Znak ucieczki `\ ` świadomie nie
 * wchodzi: jedna reguła na spację wystarczy, a dwie trzeba by tłumaczyć.
 */
final class CommandLineParser
{
    public function __construct(
        private readonly TranslatorPort $translator,
    ) {
    }

    /**
     * Podział wiersza na słowa z poszanowaniem cudzysłowów prostych i podwójnych.
     *
     * @return list<string>
     */
    public function words(string $line): array
    {
        return $this->tokenize($line)[0];
    }

    /** Gdzie stoi wpisywanie — materiał dla uzupełniania w oknie komend. */
    public function completion(string $line): CommandCompletion
    {
        [$words, $startsNewWord] = $this->tokenize($line);

        if ($startsNewWord) {
            return new CommandCompletion($words[0] ?? '', count($words) - 1, '');
        }

        $last = count($words) - 1;

        if ($last <= 0) {
            return new CommandCompletion($words[0] ?? '', -1, $words[0] ?? '');
        }

        return new CommandCompletion($words[0], $last - 1, $words[$last]);
    }

    public function parse(string $line, CommandRegistry $registry): CommandLine
    {
        $words = $this->words($line);

        if ($words === []) {
            return CommandLine::problem(Message::error($this->translator->translate('command.problem.empty')));
        }

        $name = array_shift($words);
        $command = $registry->find($name);

        if ($command === null) {
            return CommandLine::problem(Message::error(
                $this->translator->translate('command.problem.unknown', ['name' => $name]),
            ));
        }

        return $this->bind($command, $words);
    }

    /**
     * Mapowanie pozycyjne wartości na nazwy z deklaracji wraz ze sprawdzeniem
     * obecności i rodzaju. Istnienia zasobu parser nie sprawdza — o tym, czy
     * katalog da się otworzyć, wie sama komenda.
     *
     * @param list<string> $values
     */
    private function bind(CommandInterface $command, array $values): CommandLine
    {
        $declared = $command->arguments();

        if (count($values) > count($declared)) {
            return CommandLine::problem(Message::error($this->translator->translate(
                'command.problem.extra',
                ['name' => $command->name(), 'count' => (string) count($declared)],
            )));
        }

        $arguments = [];

        foreach ($declared as $position => $argument) {
            $value = $values[$position] ?? null;

            if ($value === null || $value === '') {
                if ($argument->required) {
                    return CommandLine::problem(Message::error($this->translator->translate(
                        'command.problem.missing',
                        ['argument' => $this->translator->translate($argument->labelKey)],
                    )));
                }

                continue;
            }

            if ($argument->kind === CommandArgumentKind::Number && !$this->isNumber($value)) {
                return CommandLine::problem(Message::error($this->translator->translate(
                    'command.problem.number',
                    ['argument' => $this->translator->translate($argument->labelKey), 'value' => $value],
                )));
            }

            $arguments[$argument->name] = $value;
        }

        return CommandLine::of($command, new CommandInput($arguments));
    }

    /** Liczba całkowita, ewentualnie ze znakiem — tyle, ile rdzeń potrafi sprawdzić. */
    private function isNumber(string $value): bool
    {
        return preg_match('/^-?\d+$/', $value) === 1;
    }

    /**
     * @return array{list<string>, bool} słowa oraz to, czy wiersz kończy się
     *                                   odstępem — wtedy zaczęte jest kolejne,
     *                                   jeszcze puste słowo
     */
    private function tokenize(string $line): array
    {
        $words = [];
        $current = '';
        $started = false;
        $quote = null;
        $length = mb_strlen($line);

        for ($position = 0; $position < $length; ++$position) {
            $character = mb_substr($line, $position, 1);

            if ($quote !== null) {
                if ($character === $quote) {
                    $quote = null;

                    continue;
                }

                $current .= $character;

                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
                $started = true;

                continue;
            }

            if ($character === ' ' || $character === "\t") {
                if ($started) {
                    $words[] = $current;
                    $current = '';
                    $started = false;
                }

                continue;
            }

            $current .= $character;
            $started = true;
        }

        if ($started) {
            $words[] = $current;
        }

        return [$words, !$started];
    }
}
