<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Command;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;

/**
 * Komenda otwierająca ekran rdzenia — `core.settings` i `core.help`.
 *
 * Jedna klasa na oba, bo różnią się wyłącznie dwoma napisami: nazwą komendy
 * i identyfikatorem ekranu. Dwie bliźniacze klasy różniące się stałą to nie
 * jest „wyraźniej”, tylko dwa miejsca do poprawienia przy każdej zmianie.
 *
 * Ekran wskazuje **identyfikatorem**: kontrakt komendy leży w `Application`
 * i obiektu ekranu zobaczyć nie może (D39, P24). Napis na obiekt tłumaczy
 * `InputHandler` — jedyne miejsce, które zna komplet ekranów.
 */
final class ScreenCommand implements CommandInterface
{
    public function __construct(
        private readonly string $name,
        private readonly string $screenId,
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
        return [];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        return CommandOutcome::opens($this->screenId);
    }
}
