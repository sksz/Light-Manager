<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Command;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\Exception\DomainException;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Presentation\HiddenEntries;

/**
 * `browser.hidden` — pokazanie albo ukrycie wpisów ukrytych (krok 32).
 *
 * Nazwa dla czynności, którą przeglądarka miała od kroku 21 wyłącznie pod
 * kropką. Zdolności `AppliesToSelection` **nie deklaruje i nie ma prawa
 * zadeklarować**: dotyczy panelu, a nie zaznaczenia, więc w menu kontekstowym
 * byłaby pozycją, która na pytanie „co da się zrobić z tym plikiem” odpowiada
 * czymś o widoku. To jest granica, na której menu różni się od okna komend —
 * i dlatego ta komenda jest w rejestrze, a w menu jej nie ma.
 *
 * Samej czynności tu nie ma: mieszka w `HiddenEntries`, wspólna z klawiszem,
 * razem z kolejnością kroków, której nie wolno odwrócić.
 */
final class HiddenCommand implements CommandInterface
{
    public function __construct(
        private readonly HiddenEntries $entries,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return BrowserSettings::ID . '.hidden';
    }

    public function descriptionKey(): string
    {
        return 'module.' . BrowserSettings::ID . '.command.hidden';
    }

    public function arguments(): array
    {
        return [];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        try {
            return CommandOutcome::done($this->entries->flip());
        } catch (DomainException) {
            // Katalogu nie da się przeczytać ponownie — ustawienie zostaje
            // wtedy takie, jakie było, bo zapis idzie po odczycie.
            return CommandOutcome::done(Message::error(
                $this->translator->translate('module.' . BrowserSettings::ID . '.hidden.failed'),
            ));
        }
    }
}
