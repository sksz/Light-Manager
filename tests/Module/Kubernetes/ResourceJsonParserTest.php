<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Kubernetes;

use LightManager\Module\Kubernetes\Application\ResourceColumn;
use LightManager\Module\Kubernetes\Application\RowTone;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceKind;
use LightManager\Module\Kubernetes\Infrastructure\ResourceJsonParser;
use PHPUnit\Framework\TestCase;

/**
 * Wiersze listy rozczytane z JSON-a klastra (krok 52).
 *
 * Wszystkie wypisy są **zapisane w teście**, a nie brane z klastra — kryterium
 * ukończenia kroku mówi wprost, że żaden test nie rozmawia z klastrem ani nie
 * wywołuje `kubectl`.
 *
 * Najwięcej uwagi dostaje **stan poda**, bo to on rozstrzyga o kolorze wiersza
 * i to on najłatwiej kłamie: pod w `CrashLoopBackOff` ma fazę `Running`, więc
 * wypisanie fazy dałoby listę samych „działających” podów, z których żaden nie
 * działa.
 */
final class ResourceJsonParserTest extends TestCase
{
    public function testPodCarriesReadinessRestartsAndNode(): void
    {
        $rows = ResourceJsonParser::rows(self::podList(), self::pods());

        self::assertCount(1, $rows);
        self::assertSame('web-7d9f8b5c4-x2k9p', $rows[0]->name);
        self::assertSame('1/2', $rows[0]->valueOf(ResourceColumn::Ready));
        self::assertSame('7', $rows[0]->valueOf(ResourceColumn::Restarts), 'restarty sumują się po kontenerach');
        self::assertSame('node-1', $rows[0]->valueOf(ResourceColumn::Node));
    }

    /**
     * **Powód czekania wypiera fazę** — inaczej pod restartujący się w kółko
     * wyglądałby na działający.
     */
    public function testWaitingReasonWinsOverThePhase(): void
    {
        $rows = ResourceJsonParser::rows(self::podList(), self::pods());

        self::assertSame('CrashLoopBackOff', $rows[0]->valueOf(ResourceColumn::Status));
        self::assertSame(RowTone::Broken, $rows[0]->tone);
    }

    public function testPodBeingDeletedSaysSo(): void
    {
        $json = self::listOf([[
            'metadata' => ['name' => 'ginie', 'namespace' => 'default', 'deletionTimestamp' => '2026-08-16T07:00:00Z'],
            'status' => ['phase' => 'Running', 'containerStatuses' => [['ready' => true, 'restartCount' => 0]]],
        ]]);

        $rows = ResourceJsonParser::rows($json, self::pods());

        self::assertSame('Terminating', $rows[0]->valueOf(ResourceColumn::Status));
        self::assertSame(RowTone::Waiting, $rows[0]->tone, 'znikanie jest stanem przejściowym, nie awarią');
    }

    public function testRunningPodIsCalm(): void
    {
        $json = self::listOf([[
            'metadata' => ['name' => 'spokojny', 'namespace' => 'default'],
            'status' => ['phase' => 'Running', 'containerStatuses' => [['ready' => true, 'restartCount' => 0]]],
        ]]);

        $rows = ResourceJsonParser::rows($json, self::pods());

        self::assertSame(RowTone::Normal, $rows[0]->tone);
        self::assertSame('1/1', $rows[0]->valueOf(ResourceColumn::Ready));
    }

    /** Pusta lista jest **odpowiedzią**, a nie awarią — tak samo jak pusty katalog. */
    public function testEmptyListIsAnAnswer(): void
    {
        self::assertSame([], ResourceJsonParser::rows('{"apiVersion":"v1","items":[],"kind":"List"}', self::pods()));
    }

    /**
     * Wypis, który nie jest JSON-em, daje pustą listę.
     *
     * Tak wygląda `kubectl` bez klastra: na wyjściu pustka, a powód na strumieniu
     * błędów — i to strumień błędów niesie zdanie dla użytkownika, nie parser.
     */
    public function testErrorOutputGivesNoRows(): void
    {
        self::assertSame([], ResourceJsonParser::rows('The connection to the server was refused', self::pods()));
        self::assertSame([], ResourceJsonParser::rows('', self::pods()));
    }

    public function testSingleResourceIsReadLikeAOneElementList(): void
    {
        $json = (string) json_encode([
            'kind' => 'Pod',
            'metadata' => ['name' => 'sam', 'namespace' => 'default'],
            'status' => ['phase' => 'Running'],
        ]);

        $rows = ResourceJsonParser::rows($json, self::pods());

        self::assertCount(1, $rows);
        self::assertSame('sam', $rows[0]->name);
    }

    /**
     * Sekret oddaje **rozmiary wartości, nie wartości** — i to jest cała różnica
     * między listą a odsłonięciem (D91 nr 10).
     */
    public function testSecretGivesSizesNotValues(): void
    {
        $secret = [
            'metadata' => ['name' => 'poświadczenia'],
            'type' => 'Opaque',
            'data' => ['hasło' => base64_encode('tajne-hasło'), 'token' => base64_encode('abc')],
        ];

        self::assertSame(
            ['hasło' => strlen('tajne-hasło'), 'token' => 3],
            ResourceJsonParser::secretSizesOf($secret),
        );
        self::assertSame('tajne-hasło', ResourceJsonParser::secretValueOf($secret, 'hasło'));
        self::assertNull(ResourceJsonParser::secretValueOf($secret, 'nie ma takiego'));
    }

    /**
     * Wartość zapisana **nie w base64** wraca taka, jaka przyszła.
     *
     * Sekret założony spoza `kubectl` bywa zapisany inaczej, niż każe schemat,
     * a pokazanie surowego zapisu mówi więcej niż odmowa.
     */
    public function testUndecodableSecretValueComesBackAsItIs(): void
    {
        $secret = ['data' => ['klucz' => 'to nie jest base64!!']];

        self::assertSame('to nie jest base64!!', ResourceJsonParser::secretValueOf($secret, 'klucz'));
    }

    public function testContainerNamesIncludeTheInitialisingOnes(): void
    {
        $pod = ['spec' => [
            'initContainers' => [['name' => 'migracja']],
            'containers' => [['name' => 'serwer'], ['name' => 'wózek-boczny']],
        ]];

        self::assertSame(['migracja', 'serwer', 'wózek-boczny'], ResourceJsonParser::containersOf($pod));
    }

    public function testDeploymentCarriesItsOwnColumns(): void
    {
        $json = self::listOf([[
            'metadata' => ['name' => 'sklep', 'namespace' => 'default'],
            'spec' => ['replicas' => 3],
            'status' => ['readyReplicas' => 2, 'updatedReplicas' => 3, 'availableReplicas' => 2],
        ]]);

        $rows = ResourceJsonParser::rows($json, ResourceKind::of('deployments', 'Deployment', 'apps'));

        self::assertSame('2/3', $rows[0]->valueOf(ResourceColumn::Ready));
        self::assertSame('3', $rows[0]->valueOf(ResourceColumn::UpToDate));
        self::assertSame('2', $rows[0]->valueOf(ResourceColumn::Available));
    }

    /**
     * Rodzaj spoza pakietów — czyli każdy CRD — dostaje kolumny ogólne i **żadna
     * z tego nie robi awarii**.
     *
     * To jest zapisana cena rozstrzygnięcia D91 nr 4 i test pilnuje, żeby była
     * dokładnie taka: mniej kolumn, a nie mniej wierszy.
     */
    public function testUnknownKindStillGivesRows(): void
    {
        $json = self::listOf([[
            'metadata' => ['name' => 'moja-kafka', 'namespace' => 'default', 'creationTimestamp' => '2026-08-01T10:00:00Z'],
            'spec' => ['cokolwiek' => true],
        ]]);

        $rows = ResourceJsonParser::rows($json, ResourceKind::of('kafkas', 'Kafka', 'kafka.strimzi.io'));

        self::assertCount(1, $rows);
        self::assertSame('moja-kafka', $rows[0]->name);
        self::assertSame([], $rows[0]->values, 'nieznany rodzaj nie ma kolumn własnych');
        self::assertNotNull($rows[0]->createdAt, 'wiek ma każdy zasób');
    }

    private static function pods(): ResourceKind
    {
        return ResourceKind::of('pods', 'Pod');
    }

    /** Pod o dwóch kontenerach: jeden gotowy, drugi w pętli restartów. */
    private static function podList(): string
    {
        return self::listOf([[
            'metadata' => [
                'name' => 'web-7d9f8b5c4-x2k9p',
                'namespace' => 'default',
                'creationTimestamp' => '2026-08-15T09:00:00Z',
            ],
            'spec' => ['nodeName' => 'node-1'],
            'status' => [
                'phase' => 'Running',
                'containerStatuses' => [
                    ['ready' => true, 'restartCount' => 0, 'state' => ['running' => []]],
                    [
                        'ready' => false,
                        'restartCount' => 7,
                        'state' => ['waiting' => ['reason' => 'CrashLoopBackOff']],
                    ],
                ],
            ],
        ]]);
    }

    /** @param list<array<string, mixed>> $items */
    private static function listOf(array $items): string
    {
        return (string) json_encode(['apiVersion' => 'v1', 'kind' => 'List', 'items' => $items]);
    }
}
