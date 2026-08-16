<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Kubernetes;

use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceKind;
use LightManager\Module\Kubernetes\Infrastructure\ApiResourcesParser;
use PHPUnit\Framework\TestCase;

/**
 * Katalog rodzajów rozczytany z wypisu `kubectl api-resources -o wide`
 * (krok 52).
 *
 * **To jest jedyne miejsce w module czytające tekst**, więc jedyne, w którym
 * może się wydarzyć pomyłka rozczytania — i dlatego ma najgęstszy zestaw
 * sprawdzeń. Wypis pochodzi z klienta 1.25.2, tego samego, który stoi na
 * maszynie projektu.
 *
 * Najważniejszy jest tu **pierwszy test**: kolumna `SHORTNAMES` bywa pusta,
 * a wtedy podział wiersza po dwóch spacjach daje o jedno pole mniej i przesuwa
 * wszystkie pozostałe. Skutek byłby cichy — `namespaced` czytane z kolumny
 * `APIVERSION` znaczy odwróconą odpowiedź na pytanie „czy ten zasób mieszka
 * w przestrzeni nazw”, czyli listę pytaną z niewłaściwym argumentem.
 */
final class ApiResourcesParserTest extends TestCase
{
    /** Prawdziwy wypis: pierwszy wiersz bez skrótów, drugi ze skrótem. */
    private const OUTPUT = <<<'TXT'
        bindings                            v1     true    Binding   [create]
        podtemplates                        v1     true    PodTemplate [create delete get list patch update watch]
        pods                             po v1     true    Pod       [create delete get list patch update watch] all
        deployments                    deploy apps/v1 true Deployment [create delete get list patch update watch] all
        nodes                            no v1     false   Node      [create delete get list patch update watch]
        TXT;

    /**
     * Wiersz bez skrótów rozczytuje się **w każdej kolumnie tak samo**, jak
     * wiersz ze skrótami.
     *
     * `podtemplates` skrótów nie ma, a `pods` ma — obydwa mają wypaść z tego
     * samego wzorca z tymi samymi polami. Podział po dwóch spacjach dałby tu
     * przesunięcie: nazwą pojedynczą stałoby się `true`, a namespace'owością —
     * `v1`.
     */
    public function testEmptyShortNamesColumnDoesNotShiftTheRest(): void
    {
        $templates = self::kindNamed(ApiResourcesParser::kinds(self::OUTPUT), 'podtemplates');

        self::assertNotNull($templates, 'rodzaj bez skrótów wypadł z katalogu');
        self::assertSame('PodTemplate', $templates->kind, 'nazwa pojedyncza przyszła z niewłaściwej kolumny');
        self::assertTrue($templates->namespaced, 'przynależność do przestrzeni przyszła z niewłaściwej kolumny');
        self::assertSame('', $templates->group);
        self::assertSame([], $templates->shortNames);
    }

    /**
     * Rodzaj, którego **nie da się wypisać**, nie wchodzi do katalogu.
     *
     * `bindings` przyjmuje wyłącznie `create`, więc gałąź drzewa dla niego
     * kończyłaby się błędem przy każdym rozwinięciu. Gałąź, której nie ma, jest
     * od takiej lepsza.
     */
    public function testKindsWithoutListStayOut(): void
    {
        self::assertNull(self::kindNamed(ApiResourcesParser::kinds(self::OUTPUT), 'bindings'));
    }

    public function testNamespacedFlagComesFromItsOwnColumn(): void
    {
        $kinds = ApiResourcesParser::kinds(self::OUTPUT);

        self::assertTrue(self::kindNamed($kinds, 'pods')?->namespaced);
        self::assertFalse(self::kindNamed($kinds, 'nodes')?->namespaced, 'węzły nie mieszkają w przestrzeni nazw');
    }

    public function testGroupComesFromTheApiVersion(): void
    {
        $kinds = ApiResourcesParser::kinds(self::OUTPUT);

        $deployments = self::kindNamed($kinds, 'deployments');

        self::assertSame('', self::kindNamed($kinds, 'pods')?->group, 'v1 znaczy grupę rdzenną');
        self::assertNotNull($deployments);
        self::assertSame('apps', $deployments->group);
        self::assertSame('deployments.apps', $deployments->address());
    }

    public function testShortNamesSurviveForTheKindsThatHaveThem(): void
    {
        $kinds = ApiResourcesParser::kinds(self::OUTPUT);

        self::assertSame(['po'], self::kindNamed($kinds, 'pods')?->shortNames);
        self::assertSame(['deploy'], self::kindNamed($kinds, 'deployments')?->shortNames);
    }

    /**
     * Zasoby własne wchodzą **bez jednej linii dopisanej do aplikacji** — to jest
     * cała obietnica rozstrzygnięcia D91 nr 2.
     */
    public function testCustomResourcesEnterOnTheirOwn(): void
    {
        $output = 'kafkas  k kafka.strimzi.io/v1beta2 true Kafka [create delete get list patch update watch]';
        $kinds = ApiResourcesParser::kinds($output);

        self::assertCount(1, $kinds);
        self::assertSame('kafkas.kafka.strimzi.io', $kinds[0]->address());
        self::assertSame('kafka.strimzi.io', $kinds[0]->groupLabel());
    }

    public function testHeaderAndBlankLinesAreIgnored(): void
    {
        $output = "NAME  SHORTNAMES  APIVERSION  NAMESPACED  KIND  VERBS\n\n"
            . 'pods  po  v1  true  Pod  [list]';

        self::assertCount(1, ApiResourcesParser::kinds($output));
    }

    /** Wypis, którego nie da się rozczytać, daje pusty katalog — a nie wyjątek. */
    public function testUnreadableOutputGivesAnEmptyCatalogue(): void
    {
        self::assertSame([], ApiResourcesParser::kinds('error: You must be logged in to the server'));
        self::assertSame([], ApiResourcesParser::kinds(''));
    }

    /** @param list<ResourceKind> $kinds */
    private static function kindNamed(array $kinds, string $name): ?ResourceKind
    {
        foreach ($kinds as $kind) {
            if ($kind->name === $name) {
                return $kind;
            }
        }

        return null;
    }
}
