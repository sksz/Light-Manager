<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandArgumentKind;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Domain\Exception\DomainException;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Presentation\EntryOperations;

/**
 * `browser.rename <nazwa>` — zmiana nazwy zaznaczonego wpisu (krok 41).
 *
 * **Pierwsza komenda w projekcie, której argument jest treścią, a nie wskazaniem**
 * — i to nie jest ciekawostka, tylko powód, dla którego ta komenda w ogóle
 * wygląda tak, jak wygląda. Klawisz `F6` otwiera okno z nazwą bieżącą; komenda
 * okna otworzyć **nie może**, bo `CommandOutcome` leży w `Application` i wskazuje
 * ekran identyfikatorem, a okna nakładane rejestru identyfikatorów nie mają
 * (D39, D75 — rozstrzygnięcie 5). Nazwa przychodzi więc w wierszu.
 *
 * Z tego samego powodu komenda **nie deklaruje `AppliesToSelection`**: menu
 * kontekstowe wykonuje pozycję bez pytania o cokolwiek, a zmiana nazwy bez nazwy
 * nie jest czynnością. Zobowiązanie wobec kroku 32 zostaje na to długiem —
 * spłaci je krok, który da rdzeniowi okna pod identyfikatorem, jeśli kiedyś taki
 * powstanie.
 *
 * Czynność mieszka w `EntryOperations`, wspólnie z klawiszem: dwa wejścia, jedno
 * miejsce (wzorzec `HiddenEntries` z kroku 32).
 *
 * Wyjątku nie wypuszcza — komenda wywołana z okna nie ma nad sobą łapacza
 * `DomainException` (precedens `JumpCommand`), a **okno zostaje otwarte**, bo
 * wpisaną nazwę da się poprawić.
 */
final class RenameCommand implements CommandInterface
{
    private const ARGUMENT = 'name';

    public function __construct(
        private readonly EntryOperations $entries,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return BrowserSettings::ID . '.rename';
    }

    public function descriptionKey(): string
    {
        return 'module.' . BrowserSettings::ID . '.command.rename';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ARGUMENT,
                'module.' . BrowserSettings::ID . '.argument.name',
                CommandArgumentKind::Text,
            ),
        ];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        try {
            $message = $this->entries->rename($input->text(self::ARGUMENT));
        } catch (DomainException $problem) {
            return CommandOutcome::stay($this->problem($problem));
        }

        return CommandOutcome::done($message);
    }

    /**
     * Zdanie o niepowodzeniu: wyjątek podaje je sam, jeśli umie
     * (`DescribesProblem`, D42), a jeśli nie — zostaje zdanie ogólne rdzenia.
     */
    private function problem(DomainException $problem): Message
    {
        if ($problem instanceof DescribesProblem) {
            return Message::error($this->translator->translate(
                $problem->problemKey(),
                $problem->problemParameters(),
            ));
        }

        return Message::error($this->translator->translate('problem.unexpected'));
    }
}
