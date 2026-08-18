<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Kubernetes\Application\Clusters;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Domain\Exception\InvalidClusterNameException;
use LightManager\Module\Kubernetes\Domain\ValueObject\ClusterProfile;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * Łańcuch okien, którym prowadzi się wpis klastra (krok 59, wzorem
 * `EnvironmentFlow` z kroku 58).
 *
 * Rdzeń nie ma komponentu formularza (reguła 13), więc wpis powstaje **oknami
 * po kolei**: stos okien ma jedno piętro (D75), każde ogniwo ustępuje
 * następnemu przez `OverlayOutcome::replace()`, a wartości niosą się
 * domknięciami. Trzy ogniwa: nazwa → plik → kontekst.
 *
 * **Okna rodzaju tu nie ma**, w odróżnieniu od środowisk Dockera: klaster ma
 * jeden kształt, a różnicę robi wyłącznie treść pól. Zmiana (`F4`) idzie tym
 * samym łańcuchem z wypełnionymi polami.
 *
 * **Ogniw jest trzy, nie cztery, i to jest rozstrzygnięcie z kodu, nie z
 * upodobania**: `PromptOverlay` na pustym polu świadomie nie robi nic (krok 41),
 * a przestrzeń nazw ma prawo zostać pusta — pusta znaczy „ta z pliku". Czwarte
 * okno zawiesiłoby przez to łańcuch na wpisie, który przestrzeni nie pilnuje.
 * Przestrzeń zmienia się odtąd klawiszem `n` na ekranie zasobów i **zapisuje
 * przy wpisie**, czyli tam, gdzie mieszka miejsce.
 *
 * Własnego limitu czasu łańcuch nie pyta z tego samego powodu, dla którego nie
 * pyta o przestrzeń: wpis go unosi (`ClusterProfile::$timeoutSeconds`), ale
 * okno na wartość, którą ustawienia modułu podają przystankami, kosztowałoby
 * więcej niż daje. Wpis zachowuje limit przy zmianie.
 */
final class ClusterFlow
{
    public function __construct(
        private readonly Clusters $clusters,
        private readonly TranslatorPort $translator,
    ) {
    }

    /** Pierwsze ogniwo dodawania: nazwa własna, czyli tożsamość miejsca. */
    public function add(): OverlayInterface
    {
        return $this->namePrompt(null);
    }

    /** Zmiana: ten sam łańcuch, pola wypełnione. */
    public function edit(ClusterProfile $entry): OverlayInterface
    {
        return $this->namePrompt($entry);
    }

    private function namePrompt(?ClusterProfile $entry): PromptOverlay
    {
        return new PromptOverlay(
            $this->key('cluster.prompt.name'),
            [],
            $entry->name ?? '',
            fn (string $name): OverlayOutcome => OverlayOutcome::replace(
                $this->configPrompt($entry, trim($name)),
            ),
            $this->translator,
            $this->key('cluster.prompt.name.field'),
        );
    }

    /**
     * Drugie ogniwo: plik `kubeconfig`.
     *
     * Wartością domyślną nowego wpisu jest **plik domyślny klienta**, bo to
     * najczęstsza odpowiedź; wpis wskazujący plik spoza standardowych ścieżek
     * jest powodem, dla którego książka w ogóle powstała.
     */
    private function configPrompt(?ClusterProfile $entry, string $name): PromptOverlay
    {
        return new PromptOverlay(
            $this->key('cluster.prompt.kubeconfig'),
            [],
            $entry->kubeconfig ?? Clusters::defaultConfigPath(),
            fn (string $path): OverlayOutcome => OverlayOutcome::replace(
                $this->contextPrompt($entry, $name, trim($path)),
            ),
            $this->translator,
            $this->key('cluster.prompt.kubeconfig.field'),
        );
    }

    private function contextPrompt(?ClusterProfile $entry, string $name, string $path): PromptOverlay
    {
        return new PromptOverlay(
            $this->key('cluster.prompt.context'),
            [],
            $entry->context ?? '',
            fn (string $context): OverlayOutcome => $this->save(
                fn (): ClusterProfile => ClusterProfile::of(
                    $name,
                    $path,
                    trim($context),
                    $entry->namespace ?? '',
                    $entry->timeoutSeconds ?? null,
                ),
                $entry,
            ),
            $this->translator,
            $this->key('cluster.prompt.context.field'),
        );
    }

    /**
     * Samowalidacja wpisu i zapis książki.
     *
     * Wartość nie do przyjęcia wraca zdaniem z wyjątku modułu — okno się
     * zamyka, a użytkownik zaczyna od nowa z wiedzą, co odrzucono (wzorem
     * `EnvironmentFlow::save()`).
     *
     * **Zmiana nazwy jest zmianą tożsamości**, więc stary wpis znika: dwa wpisy
     * po jednej edycji byłyby kopią, której nikt nie zamawiał.
     *
     * @param callable(): ClusterProfile $factory
     */
    private function save(callable $factory, ?ClusterProfile $previous): OverlayOutcome
    {
        try {
            $entry = $factory();
        } catch (InvalidClusterNameException $exception) {
            return OverlayOutcome::close(Message::error(
                $this->translator->translate($exception->problemKey(), $exception->problemParameters()),
            ));
        }

        if ($previous !== null && $previous->name !== $entry->name) {
            $this->clusters->remove($previous->name);
        }

        $this->clusters->add($entry);

        return OverlayOutcome::close(Message::info(
            $this->translator->translate($this->key('cluster.saved'), ['name' => $entry->name]),
        ));
    }

    private function key(string $suffix): string
    {
        return 'module.' . KubernetesSettings::ID . '.' . $suffix;
    }
}
