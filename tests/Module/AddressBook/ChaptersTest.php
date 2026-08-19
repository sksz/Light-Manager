<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\AddressBook;

use LightManager\Module\AddressBook\Application\ChapterField;
use LightManager\Module\AddressBook\Application\Chapters;
use LightManager\Module\AddressBook\Domain\ValueObject\FieldKind;
use PHPUnit\Framework\TestCase;

/**
 * Deklaracje rozdziałów (krok 60, D104 nr 2).
 *
 * Cały ten test sprawdza jedno zdanie: **deklaracja jest zapowiedzią użycia,
 * nie zastrzeżeniem**. Wolno ją powtórzyć, wolno ją złożyć dwóm modułom naraz,
 * a sprzeczna nie przestawia tego, co już stoi — bo inaczej dwa moduły
 * używające tego samego pola przerzucałyby się jego rodzajem co takt.
 */
final class ChaptersTest extends TestCase
{
    public function testDeclarationCreatesChapterWithFieldsInOrder(): void
    {
        $chapters = new Chapters();
        $chapters->declareChapter('ssh', 'module.ssh.name');
        $chapters->declareField('ssh', new ChapterField('host', 'module.ssh.field.host'));
        $chapters->declareField('ssh', new ChapterField('port', 'module.ssh.field.port', FieldKind::Number, '22'));

        $chapter = $chapters->find('ssh');

        self::assertNotNull($chapter);
        self::assertSame('module.ssh.name', $chapter->titleKey());
        self::assertSame(['host', 'port'], array_map(
            static fn (ChapterField $field): string => $field->key,
            $chapter->fields(),
        ));
    }

    public function testRepeatingTheSameDeclarationChangesNothing(): void
    {
        $chapters = new Chapters();
        $chapters->declareChapter('ssh', 'module.ssh.name');
        $chapters->declareField('ssh', new ChapterField('host', 'module.ssh.field.host'));
        $revision = $chapters->revision();

        self::assertTrue($chapters->declareChapter('ssh', 'module.ssh.name'));
        self::assertTrue($chapters->declareField('ssh', new ChapterField('host', 'module.ssh.field.host')));
        self::assertSame($revision, $chapters->revision(), 'powtórzenie nie jest zmianą');
    }

    public function testConflictingDeclarationLeavesTheFirstOneStanding(): void
    {
        $chapters = new Chapters();
        $chapters->declareField('ssh', new ChapterField('port', 'module.ssh.field.port', FieldKind::Number));

        $accepted = $chapters->declareField('ssh', new ChapterField('port', 'inny.klucz', FieldKind::Text));

        self::assertFalse($accepted);
        self::assertSame(FieldKind::Number, $chapters->find('ssh')?->field('port')?->kind);
    }

    public function testSecondDeclarerMayUseTheSameChapter(): void
    {
        $chapters = new Chapters();
        $chapters->declareField('places', new ChapterField('address', 'module.ssh.field.host'));
        $chapters->declareField('places', new ChapterField('region', 'module.docker.field.region'));

        self::assertSame(2, $chapters->find('places')?->fieldCount());
    }

    /** Rozdział niezadeklarowany powstaje przy pierwszym polu — bez tytułu, ale z polem. */
    public function testFieldWithoutChapterCreatesItUnnamed(): void
    {
        $chapters = new Chapters();
        $chapters->declareField('k8s', new ChapterField('context', 'module.k8s.field.context'));

        self::assertTrue($chapters->has('k8s'));
        self::assertSame('', $chapters->find('k8s')?->titleKey());
    }

    /** Pierwszy deklarujący nazywa rozdział; drugi nie przemianowuje mu zakładki. */
    public function testTitleOfANamedChapterStands(): void
    {
        $chapters = new Chapters();
        $chapters->declareChapter('ssh', 'module.ssh.name');
        $chapters->declareChapter('ssh', 'zupełnie.inny.tytuł');

        self::assertSame('module.ssh.name', $chapters->find('ssh')?->titleKey());
    }

    public function testNamesOutsideTheShapeAreRefused(): void
    {
        $chapters = new Chapters();

        self::assertFalse($chapters->declareChapter('Ssh', 'module.ssh.name'));
        self::assertFalse($chapters->declareField('ssh', new ChapterField('zły klucz', 'etykieta')));
        self::assertSame([], $chapters->names());
    }
}
