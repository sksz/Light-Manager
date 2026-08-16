<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Kubernetes;

use LightManager\Module\Kubernetes\Domain\Exception\InvalidClusterNameException;
use LightManager\Module\Kubernetes\Domain\ValueObject\ContextName;
use LightManager\Module\Kubernetes\Domain\ValueObject\NamespaceName;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceKind;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceRef;
use PHPUnit\Framework\TestCase;

/**
 * Nazwy, które trafiają do wiersza polecenia (krok 52).
 *
 * **Najważniejszy jest tu wiodący myślnik** — reguła 11r z kroku 48, zapisana po
 * tym, jak okazało się, że `escapeshellarg()` przed nim nie chroni: cytowanie
 * broni przed powłoką, a nie przed programem, który sam rozbiera swoje argumenty.
 * Przestrzeń nazwana `--all-namespaces` przeszłaby przez cytowanie nietknięta
 * i **zmieniła znaczenie polecenia**.
 *
 * Druga rzecz warta testu to **różnica szerokości**: nazwę kontekstu wpisuje
 * człowiek albo narzędzie, które go zakładało, więc bywa dziwna i ma prawo taka
 * być; nazwę przestrzeni dyktuje Kubernetes i jest etykietą DNS-1123.
 */
final class ClusterNamesTest extends TestCase
{
    public function testContextStartingWithADashIsRefused(): void
    {
        $this->expectException(InvalidClusterNameException::class);

        ContextName::of('--kubeconfig=/etc/passwd');
    }

    public function testNamespaceStartingWithADashIsRefused(): void
    {
        $this->expectException(InvalidClusterNameException::class);

        NamespaceName::of('--all-namespaces');
    }

    public function testKindStartingWithADashIsRefused(): void
    {
        $this->expectException(InvalidClusterNameException::class);

        ResourceKind::of('-o', 'Nic');
    }

    public function testResourceNameStartingWithADashIsRefused(): void
    {
        $this->expectException(InvalidClusterNameException::class);

        ResourceRef::of(ResourceKind::of('pods', 'Pod'), NamespaceName::fallback(), '--force');
    }

    /** Powód „to wygląda na opcję” jest **osobny** — dla użytkownika to inna wiadomość. */
    public function testOptionLikeNameSaysWhyItLooksWrong(): void
    {
        try {
            NamespaceName::of('-n');
            self::fail('nazwa wyglądająca na opcję miała zostać odrzucona');
        } catch (InvalidClusterNameException $exception) {
            self::assertSame('module.k8s.name.optionLike', $exception->problemKey());
        }
    }

    /**
     * Nazwy kontekstów bywają takie i **mają prawo być** — pochodzą z narzędzi,
     * które zakładały klaster.
     */
    public function testRealWorldContextNamesPass(): void
    {
        self::assertSame('gke_projekt_europe-west1_klaster', ContextName::of('gke_projekt_europe-west1_klaster')->value);
        self::assertSame('uzytkownik@klaster.eksctl.io', ContextName::of('uzytkownik@klaster.eksctl.io')->value);
        self::assertSame('kind-lokalny', ContextName::of('kind-lokalny')->value);
    }

    public function testContextWithWhitespaceIsRefused(): void
    {
        $this->expectException(InvalidClusterNameException::class);

        ContextName::of("ca-dev\nrm -rf /");
    }

    /** Przestrzeń nazw jest etykietą DNS-1123 — wielkie litery i ogonki odpadają. */
    public function testNamespaceFollowsTheDnsLabelRule(): void
    {
        self::assertSame('produkcja', NamespaceName::of('produkcja')->value);
        self::assertSame('moja-przestrzen-2', NamespaceName::of('moja-przestrzen-2')->value);

        $this->expectException(InvalidClusterNameException::class);
        NamespaceName::of('Produkcja');
    }

    public function testNamespaceLongerThanTheLimitIsRefused(): void
    {
        $this->expectException(InvalidClusterNameException::class);

        NamespaceName::of(str_repeat('a', NamespaceName::MAXIMUM_LENGTH + 1));
    }

    /** Nazwy podów wyglądają właśnie tak i muszą przechodzić. */
    public function testGeneratedPodNamesPass(): void
    {
        $reference = ResourceRef::of(ResourceKind::of('pods', 'Pod'), NamespaceName::fallback(), 'web-7d9f8b5c4-x2k9p');

        self::assertSame('pods/web-7d9f8b5c4-x2k9p', $reference->address());
    }

    /**
     * **Zasób klastrowy gubi przestrzeń nazw** — rozstrzyga o tym rodzaj, bo
     * tylko on wie, czy zasób w niej mieszka.
     */
    public function testClusterScopedResourceDropsTheNamespace(): void
    {
        $nodes = ResourceKind::of('nodes', 'Node', ResourceKind::CORE_GROUP, namespaced: false);
        $reference = ResourceRef::of($nodes, NamespaceName::fallback(), 'node-1');

        self::assertNull($reference->namespace);
    }

    /** Rodzaj bez grupy adresuje się samą nazwą, z grupą — nazwą z kropką. */
    public function testKindAddressCarriesTheGroupOnlyWhenThereIsOne(): void
    {
        self::assertSame('pods', ResourceKind::of('pods', 'Pod')->address());
        self::assertSame('deployments.apps', ResourceKind::of('deployments', 'Deployment', 'apps')->address());
    }
}
