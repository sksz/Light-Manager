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
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Presentation\EntryOperations;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `browser.delete [nazwa]` — usunięcie wpisu (krok 47, D78).
 *
 * **Komenda, której krok 41 nie zdołał napisać.** Usuwanie prowadzi przez okna —
 * pytanie w wariancie groźnym, a przy katalogu jeszcze liczenie i pasek postępu —
 * a komenda okna otworzyć wtedy nie umiała. Zdolność `OpensOverlay` to zmienia
 * i cała treść tej klasy sprowadza się do jednego wywołania: droga jest ta sama,
 * którą idzie `F8`, bo prowadzi przez `EntryOperations`.
 *
 * Nazwa jest **opcjonalna** (rozstrzygnięcie 3): bez niej usuwa wpis pod
 * kursorem, z nią — wskazany, o ile jest w katalogu panelu czynnego. Nazwa jest
 * przy tym nazwą, nie ścieżką: usunąć wolno to, co widać na liście.
 *
 * **Pytania nie da się pominąć** i to jest jedyny powód, dla którego `execute()`
 * ma tu treść niepodobną do reszty: wołający, który nie umie otworzyć okna, nie
 * ma jak zapytać, więc nie dostaje usunięcia — dostaje zdanie. Dziś takiego
 * wołającego w aplikacji nie ma (okno komend i menu pytają wpierw o zdolność),
 * ale kontrakt `CommandInterface` wymaga metody, a metoda usuwająca bez pytania
 * byłaby drugą drogą do nieodwracalnej czynności.
 */
final class DeleteCommand implements CommandInterface, AppliesToSelection, OpensOverlay
{
    private const ARGUMENT = 'name';

    public function __construct(
        private readonly EntryOperations $entries,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return BrowserSettings::ID . '.delete';
    }

    public function descriptionKey(): string
    {
        return 'module.' . BrowserSettings::ID . '.command.delete';
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

    /**
     * Zawsze coś oddaje: okno (pytanie, liczenie, postęp) albo samo zdanie —
     * gdy nie ma czego usuwać albo gdy pytać nie trzeba i wpis już zniknął.
     *
     * Typ jest przez to **węższy niż w zdolności** (`OverlayOutcome`, nie
     * `?OverlayOutcome`) i tak ma być: `null` znaczy „wykonaj mnie zwyczajnie”,
     * a usunięcie zwyczajnej drogi nie ma.
     */
    public function overlayFor(CommandInput $input): OverlayOutcome
    {
        return $this->entries->deleteRequest(
            $input->has(self::ARGUMENT) ? $input->text(self::ARGUMENT) : null,
        );
    }

    public function appliesTo(ModuleContext $context): bool
    {
        return $context->selection !== null;
    }

    public function inputFor(ModuleContext $context): CommandInput
    {
        // Menu usuwa **to, na czym stoi kursor**, więc argumentu nie podaje:
        // nazwa z kontekstu i tak wskazałaby ten sam wpis, tylko dłuższą drogą.
        return new CommandInput();
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        return CommandOutcome::stay(Message::error($this->translator->translate(
            'module.' . BrowserSettings::ID . '.delete.needsOverlay',
        )));
    }
}
