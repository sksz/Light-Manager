<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation\Command;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Presentation\ClusterScreen;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `k8s.context` — wybór klastra oknem (krok 52).
 *
 * Komenda **otwierająca okno**, czyli dokładnie to, po co powstała zdolność
 * `OpensOverlay` w kroku 47: do tamtego kroku komenda umiała wyłącznie zmienić
 * ekran i powiedzieć zdanie, więc czynność wymagająca wyboru nie miała jak
 * wejść do okna komend.
 *
 * Czynność ma dwa wejścia — klawisz `c` na ekranie i tę komendę — i mieszka
 * **raz** (reguła 11n): oba wołają `ClusterScreen::openContextChoice()`.
 */
final class ContextCommand implements CommandInterface, OpensOverlay
{
    public function __construct(private readonly ClusterScreen $screen)
    {
    }

    public function name(): string
    {
        return KubernetesSettings::ID . '.context';
    }

    public function descriptionKey(): string
    {
        return 'module.' . KubernetesSettings::ID . '.command.context';
    }

    public function arguments(): array
    {
        return [];
    }

    /**
     * Okno wyboru klastra — **zawsze**, bo komenda bez argumentów nie ma innej
     * drogi do odpowiedzi.
     *
     * Typ zwracany kontraktu dopuszcza `null` („wykonaj mnie zwyczajnie”), ale ta
     * komenda z niego nie korzysta i deklaruje to sygnaturą.
     */
    public function overlayFor(CommandInput $input): OverlayOutcome
    {
        return OverlayOutcome::replace($this->screen->openContextChoice());
    }

    /**
     * Wykonanie bez okna — droga, którą komenda idzie, gdy wołający okien nie
     * obsługuje.
     *
     * Nie jest martwa: kontrakt komendy nie gwarantuje, że każdy wołający zapyta
     * o `overlayFor()`, a komenda ma działać u każdego. Otwarcie ekranu jest tu
     * najbliższym sensownym skutkiem — użytkownik ląduje tam, gdzie klawisz `c`
     * jest na wyciągnięcie ręki.
     */
    public function execute(CommandInput $input): CommandOutcome
    {
        return CommandOutcome::opens(ClusterScreen::ID);
    }
}
