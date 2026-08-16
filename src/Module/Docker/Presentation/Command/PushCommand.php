<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Presentation\DockerQueries;
use LightManager\Module\Docker\Presentation\PushFlow;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `docker.push [obraz]` — wypycha obraz do rejestru (krok 54).
 *
 * Bez argumentu bierze obraz **wskazany na liście**, z argumentem — podany.
 * Wzorzec ten sam, co w `docker.build` i `ssh.get`: komenda niczego nie robi
 * sama, tylko prowadzi w `PushFlow`, czyli tam, gdzie prowadzi klawisz (11n).
 *
 * **Jest zarazem drugim etapem czynności `k8s.deploy-image` widzianym od strony
 * wołającego** — tamten moduł zna tę komendę wyłącznie **z nazwy**, nigdy z typu
 * (15g).
 */
final class PushCommand implements CommandInterface, OpensOverlay
{
    private const ARGUMENT = 'image';

    public function __construct(
        private readonly PushFlow $pushes,
        private readonly DockerQueries $reader,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return DockerSettings::ID . '.push';
    }

    public function descriptionKey(): string
    {
        return 'module.' . DockerSettings::ID . '.command.push';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ARGUMENT,
                'module.' . DockerSettings::ID . '.argument.image',
                required: false,
            ),
        ];
    }

    public function overlayFor(CommandInput $input): OverlayOutcome
    {
        return $this->pushes->request($this->imageFrom($input));
    }

    /**
     * Wykonanie **bez okna — i tutaj to naprawdę się zdarza**, w odróżnieniu od
     * `docker.build`.
     *
     * Tą drogą idzie czynność `k8s.deploy-image`: prowadzi **własne** okno od
     * pierwszego etapu, więc drugie nie miałoby gdzie stanąć (stos ma jedno
     * piętro). Nazwa docelowa bierze się wtedy z propozycji — rejestr
     * i użytkownik z ustawień modułu — bo nie ma kogo o nią zapytać.
     *
     * Rozdzielenie jest przez to **znaczące, a nie formalne**: `overlayFor()`
     * pyta o nazwę, bo za nim stoi człowiek; `execute()` bierze propozycję, bo za
     * nim stoi drugi moduł. Ta sama komenda, dwa wejścia — i to jest dokładnie ten
     * podział, dla którego zdolność `OpensOverlay` powstała w kroku 47.
     */
    public function execute(CommandInput $input): CommandOutcome
    {
        $image = $this->imageFrom($input);

        if ($image === '') {
            return CommandOutcome::done(Message::warning(
                $this->translator->translate('module.' . DockerSettings::ID . '.push.noImage'),
            ));
        }

        $target = $this->pushes->suggest($image);
        $this->pushes->begin($image, $target);

        return CommandOutcome::done(Message::info(
            $this->translator->translate('module.' . DockerSettings::ID . '.push.started', ['tag' => $target]),
        ));
    }

    /**
     * Obraz z argumentu albo ten pod kursorem.
     *
     * Obraz **osierocony** (bez ani jednej etykiety) oddaje tu pusty napis, a nie
     * skrót treści, i jest to zamierzone: skrótu treści nie da się wypchnąć do
     * rejestru, więc podanie go udawałoby możliwość, której nie ma. `PushFlow`
     * odpowiada wtedy zdaniem.
     */
    private function imageFrom(CommandInput $input): string
    {
        if ($input->has(self::ARGUMENT)) {
            return trim($input->text(self::ARGUMENT));
        }

        return $this->reader->images()->selected()->tags[0] ?? '';
    }
}
