<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Module\Kubernetes\Application\Port\SecretFilePort;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceRef;

/**
 * Sekret rejestru zakładany w klastrze i dopinany do wdrożenia (krok 61,
 * etap 3).
 *
 * **Trzy zdania, na których ta klasa stoi.**
 *
 * *Pierwsze: plik ginie zawsze.* Kryterium ukończenia kroku mówi „także wtedy,
 * gdy `apply` się nie powiódł", i mówi tak, bo plik z poświadczeniem zostawiony
 * po błędzie jest gorszy od braku sekretu — nikt go nie szuka, skoro czynność
 * się nie udała. Kasowanie stoi więc na **wyjściu z etapu**, a nie na ścieżce
 * powodzenia.
 *
 * *Drugie: dopinamy bez sprawdzania, czy sekret już jest.* Zmierzone na żywym
 * klastrze: łata strategiczna powtórzona **nie dubluje** wpisu, bo klucz
 * scalania idzie po nazwie. Kod sprawdzający byłby drugim rachunkiem obok tego,
 * który klaster prowadzi sam.
 *
 * *Trzecie: sekret powstaje przed podmianą obrazu.* Kolejność nie jest
 * porządkiem, tylko warunkiem: wdrożenie przestawione na obraz z rejestru
 * prywatnego, zanim dostanie poświadczenie, próbuje go pobrać i **nie może** —
 * a użytkownik widzi `ImagePullBackOff` zamiast działającej aplikacji.
 */
final class PullSecretWork
{
    private PullSecretStage $stage = PullSecretStage::Idle;

    private string $path = '';

    private string $secretName = '';

    private ?ResourceRef $deployment = null;

    private ?string $problemKey = null;

    /** @var array<string, string|int|float> */
    private array $problemParameters = [];

    public function __construct(
        private readonly ClusterActions $actions,
        private readonly SecretFilePort $files,
    ) {
    }

    /**
     * Zakłada sekret o podanej treści i dopina go do wskazanego wdrożenia.
     *
     * Nazwa sekretu jest **stała dla rejestru** — wyprowadzona z jego nazwy —
     * więc powtórzone wdrożenie nie mnoży sekretów w klastrze. To zapowiedź
     * planu, którą klaster spełnia sam: sprawdzone, że zastosowanie tego samego
     * manifestu drugi raz oddaje `unchanged`.
     */
    public function begin(string $secretName, string $dockerConfigJson, ResourceRef $deployment): void
    {
        $this->reset();

        if ($secretName === '' || $dockerConfigJson === '') {
            $this->fail('module.k8s.deploy.noCredentials');

            return;
        }

        $path = $this->files->write($secretName, self::manifestOf($secretName, $dockerConfigJson));

        if ($path === '') {
            $this->fail('module.k8s.deploy.secretFileFailed');

            return;
        }

        $this->path = $path;
        $this->secretName = $secretName;
        $this->deployment = $deployment;
        $this->stage = PullSecretStage::Applying;
        $this->actions->apply($path);
    }

    /**
     * Posunięcie o takt — wołane przez takt modułu.
     *
     * **Posuwa własny tor czynności**, bo go ma: `ClusterActions` tej pracy jest
     * osobny od tego, którym posługuje się ekran. Wspólny byłby niewykonalny
     * z dwóch powodów naraz — jedna czynność naraz i skutek zabierany raz —
     * a powód stoi rozpisany przy składaniu tej klasy w module.
     */
    public function tick(): void
    {
        if ($this->stage !== PullSecretStage::Applying && $this->stage !== PullSecretStage::Attaching) {
            return;
        }

        $this->actions->advance();
        $outcome = $this->actions->takeOutcome();

        if ($outcome === null) {
            return;
        }

        if (!$outcome->successful) {
            // **Plik ginie także tutaj** — patrz zdanie pierwsze w opisie klasy.
            $this->forgetFile();
            $this->fail($outcome->problemKey ?? 'module.k8s.deploy.secretFailed', $outcome->problemParameters);

            return;
        }

        if ($this->stage === PullSecretStage::Applying) {
            // Sekret stoi w klastrze — plik przestał być potrzebny **w tej
            // chwili**, a nie na końcu łańcucha.
            $this->forgetFile();

            $deployment = $this->deployment;

            if ($deployment === null) {
                $this->stage = PullSecretStage::Done;

                return;
            }

            $this->stage = PullSecretStage::Attaching;
            $this->actions->addPullSecret($deployment, $this->secretName);

            return;
        }

        $this->stage = PullSecretStage::Done;
    }

    public function stage(): PullSecretStage
    {
        return $this->stage;
    }

    public function isWorking(): bool
    {
        return $this->stage === PullSecretStage::Applying || $this->stage === PullSecretStage::Attaching;
    }

    public function problemKey(): ?string
    {
        return $this->problemKey;
    }

    /** @return array<string, string|int|float> */
    public function problemParameters(): array
    {
        return $this->problemParameters;
    }

    public function secretName(): string
    {
        return $this->secretName;
    }

    public function reset(): void
    {
        $this->forgetFile();
        $this->stage = PullSecretStage::Idle;
        $this->secretName = '';
        $this->deployment = null;
        $this->problemKey = null;
        $this->problemParameters = [];
    }

    /**
     * Manifest sekretu — **składany tutaj, bo to pojęcie Kubernetesa**.
     *
     * Treść `.dockerconfigjson` przychodzi gotowa od modułu Dockera (jego
     * pojęcie, jego format); opakowanie w zasób `Secret` należy do tej strony.
     * JSON, a nie YAML — `kubectl apply` przyjmuje oba, a JSON nie ma pułapek
     * wcięcia przy treści z cudzego napisu.
     */
    private static function manifestOf(string $name, string $dockerConfigJson): string
    {
        $manifest = json_encode([
            'apiVersion' => 'v1',
            'kind' => 'Secret',
            'metadata' => ['name' => $name],
            'type' => 'kubernetes.io/dockerconfigjson',
            'data' => ['.dockerconfigjson' => base64_encode($dockerConfigJson)],
        ]);

        return $manifest === false ? '' : $manifest;
    }

    private function forgetFile(): void
    {
        if ($this->path !== '') {
            $this->files->forget($this->path);
            $this->path = '';
        }
    }

    /** @param array<string, string|int|float> $parameters */
    private function fail(string $problemKey, array $parameters = []): void
    {
        $this->stage = PullSecretStage::Failed;
        $this->problemKey = $problemKey;
        $this->problemParameters = $parameters;
    }
}
