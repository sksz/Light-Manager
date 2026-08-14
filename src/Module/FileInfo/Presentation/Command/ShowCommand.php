<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Presentation\Command;

use LightManager\Application\Command\AppliesToSelection;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Module\FileInfo\Application\FileInfoSettings;

/**
 * `file-info.show` — opis zaznaczonego wpisu (krok 32).
 *
 * Pierwsza komenda tego modułu i zarazem **pierwsza w projekcie, która istnieje
 * po to, żeby czynność miała nazwę**: opis wpisu otwierał się dotąd wyłącznie
 * skrótem `Ctrl`+`D`, więc czynność była w aplikacji, ale nie było jej
 * w rejestrze — a menu kontekstowe pokazuje wyłącznie to, co w rejestrze stoi.
 * Ta sama zmiana daje ją oknu komend, bo rejestr jest jeden.
 *
 * Argumentu nie ma i mieć nie może: opis dotyczy **zaznaczenia**, a nie ścieżki
 * z wiersza. Wpis, którego dotyczy, wskazuje kontekst sesji — ten sam, który
 * czyta ekran modułu (`ReadsContext`), więc komenda nie musi go nawet oglądać.
 * Ogląda go wyłącznie po to, by odpowiedzieć, czy ma sens: na pustym katalogu
 * nie ma czego opisać.
 *
 * Ekran wskazuje **identyfikatorem**, jak każda komenda otwierająca ekran
 * (D39, P24); tłumaczy go na obiekt `InputHandler`, jedyne miejsce znające
 * komplet ekranów.
 */
final class ShowCommand implements CommandInterface, AppliesToSelection
{
    public function name(): string
    {
        return FileInfoSettings::ID . '.show';
    }

    public function descriptionKey(): string
    {
        return 'module.' . FileInfoSettings::ID . '.command.show';
    }

    public function arguments(): array
    {
        return [];
    }

    /** Opis powstaje i dla pliku, i dla katalogu — dla katalogu liczy się w nim `du`. */
    public function appliesTo(ModuleContext $context): bool
    {
        return $context->kind !== ContextEntryKind::None;
    }

    public function inputFor(ModuleContext $context): CommandInput
    {
        return new CommandInput();
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        return CommandOutcome::opens(FileInfoSettings::ID);
    }
}
