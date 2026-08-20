<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Application\Dto\BackgroundStage;
use LightManager\Module\Kubernetes\Application\Port\KubectlPort;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceRef;
use LightManager\Module\Kubernetes\Infrastructure\SecretPatch;

/**
 * Czynności zmieniające klaster: zastosowanie pliku, usunięcie, zmiana sekretu
 * (krok 52).
 *
 * **Jedna czynność naraz** — i to jest rozstrzygnięcie z tego samego powodu, co
 * przy liście: użytkownik robi jedną rzecz w jednej chwili, a dwie czynności
 * zmieniające ten sam klaster jednocześnie to najprostsza droga do stanu, którego
 * nikt nie zamawiał. Port prowadzi kilka prac, więc czynność **nie ubija** listy
 * ani logów; ubija wyłącznie poprzednią czynność, na którą nikt już nie czeka.
 *
 * Wynik zabiera się **raz** (`takeOutcome()`), wzorem `ContainerList` z modułu
 * Dockera: inaczej ekran zgłaszałby to samo zdanie co klatkę.
 */
final class ClusterActions
{
    private readonly KubectlWork $work;

    private ?ClusterAction $action = null;

    private string $subject = '';

    private ?ActionOutcome $outcome = null;

    public function __construct(
        KubectlPort $kubectl,
        private readonly ClusterSession $session,
    ) {
        $this->work = new KubectlWork($kubectl);
    }

    public function isWorking(): bool
    {
        return $this->work->isWorking();
    }

    /** Czynność, na którą czekamy — ekran mówi o niej w nagłówku. */
    public function pending(): ?ClusterAction
    {
        return $this->action;
    }

    /** Wynik ostatniej czynności — **oddawany raz**. */
    public function takeOutcome(): ?ActionOutcome
    {
        $outcome = $this->outcome;
        $this->outcome = null;

        return $outcome;
    }

    /**
     * Zastosowanie pliku — **ścieżką, nigdy wejściem standardowym**.
     *
     * Ścieżki tutaj nie sprawdzamy: `kubectl` powie o niej lepiej niż my
     * (nie ma pliku, nie jest to YAML, jest to YAML z błędem w trzeciej linii),
     * a jego zdanie idzie prosto do użytkownika.
     */
    public function apply(string $path): void
    {
        $this->begin(
            ClusterAction::Apply,
            $path,
            KubectlCall::apply($path, $this->session->namespace(), $this->session->place()),
        );
    }

    public function delete(ResourceRef $reference): void
    {
        $this->begin(
            ClusterAction::Delete,
            $reference->address(),
            KubectlCall::delete($reference, $this->session->place()),
        );
    }

    /**
     * Zmiana wartości pod kluczem sekretu — albo jej skasowanie.
     *
     * `null` w miejscu wartości znaczy **skasuj klucz** i jest to reguła samego
     * `kubectl patch --type=merge`, nie nasza umowa. Wartość podaje się już
     * zakodowaną, bo kodowanie zależy od tego, co użytkownik wpisał (base64 czy
     * tekst surowy), a ta klasa o oknach nie wie.
     */
    public function patchSecret(ResourceRef $reference, string $key, ?string $base64Value): void
    {
        $this->begin(
            ClusterAction::PatchSecret,
            $key,
            KubectlCall::patch(
                $reference,
                SecretPatch::forKey($key, $base64Value),
                $this->session->place(),
            ),
        );
    }

    /**
     * Dopięcie sekretu rejestru do wdrożenia (krok 61, etap 3).
     *
     * Łata **strategiczna** — dlaczego akurat ta i co zmierzono, stoi
     * w `KubectlCall::addPullSecret()`.
     */
    public function addPullSecret(ResourceRef $reference, string $secret): void
    {
        $this->begin(
            ClusterAction::AddPullSecret,
            $reference->name . '/' . $secret,
            KubectlCall::addPullSecret($reference, $secret, $this->session->place()),
        );
    }

    /**
     * Podmiana obrazu kontenera we wdrożeniu — **ostatni etap
     * `k8s.deploy-image`** (krok 54).
     *
     * Wdrożenie i kontener przychodzą osobno, bo `kubectl set image` wskazuje
     * kontener nazwą — i to jest powód, dla którego kwerenda `k8s.deployments`
     * oddaje wiersz na kontener, a nie na wdrożenie.
     */
    public function setImage(ResourceRef $reference, string $container, string $image): void
    {
        $this->begin(
            ClusterAction::SetImage,
            $reference->name . '/' . $container,
            KubectlCall::setImage($reference, $container, $image, $this->session->place()),
        );
    }

    public function advance(): void
    {
        $state = $this->work->advance();
        $action = $this->action;

        if ($state === null || $action === null) {
            return;
        }

        $this->action = null;

        if ($state->stage === BackgroundStage::Failed) {
            $this->outcome = ActionOutcome::failure(
                $action,
                $this->subject,
                $state->problemKey ?? 'module.' . KubernetesSettings::ID . '.problem.action',
                $state->problemParameters,
            );

            return;
        }

        if (($state->exitCode ?? 0) !== 0) {
            $this->outcome = ActionOutcome::failure(
                $action,
                $this->subject,
                'module.' . KubernetesSettings::ID . '.problem.rejected',
                ['reason' => self::reasonOf($state->errorOutput)],
            );

            return;
        }

        $this->outcome = ActionOutcome::success($action, $this->subject);
    }

    public function stop(): void
    {
        $this->work->stop();
        $this->action = null;
    }

    private function begin(ClusterAction $action, string $subject, KubectlCall $call): void
    {
        $this->action = $action;
        $this->subject = $subject;
        $this->work->begin($call, $this->session->timeoutSeconds());
    }

    private static function reasonOf(string $errorOutput): string
    {
        $first = strtok(trim($errorOutput), "\n");

        return $first === false ? '' : $first;
    }
}
