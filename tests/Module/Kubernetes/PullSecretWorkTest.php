<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Kubernetes;

use LightManager\Module\Kubernetes\Application\ClusterActions;
use LightManager\Module\Kubernetes\Application\ClusterSession;
use LightManager\Module\Kubernetes\Application\PullSecretStage;
use LightManager\Module\Kubernetes\Application\PullSecretWork;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceKind;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceRef;
use LightManager\Tests\Support\StubKubectl;
use LightManager\Tests\Support\StubSecretFiles;
use PHPUnit\Framework\TestCase;

/**
 * Sekret rejestru zakładany w klastrze (krok 61, etap 3).
 *
 * Sprawdza **trzy zdania, na których ten kawałek stoi**, i wszystkie trzy bez
 * dotykania klastra: plik z poświadczeniem ginie **zawsze**, łańcuch idzie
 * `apply` → łata, a poświadczenie **nie trafia do wiersza polecenia**.
 *
 * Ostatnie ma osobny test, bo jest granicą bezpieczeństwa, a nie szczegółem —
 * tak żąda plan kroku.
 */
final class PullSecretWorkTest extends TestCase
{
    private const CONFIG = '{"auths":{"rejestr.example.com":{"username":"anna","password":"TAJNE-HASLO"}}}';

    private StubKubectl $kubectl;

    private StubSecretFiles $files;

    private PullSecretWork $work;

    protected function setUp(): void
    {
        $this->kubectl = new StubKubectl();
        $this->files = new StubSecretFiles();
        $this->work = new PullSecretWork(
            new ClusterActions($this->kubectl, new ClusterSession()),
            $this->files,
        );
    }

    /** Łańcuch: manifest na dysk → `apply` → łata dopinająca sekret. */
    public function testTheChainAppliesTheSecretAndThenAttachesIt(): void
    {
        $this->work->begin('lm-registry-proba', self::CONFIG, $this->deployment());

        self::assertSame(PullSecretStage::Applying, $this->work->stage());
        self::assertCount(1, $this->files->written, 'manifest trafił na dysk');

        $this->finishAction();

        self::assertSame(PullSecretStage::Attaching, $this->work->stage());

        $this->finishAction();

        self::assertSame(PullSecretStage::Done, $this->work->stage());
    }

    /**
     * **Poświadczenie nie pojawia się w wierszu polecenia** — zakaz twardy,
     * z kroku 48: `ps` widzi wiersz polecenia.
     *
     * Sprawdzane na **wszystkich** wywołaniach łańcucha, a nie na pierwszym:
     * treść wchodzi przez plik, a `kubectl` dostaje wyłącznie jego ścieżkę.
     */
    public function testTheCredentialNeverReachesTheCommandLine(): void
    {
        $this->work->begin('lm-registry-proba', self::CONFIG, $this->deployment());
        $this->finishAction();
        $this->finishAction();

        $commands = '';

        foreach ($this->kubectl->calls as $call) {
            $commands .= implode(' ', $call->arguments) . "\n";
        }

        self::assertStringNotContainsString('TAJNE-HASLO', $commands);
        self::assertStringNotContainsString('auths', $commands);
        self::assertStringNotContainsString(base64_encode(self::CONFIG), $commands);
    }

    /**
     * **Plik ginie także po niepowodzeniu** — kryterium ukończenia kroku.
     *
     * Plik z poświadczeniem zostawiony po błędzie jest gorszy od braku sekretu:
     * nikt go nie szuka, skoro czynność się nie udała.
     */
    public function testTheFileIsRemovedEvenWhenApplyFails(): void
    {
        // Odpowiedź ustawia się **przed** zamówieniem: atrapa wydaje je
        // w kolejności zapisu, a praca pyta o wynik już przy pierwszym takcie.
        $this->kubectl->willReturn('', 1, 'nie ma takiego zasobu');
        $this->work->begin('lm-registry-proba', self::CONFIG, $this->deployment());

        self::assertFalse($this->files->nothingLeft(), 'plik naprawdę powstał');

        $this->work->tick();

        self::assertSame(PullSecretStage::Failed, $this->work->stage());
        self::assertTrue($this->files->nothingLeft(), 'i mimo niepowodzenia zniknął');
        self::assertCount(1, $this->files->forgotten);
    }

    /** Plik ginie **zaraz po zastosowaniu**, a nie dopiero na końcu łańcucha. */
    public function testTheFileIsRemovedAsSoonAsItIsApplied(): void
    {
        $this->work->begin('lm-registry-proba', self::CONFIG, $this->deployment());
        $this->finishAction();

        self::assertSame(PullSecretStage::Attaching, $this->work->stage(), 'łańcuch jeszcze trwa');
        self::assertTrue($this->files->nothingLeft(), 'a pliku już nie ma');
    }

    /** Poświadczenie puste nie zakłada niczego i nie zostawia pliku. */
    public function testNothingHappensWithoutACredential(): void
    {
        $this->work->begin('lm-registry-proba', '', $this->deployment());

        self::assertSame(PullSecretStage::Failed, $this->work->stage());
        self::assertSame([], $this->files->written);
    }

    /** Plik, którego nie da się założyć, kończy pracę zdaniem, a nie ciszą. */
    public function testARefusedFileEndsWithAReason(): void
    {
        $this->files->refuses = true;

        $this->work->begin('lm-registry-proba', self::CONFIG, $this->deployment());

        self::assertSame(PullSecretStage::Failed, $this->work->stage());
        self::assertSame('module.k8s.deploy.secretFileFailed', $this->work->problemKey());
    }

    private function deployment(): ResourceRef
    {
        return ResourceRef::of(ResourceKind::of('deployments', 'Deployment', 'apps'), null, 'sklep');
    }

    /** Domknięcie bieżącego wywołania `kubectl` powodzeniem. */
    private function finishAction(): void
    {
        $this->kubectl->willReturn('');
        $this->work->tick();
    }

}
