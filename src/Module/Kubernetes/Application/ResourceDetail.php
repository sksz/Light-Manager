<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Application\Dto\BackgroundStage;
use LightManager\Module\Kubernetes\Application\Port\KubectlPort;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceRef;
use LightManager\Module\Kubernetes\Infrastructure\ResourceJsonParser;

/**
 * Jeden zasób w całości — treść prawego panelu (krok 52).
 *
 * Trzyma **dwie postacie tego samego**: rozczytany JSON (z niego powstają
 * zwijane sekcje) i surowy YAML (z niego — widok tekstu). Obie pochodzą z tego
 * samego zasobu, ale z dwóch wywołań, i drugie z nich pada **dopiero na
 * żądanie**: YAML poda to kilkaset wierszy, których nikt nie ogląda, dopóki nie
 * naciśnie `y`, a każde niepotrzebne wywołanie to proces potomny.
 *
 * Sekret ma tu jedną własność ponad resztę zasobów: **wartości są zamaskowane,
 * dopóki użytkownik ich nie odsłoni** (D91 nr 10), a odsłonięcie dotyczy
 * **jednego klucza**, nie całego zasobu. Ta klasa trzyma, który to klucz;
 * maskowaniem zajmuje się warstwa rysująca, bo to ona składa napisy.
 */
final class ResourceDetail
{
    /** @var array<string, mixed>|null rozczytany zasób */
    private ?array $object = null;

    private ?ResourceRef $reference = null;

    private string $yaml = '';

    private readonly KubectlWork $objectWork;

    private readonly KubectlWork $yamlWork;

    /** Klucz sekretu, którego wartość jest odsłonięta — `null`, gdy żaden. */
    private ?string $revealed = null;

    private ?string $problemKey = null;

    public function __construct(
        KubectlPort $kubectl,
        private readonly ClusterSession $session,
    ) {
        $this->objectWork = new KubectlWork($kubectl);
        $this->yamlWork = new KubectlWork($kubectl);
    }

    public function reference(): ?ResourceRef
    {
        return $this->reference;
    }

    /** @return array<string, mixed>|null */
    public function object(): ?array
    {
        return $this->object;
    }

    public function yaml(): string
    {
        return $this->yaml;
    }

    public function hasYaml(): bool
    {
        return $this->yaml !== '';
    }

    public function isWorking(): bool
    {
        return $this->objectWork->isWorking() || $this->yamlWork->isWorking();
    }

    public function problemKey(): ?string
    {
        return $this->problemKey;
    }

    public function revealed(): ?string
    {
        return $this->revealed;
    }

    /**
     * Odsłania wartość jednego klucza sekretu — **jednego naraz**.
     *
     * Odsłonięcie jest chwilowe i ginie razem z przejściem na inny zasób.
     * Odsłanianie wszystkiego naraz byłoby jednym naciśnięciem od zrzutu ekranu
     * z kompletem haseł, a `core.dump` z kroku 38 zapisuje klatkę na dysk.
     */
    public function reveal(?string $key): void
    {
        $this->revealed = $key;
    }

    /**
     * Otwiera zasób — **porzucając poprzedni razem z jego odsłonięciem**.
     */
    public function open(ResourceRef $reference): void
    {
        $this->reference = $reference;
        $this->object = null;
        $this->yaml = '';
        $this->revealed = null;
        $this->problemKey = null;
        $this->objectWork->begin(
            KubectlCall::describe($reference, $this->session->place()),
            $this->session->timeoutSeconds(),
        );
    }

    /** Zamawia surowy YAML — wyłącznie na żądanie użytkownika. */
    public function askForYaml(): void
    {
        $reference = $this->reference;

        if ($reference === null || $this->yamlWork->isWorking()) {
            return;
        }

        $this->yamlWork->begin(
            KubectlCall::yaml($reference, $this->session->place()),
            $this->session->timeoutSeconds(),
        );
    }

    /** Odczytuje zasób ponownie — po zmianie, którą sami zrobiliśmy. */
    public function reload(): void
    {
        $reference = $this->reference;

        if ($reference !== null) {
            $revealed = $this->revealed;
            $this->open($reference);
            $this->revealed = $revealed;
        }
    }

    public function advance(): void
    {
        $this->advanceObject();
        $this->advanceYaml();
    }

    public function stop(): void
    {
        $this->objectWork->stop();
        $this->yamlWork->stop();
    }

    /**
     * Nazwy kontenerów poda — wybór przed otwarciem logów.
     *
     * @return list<string>
     */
    public function containers(): array
    {
        return $this->object === null ? [] : ResourceJsonParser::containersOf($this->object);
    }

    /**
     * Klucze sekretu wraz z rozmiarem wartości.
     *
     * @return array<string, int>
     */
    public function secretSizes(): array
    {
        return $this->object === null ? [] : ResourceJsonParser::secretSizesOf($this->object);
    }

    public function secretValue(string $key): ?string
    {
        return $this->object === null ? null : ResourceJsonParser::secretValueOf($this->object, $key);
    }

    private function advanceObject(): void
    {
        $state = $this->objectWork->advance();

        if ($state === null) {
            return;
        }

        if ($state->stage === BackgroundStage::Failed || ($state->exitCode ?? 0) !== 0) {
            $this->problemKey = 'module.' . KubernetesSettings::ID . '.problem.detail';

            return;
        }

        $this->object = ResourceJsonParser::object($state->output);
        $this->problemKey = null;
    }

    private function advanceYaml(): void
    {
        $state = $this->yamlWork->advance();

        if ($state === null) {
            return;
        }

        if ($state->stage === BackgroundStage::Failed || ($state->exitCode ?? 0) !== 0) {
            $this->problemKey = 'module.' . KubernetesSettings::ID . '.problem.detail';

            return;
        }

        $this->yaml = $state->output;
    }
}
