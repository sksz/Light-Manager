<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Kubernetes;

use LightManager\Module\Kubernetes\Application\ClusterBook;
use LightManager\Module\Kubernetes\Domain\Exception\InvalidClusterNameException;
use LightManager\Module\Kubernetes\Domain\ValueObject\ClusterPlace;
use LightManager\Module\Kubernetes\Domain\ValueObject\ClusterProfile;
use LightManager\Module\Kubernetes\Domain\ValueObject\ContextName;
use PHPUnit\Framework\TestCase;

/**
 * Wpis książki klastrów i miejsce, które wskazuje (krok 59).
 *
 * Dwa zdania, których pilnuje ten test i których z kodu wołającego nie widać:
 * **wpis, z którego nie da się złożyć polecenia, nie ma prawa powstać**
 * (reguła 11r — wartość zaczynająca się od `-` jest opcją, nie argumentem)
 * i **istnienia pliku nie sprawdza samowalidacja**, bo plik na dysku sieciowym
 * bywa chwilowo nieobecny, a wpis nie ma przez to przestać istnieć.
 */
final class ClusterProfileTest extends TestCase
{
    public function testAnEntryCarriesBothCoordinatesOfThePlace(): void
    {
        $entry = ClusterProfile::of('praca', '/home/anna/.kube/config', 'ca-dev', 'produkcja');
        $place = $entry->place();

        self::assertSame('praca', $entry->name);
        self::assertSame('/home/anna/.kube/config', $place->kubeconfig);
        self::assertSame('ca-dev', $place->context?->value);
        self::assertSame('produkcja', $entry->namespace);
        self::assertNull($entry->timeoutSeconds, 'brak limitu własnego znaczy „ten z ustawień"');
    }

    /** Plik, którego nie ma, **przechodzi** — o jego braku mówi stan, nie wpis. */
    public function testAPathThatDoesNotExistIsStillAValidEntry(): void
    {
        $entry = ClusterProfile::of('zdalny', '/mnt/nfs/klastry/produkcja.yaml', 'prod');

        self::assertSame('/mnt/nfs/klastry/produkcja.yaml', $entry->kubeconfig);
    }

    /** Ścieżka zaczynająca się od `-` byłaby opcją `kubectl`, a nie argumentem. */
    public function testAnOptionLikePathIsRejected(): void
    {
        $this->expectException(InvalidClusterNameException::class);

        ClusterProfile::of('wpis', '-oProxyCommand=touch /tmp/ups', 'ca-dev');
    }

    public function testAnOptionLikeNameIsRejected(): void
    {
        $this->expectException(InvalidClusterNameException::class);

        ClusterProfile::of('-zly', '/home/anna/.kube/config', 'ca-dev');
    }

    public function testAnEmptyNameIsRejected(): void
    {
        $this->expectException(InvalidClusterNameException::class);

        ClusterProfile::of('   ', '/home/anna/.kube/config', 'ca-dev');
    }

    /** Nazwę wpisuje człowiek, więc spacja w środku jest legalna — nie jedzie wierszem polecenia. */
    public function testASpaceInTheOwnNameIsFine(): void
    {
        self::assertSame(
            'klaster u klienta',
            ClusterProfile::of('klaster u klienta', '/home/anna/.kube/config', 'ca-dev')->name,
        );
    }

    /** Przestrzeń nazw jest etykietą DNS-1123 — regułę dyktuje serwer, nie człowiek. */
    public function testAMalformedNamespaceIsRejected(): void
    {
        $this->expectException(InvalidClusterNameException::class);

        ClusterProfile::of('praca', '/home/anna/.kube/config', 'ca-dev', 'Produkcja_1');
    }

    /**
     * **Dwa miejsca o kontekstach tej samej nazwy w dwóch plikach są różne** —
     * to jest ten warunek, który kod sprzed kroku 59 łamał po cichu.
     */
    public function testTwoPlacesWithTheSameContextNameInDifferentFilesDiffer(): void
    {
        $first = ClusterPlace::of('/home/anna/.kube/config', ContextName::of('default'));
        $second = ClusterPlace::of('/home/anna/klienci/klient.yaml', ContextName::of('default'));

        self::assertFalse($first->equals($second));
        self::assertNotSame($first->fingerprint(), $second->fingerprint());
    }

    /** Tożsamością wpisu jest **nazwa własna**, a nie współrzędne miejsca. */
    public function testTheOwnNameIsTheIdentity(): void
    {
        $first = ClusterProfile::of('praca', '/home/anna/.kube/config', 'ca-dev');
        $second = ClusterProfile::of('praca', '/inny/plik.yaml', 'minikube');

        self::assertTrue($first->equals($second));
    }

    /** Książka zachowuje kolejność dopisywania, a nazwa zajęta **zastępuje** wpis w miejscu. */
    public function testTheBookKeepsOrderAndReplacesByName(): void
    {
        $book = new ClusterBook();
        $book->add(ClusterProfile::of('praca', '/a.yaml', 'ca-dev'));
        $book->add(ClusterProfile::of('dom', '/b.yaml', 'minikube'));
        $book->add(ClusterProfile::of('praca', '/c.yaml', 'nowy'));

        self::assertSame(['praca', 'dom'], array_map(
            static fn (ClusterProfile $entry): string => $entry->name,
            $book->all(),
        ));
        self::assertSame('nowy', $book->find('praca')?->context);
    }

    /** Skasowanie wpisu bieżącego zostawia wybór pusty, a nie wskazujący donikąd. */
    public function testRemovingTheCurrentEntryClearsTheChoice(): void
    {
        $book = new ClusterBook();
        $book->add(ClusterProfile::of('praca', '/a.yaml', 'ca-dev'));
        $book->makeCurrent('praca');

        self::assertTrue($book->remove('praca'));
        self::assertSame('', $book->current());
    }
}
