<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Application\Dto\BackgroundStage;
use LightManager\Module\Kubernetes\Application\Port\KubectlPort;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceKind;
use LightManager\Module\Kubernetes\Infrastructure\ApiResourcesParser;

/**
 * Rodzaje zasobów, które zna **ten** klaster (krok 52).
 *
 * Klasa jest wykonaniem rozstrzygnięcia D91 nr 2 i zarazem powodem, dla którego
 * krok wyszedł inny, niż go zaplanowano: rodzajów nie ma w kodzie, tylko
 * przychodzą z klastra. Zysk widać przy pierwszym operatorze zainstalowanym
 * w klastrze — jego `CustomResourceDefinition` pojawiają się w drzewie **bez ani
 * jednej linii dopisanej do aplikacji**.
 *
 * Katalog jest **pamiętany dla kontekstu**, a nie na zawsze: `api-resources` to
 * pytanie do serwera i kosztuje proces potomny, a rodzaje zmieniają się rzadko —
 * ale zmieniają, więc `Ctrl`+`R` odświeża także katalog.
 *
 * Grupowanie jest tu, a nie w drzewie, bo jest **własnością danych**: nazwa grupy
 * pochodzi z wersji API rodzaju. Drzewo dostaje gotowy podział i wyłącznie go
 * rysuje (reguła 11m: spłaszcza moduł, nie komponent).
 */
final class ApiCatalog
{
    /** @var list<ResourceKind> */
    private array $kinds = [];

    private readonly KubectlWork $work;

    private bool $loaded = false;

    private ?string $problemKey = null;

    /** Pokolenie sesji, dla którego katalog jest prawdziwy. */
    private int $generation = -1;

    public function __construct(
        KubectlPort $kubectl,
        private readonly ClusterSession $session,
    ) {
        $this->work = new KubectlWork($kubectl);
    }

    /** @return list<ResourceKind> */
    public function kinds(): array
    {
        return $this->kinds;
    }

    /**
     * Nazwy grup w kolejności, w jakiej mają stać w drzewie.
     *
     * `core` idzie pierwszy zawsze, reszta alfabetycznie. Nie jest to kosmetyka:
     * pody, usługi i sekrety — czyli wszystko, po co użytkownik tu przychodzi —
     * mieszkają w grupie rdzennej, a alfabet zepchnąłby ją między `certificates`
     * a `discovery`.
     *
     * @return list<string>
     */
    public function groups(): array
    {
        $groups = [];

        foreach ($this->kinds as $kind) {
            $groups[$kind->groupLabel()] = true;
        }

        $names = array_keys($groups);
        sort($names);

        $others = array_values(array_filter($names, static fn (string $name): bool => $name !== 'core'));

        return count($others) === count($names) ? $others : ['core', ...$others];
    }

    /**
     * Rodzaje jednej grupy, alfabetycznie.
     *
     * @return list<ResourceKind>
     */
    public function kindsOf(string $group): array
    {
        $kinds = array_values(array_filter(
            $this->kinds,
            static fn (ResourceKind $kind): bool => $kind->groupLabel() === $group,
        ));

        usort($kinds, static fn (ResourceKind $a, ResourceKind $b): int => strcmp($a->name, $b->name));

        return $kinds;
    }

    public function find(string $address): ?ResourceKind
    {
        foreach ($this->kinds as $kind) {
            if ($kind->address() === $address) {
                return $kind;
            }
        }

        return null;
    }

    public function isLoaded(): bool
    {
        return $this->loaded && $this->generation === $this->session->generation();
    }

    public function isWorking(): bool
    {
        return $this->work->isWorking();
    }

    public function problemKey(): ?string
    {
        return $this->problemKey;
    }

    /**
     * Pyta klaster o katalog — **najwyżej raz na pokolenie sesji**.
     *
     * Warunek jest tu ważniejszy, niż wygląda: pytanie pada z taktu, czyli
     * trzydzieści razy na sekundę, a każde znaczyłoby proces potomny. Bez tego
     * strażnika moduł uruchamiałby `kubectl` w kółko, a wyglądałoby to jak
     * zawieszony klaster.
     */
    public function begin(bool $force = false): void
    {
        if (!$this->session->isTargeted() || $this->work->isWorking()) {
            return;
        }

        if (!$force && $this->isLoaded()) {
            return;
        }

        $this->generation = $this->session->generation();
        $this->work->begin(
            KubectlCall::apiResources($this->session->context()),
            $this->session->timeoutSeconds(),
        );
    }

    public function advance(): void
    {
        $state = $this->work->advance();

        if ($state === null) {
            return;
        }

        if ($state->stage === BackgroundStage::Failed || ($state->exitCode ?? 0) !== 0) {
            $this->problemKey = 'module.' . KubernetesSettings::ID . '.problem.catalog';
            $this->loaded = false;

            return;
        }

        $this->kinds = ApiResourcesParser::kinds($state->output);
        $this->loaded = true;
        $this->problemKey = null;
    }

    public function stop(): void
    {
        $this->work->stop();
    }
}
