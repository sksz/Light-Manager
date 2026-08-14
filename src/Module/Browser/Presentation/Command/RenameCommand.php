<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Command;

use LightManager\Application\Command\AppliesToSelection;
use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandArgumentKind;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Domain\Exception\DomainException;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Presentation\EntryOperations;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `browser.rename [nazwa]` — zmiana nazwy zaznaczonego wpisu (krok 41).
 *
 * **Pierwsza komenda w projekcie, której argument jest treścią, a nie wskazaniem**
 * — i to nie jest ciekawostka, tylko powód, dla którego ta komenda w ogóle
 * wygląda tak, jak wygląda.
 *
 * **Krok 47 (D78) zdjął z niej dług i odwrócił zdanie, które tu stało.** Komenda
 * miała rzekomo nie móc otworzyć okna przez granicę warstw; granica nigdy nie
 * była przeszkodą — komendy leżą w `Presentation`, więc okno widzą (patrz
 * `OpensOverlay`). Argument jest odtąd **opcjonalny**: podany zmienia nazwę od
 * razu, pominięty otwiera to samo okno, które otwiera `F6`. Dzięki temu komenda
 * ma sens także w menu kontekstowym i deklaruje `AppliesToSelection`.
 *
 * Czynność mieszka w `EntryOperations`, wspólnie z klawiszem: dwa wejścia, jedno
 * miejsce (wzorzec `HiddenEntries` z kroku 32).
 *
 * Wyjątku nie wypuszcza — komenda wywołana z okna nie ma nad sobą łapacza
 * `DomainException` (precedens `JumpCommand`), a **okno zostaje otwarte**, bo
 * wpisaną nazwę da się poprawić.
 */
final class RenameCommand implements CommandInterface, AppliesToSelection, OpensOverlay
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
                required: false,
            ),
        ];
    }

    /** Bez nazwy — okno z nazwą bieżącą; z nazwą — czynność bez okna (krok 47). */
    public function overlayFor(CommandInput $input): ?OverlayOutcome
    {
        return $input->has(self::ARGUMENT) ? null : $this->entries->renameRequest();
    }

    public function appliesTo(ModuleContext $context): bool
    {
        return $context->selection !== null;
    }

    public function inputFor(ModuleContext $context): CommandInput
    {
        // Menu nie ma pola tekstowego, więc pozycja wchodzi **bez** argumentu —
        // i właśnie dlatego otwiera okno nazwy zamiast pytać o nią wierszem.
        return new CommandInput();
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
