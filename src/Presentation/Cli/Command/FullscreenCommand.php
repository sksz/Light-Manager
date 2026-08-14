<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Command;

use Closure;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;

/**
 * `core.fullscreen` — okno zajmuje cały ekran i wraca dokładnie tam, skąd
 * wyszło (krok 37).
 *
 * Komenda **istnieje wyłącznie w torze okienkowym**: `Bootstrap` dokłada ją do
 * rejestru dopiero po wybraniu `--window`, bo pełny ekran nie znaczy nic
 * w terminalu, a okno komend ma pokazywać to, co działa tu i teraz (precedens
 * kroku 30). Ten sam warunek rządzi skrótem `F11`.
 *
 * Samo przełączenie przychodzi domknięciem — wzorem reguły 10 („okno oddaje
 * czynność domknięciem”) i z tego samego powodu: dzięki niemu komenda nie zna
 * ani jednej klasy z `Infrastructure/Glfw`, a jej sprawdzenie nie wymaga
 * otwartego okna. Kto woła GLFW, wie `Bootstrap` i tylko on.
 */
final class FullscreenCommand implements CommandInterface
{
    /** @param Closure(): bool $toggle przełącza pełny ekran i oddaje stan po przełączeniu */
    public function __construct(
        private readonly TranslatorPort $translator,
        private readonly Closure $toggle,
    ) {
    }

    public function name(): string
    {
        return 'core.fullscreen';
    }

    public function descriptionKey(): string
    {
        return 'command.core.fullscreen';
    }

    public function arguments(): array
    {
        return [];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $fullscreen = ($this->toggle)();

        return CommandOutcome::done(Message::info($this->translator->translate(
            $fullscreen ? 'command.fullscreen.on' : 'command.fullscreen.off',
        )));
    }
}
