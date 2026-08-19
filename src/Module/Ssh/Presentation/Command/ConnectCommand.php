<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Command\SuggestionSource;
use LightManager\Application\Command\SuggestsArguments;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Ssh\Application\SshSettings;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Presentation\ConnectFlow;
use LightManager\Module\Ssh\Presentation\SshQueries;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `ssh.connect <nazwa>` — łączy z wpisem książki hostów (krok 48).
 *
 * **Komenda potrzebuje okna i mówi o tym wprost** (`OpensOverlay`, krok 47) —
 * i to nie z wygody. Łączenie z hostem nieznanym zatrzymuje się na pytaniu
 * o odcisk, a pytanie musi mieć gdzie stanąć; komenda kończąca się w chwili
 * uruchomienia pracy zostawiłaby stan „czekam na człowieka", którego nikt by nie
 * zobaczył. Dzięki zdolności z kroku 47 trafia zarazem do menu `F9` za darmo.
 *
 * Okno bierze z `ConnectFlow` — **tego samego**, którym prowadzi połączenie
 * `Enter` w spisie hostów (11n). Gdyby budowała je sama, byłaby drugą
 * implementacją tej samej czynności, a taka pamiętałaby o odcisku dopóty, dopóki
 * ktoś nie poprawiłby jednej z nich.
 *
 * Podpowiedzi biorą się z **książki hostów**, bo to jedyny spis nazw, które ta
 * komenda przyjmuje.
 */
final class ConnectCommand implements CommandInterface, SuggestsArguments, OpensOverlay
{
    private const ARGUMENT = 'host';

    public function __construct(
        private readonly SshQueries $reader,
        private readonly ConnectFlow $flow,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return SshSettings::ID . '.connect';
    }

    public function descriptionKey(): string
    {
        return 'module.' . SshSettings::ID . '.command.connect';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ARGUMENT,
                'module.' . SshSettings::ID . '.argument.host',
                suggestions: SuggestionSource::OnDemand,
            ),
        ];
    }

    public function suggestions(string $argument, string $prefix): array
    {
        if ($argument !== self::ARGUMENT) {
            return [];
        }

        $matching = [];

        foreach ($this->reader->book()->names() as $name) {
            if ($prefix === '' || stripos($name, $prefix) === 0) {
                $matching[] = $name;
            }
        }

        return $matching;
    }

    /**
     * Okno łańcucha — albo `null`, gdy nie ma z czym się łączyć.
     *
     * `null` znaczy „wykonaj mnie zwyczajnie", a `execute()` oddaje wtedy zdanie
     * o nieznanej nazwie. To jest dokładnie ten podział, dla którego zdolność
     * oddaje `?OverlayOutcome`, a nie samo okno: „okno albo zdanie" to jedna
     * odpowiedź, nie dwie.
     */
    public function overlayFor(CommandInput $input): ?OverlayOutcome
    {
        $profile = $this->profileFrom($input);

        if ($profile === null) {
            return null;
        }

        // `replace()`, a nie otwarcie nad spodem: stos ma jedno piętro, a oknem
        // stojącym w tej chwili jest okno komend, które właśnie ustępuje miejsca.
        return OverlayOutcome::replace($this->flow->begin($profile));
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $name = trim($input->text(self::ARGUMENT));

        return CommandOutcome::done(Message::error(
            $this->translator->translate('module.' . SshSettings::ID . '.message.unknown', ['host' => $name]),
        ));
    }

    private function profileFrom(CommandInput $input): ?HostProfile
    {
        return $this->reader->book()->find(trim($input->text(self::ARGUMENT)));
    }
}
