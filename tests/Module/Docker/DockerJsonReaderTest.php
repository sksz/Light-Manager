<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Module\Docker\Domain\ValueObject\ContainerState;
use LightManager\Module\Docker\Infrastructure\DockerJsonReader;
use PHPUnit\Framework\TestCase;

/**
 * Odpowiedzi demona rozczytane na obiekty domeny — **na zapisanym JSON-ie,
 * nigdy przez gniazdo** (krok 51).
 *
 * Próbki są przycięte, ale nie wymyślone: pochodzą z odpowiedzi prawdziwego
 * demona (API 1.47) obejrzanej przy pisaniu czytnika. Stąd biorą się
 * osobliwości, których nikt nie zgadłby z dokumentacji — nazwa listą i z
 * wiodącym ukośnikiem, `Containers: -1` w znaczeniu „nie liczyłem”, etykieta
 * projektu compose w tej samej odpowiedzi.
 */
final class DockerJsonReaderTest extends TestCase
{
    public function testContainerCarriesNameStateAndComposeProject(): void
    {
        $containers = (new DockerJsonReader())->containers(self::containerJson());

        self::assertCount(1, $containers);
        self::assertSame('worker-1', $containers[0]->name, 'wiodący ukośnik nie jest częścią nazwy');
        self::assertSame('828bcdaa7e35', $containers[0]->id->short());
        self::assertSame(ContainerState::Exited, $containers[0]->state);
        self::assertSame('development', $containers[0]->composeProject);
        self::assertTrue($containers[0]->belongsTo('development'));
    }

    /** Port wystawiony na zewnątrz czyta się inaczej niż port wewnętrzny. */
    public function testPortsArePrintedReadyToShow(): void
    {
        $containers = (new DockerJsonReader())->containers(self::containerJson());

        self::assertSame(['8080->80/tcp', '5432/tcp'], $containers[0]->ports);
    }

    /**
     * **Wpis, którego nie da się rozczytać, wypada z listy i nie przerywa jej
     * czytania.** Kontener bez identyfikatora nie jest kontenerem, ale reszta
     * odpowiedzi jest w porządku.
     */
    public function testAMalformedEntryDoesNotTakeDownTheWholeList(): void
    {
        $body = '[{"Names":["/bez-id"]},' . substr(self::containerJson(), 1);

        $containers = (new DockerJsonReader())->containers($body);

        self::assertCount(1, $containers);
        self::assertSame('worker-1', $containers[0]->name);
    }

    public function testImageWithoutTagsIsDangling(): void
    {
        $images = (new DockerJsonReader())->images(
            '[{"Id":"sha256:1a766d518712","RepoTags":[],"Size":1516489489,"Created":1786119429,"Containers":-1}]',
        );

        self::assertCount(1, $images);
        self::assertTrue($images[0]->isDangling());
        self::assertSame('1a766d518712', $images[0]->label(), 'bez nazwy zostaje skrót treści');
    }

    /** `<none>:<none>` jest sposobem, w jaki demon mówi „bez nazwy”, a nie nazwą. */
    public function testNoneTagIsNotAName(): void
    {
        $images = (new DockerJsonReader())->images(
            '[{"Id":"sha256:abcdef123456","RepoTags":["<none>:<none>"],"Size":10,"Created":1,"Containers":2}]',
        );

        self::assertTrue($images[0]->isDangling());
        self::assertSame('abcdef123456', $images[0]->label());
    }

    /** Obraz o dwóch etykietach usuwa się **nazwą**, bo po skrócie demon odmówi. */
    public function testImageWithSeveralTagsIsRemovedByTheFirstName(): void
    {
        $images = (new DockerJsonReader())->images(
            '[{"Id":"sha256:abcdef123456","RepoTags":["app:1.2","app:latest"],"Size":10,"Created":1,"Containers":0}]',
        );

        self::assertSame('app:1.2', $images[0]->label());
        self::assertSame('app:1.2', $images[0]->removalRef()->value);
    }

    /** Zdanie demona o odmowie jest cytatem z cudzego programu — podajemy je w całości. */
    public function testProblemComesFromTheMessageField(): void
    {
        $reader = new DockerJsonReader();

        self::assertSame(
            'conflict: unable to remove repository reference',
            $reader->problem('{"message":"conflict: unable to remove repository reference"}'),
        );
        self::assertNull($reader->problem('nie jest to JSON'));
    }

    /** Odpowiedź, której nie da się rozczytać, daje pustą listę — nigdy wyjątek. */
    public function testGarbageGivesAnEmptyList(): void
    {
        $reader = new DockerJsonReader();

        self::assertSame([], $reader->containers('<html>502 Bad Gateway</html>'));
        self::assertSame([], $reader->images(''));
    }

    private static function containerJson(): string
    {
        return '[{"Id":"828bcdaa7e35d88b1f0e8c9a4b2d6f7e8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d",'
            . '"Names":["/worker-1"],"Image":"perun-worker:latest","State":"exited",'
            . '"Status":"Exited (255) 5 weeks ago","Created":1783750586,'
            . '"Ports":[{"PrivatePort":80,"PublicPort":8080,"Type":"tcp"},'
            . '{"IP":"::","PrivatePort":80,"PublicPort":8080,"Type":"tcp"},'
            . '{"PrivatePort":5432,"Type":"tcp"}],'
            . '"Labels":{"com.docker.compose.project":"development","com.docker.compose.oneoff":"False"}}]';
    }
}
