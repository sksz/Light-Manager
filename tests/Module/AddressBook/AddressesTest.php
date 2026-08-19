<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\AddressBook;

use LightManager\Application\Event\EventRegistry;
use LightManager\Module\AddressBook\Application\Addresses;
use LightManager\Module\AddressBook\Application\ChapterField;
use LightManager\Module\AddressBook\Domain\Exception\InvalidAddressEntryException;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;
use LightManager\Module\AddressBook\Domain\ValueObject\FieldKind;
use LightManager\Tests\Support\StubAddressBook;
use PHPUnit\Framework\TestCase;

/**
 * Rejestr wpisów i deklaracji (krok 60).
 *
 * Sprawdza zasadę kroku od strony modelu: **rozdział nie jest przegrodą**.
 * Zapisać wolno w rozdziale niezadeklarowanym, przeczytać wolno cudzy,
 * a rozdział, którego dziś nikt nie deklaruje, nie znika ze spisu — bo jego
 * wartości nadal stoją we wpisach.
 */
final class AddressesTest extends TestCase
{
    private StubAddressBook $storage;

    private Addresses $addresses;

    protected function setUp(): void
    {
        $this->storage = new StubAddressBook();
        $this->addresses = new Addresses($this->storage, new EventRegistry());
    }

    public function testAddedEntryIsSavedAndReportedAsLast(): void
    {
        $entry = $this->addresses->add('biuro');

        self::assertSame('biuro', $entry->name);
        self::assertSame($entry->id, $this->addresses->lastAddedId());
        self::assertSame(1, $this->storage->saveCount);
        self::assertCount(1, $this->storage->saved);
    }

    public function testLastIsEmptyUntilSomethingIsAdded(): void
    {
        self::assertSame('', $this->addresses->lastAddedId());
    }

    public function testValueGoesIntoADeclaredChapterWithItsKind(): void
    {
        $this->addresses->declareField('ssh', new ChapterField('port', 'etykieta', FieldKind::Number, '22'));
        $entry = $this->addresses->add('biuro');

        self::assertTrue($this->addresses->set($entry->id, 'ssh', 'port', '2222'));
        self::assertSame(2222, $this->addresses->find($entry->id)?->value('ssh', 'port'));
    }

    public function testValueOutsideTheKindIsRefused(): void
    {
        $this->addresses->declareField('ssh', new ChapterField('port', 'etykieta', FieldKind::Number));
        $entry = $this->addresses->add('biuro');

        $this->expectException(InvalidAddressEntryException::class);

        $this->addresses->set($entry->id, 'ssh', 'port', 'dwa tysiące');
    }

    /** Brak deklaracji nie zabiera dostępu, więc nie może też zabierać zapisu. */
    public function testUndeclaredChapterAcceptsTextValues(): void
    {
        $entry = $this->addresses->add('biuro');

        self::assertTrue($this->addresses->set($entry->id, 'nieznany', 'cokolwiek', 'wartość'));
        self::assertSame('wartość', $this->addresses->find($entry->id)?->value('nieznany', 'cokolwiek'));
    }

    public function testReferenceMustPointAtAnExistingEntry(): void
    {
        $this->addresses->declareField('docker', new ChapterField('target', 'etykieta', FieldKind::Entry));
        $entry = $this->addresses->add('tunel');

        $this->expectException(InvalidAddressEntryException::class);

        $this->addresses->set($entry->id, 'docker', 'target', 'a1b2c3d4e5f6');
    }

    public function testReferenceToAnExistingEntryPasses(): void
    {
        $this->addresses->declareField('docker', new ChapterField('target', 'etykieta', FieldKind::Entry));
        $host = $this->addresses->add('biuro');
        $tunnel = $this->addresses->add('tunel');

        self::assertTrue($this->addresses->set($tunnel->id, 'docker', 'target', $host->id));
        self::assertSame($host->id, $this->addresses->find($tunnel->id)?->value('docker', 'target'));
    }

    /**
     * **Zmiana nazwy nie rusza odniesień** — to jest powód, dla którego
     * tożsamością wpisu jest identyfikator.
     */
    public function testRenamingTheTargetLeavesTheReferenceIntact(): void
    {
        $this->addresses->declareField('docker', new ChapterField('target', 'etykieta', FieldKind::Entry));
        $host = $this->addresses->add('biuro');
        $tunnel = $this->addresses->add('tunel');
        $this->addresses->set($tunnel->id, 'docker', 'target', $host->id);

        $this->addresses->rename($host->id, 'biuro-nowe');

        self::assertSame($host->id, $this->addresses->find($tunnel->id)?->value('docker', 'target'));
        self::assertSame('biuro-nowe', $this->addresses->find($host->id)?->name);
    }

    public function testChapterPresentOnlyInDataStaysOnTheList(): void
    {
        $storage = new StubAddressBook([
            new AddressEntry('a1b2c3d4e5f6', 'biuro', ['ssh' => ['host' => '10.0.0.5']]),
        ]);
        $addresses = new Addresses($storage, new EventRegistry());

        self::assertSame(['ssh'], $addresses->knownChapters());
        self::assertFalse($addresses->isDeclared('ssh'));
        self::assertFalse($addresses->chapterView('ssh')->declared);
        self::assertSame([], $addresses->chapterView('ssh')->fields);
    }

    public function testDeclaredChaptersComeFirstAndDataOnesFollow(): void
    {
        $storage = new StubAddressBook([
            new AddressEntry('a1b2c3d4e5f6', 'biuro', ['stary' => ['co' => 'to']]),
        ]);
        $addresses = new Addresses($storage, new EventRegistry());
        $addresses->declareChapter('ssh', 'module.ssh.name');

        self::assertSame(['ssh', 'stary'], $addresses->knownChapters());
    }

    public function testClearRemovesFieldThenChapter(): void
    {
        $entry = $this->addresses->add('biuro');
        $this->addresses->set($entry->id, 'ssh', 'host', '10.0.0.5');
        $this->addresses->set($entry->id, 'ssh', 'user', 'michal');

        $this->addresses->clear($entry->id, 'ssh', 'user');
        $afterField = $this->addresses->find($entry->id);
        self::assertNotNull($afterField);
        self::assertSame(['host' => '10.0.0.5'], $afterField->valuesOf('ssh'));

        $this->addresses->clear($entry->id, 'ssh');
        $afterChapter = $this->addresses->find($entry->id);
        self::assertNotNull($afterChapter);
        self::assertSame([], $afterChapter->valuesOf('ssh'));
    }

    public function testForgetSweepsTheChapterFromEveryEntry(): void
    {
        $first = $this->addresses->add('biuro');
        $second = $this->addresses->add('dom');
        $this->addresses->set($first->id, 'ssh', 'host', '10.0.0.5');
        $this->addresses->set($second->id, 'ssh', 'host', '10.0.0.6');
        $this->addresses->set($second->id, 'docker', 'kind', 'tunnel');

        self::assertSame(2, $this->addresses->forget('ssh'));
        self::assertSame([], $this->addresses->find($first->id)?->chapters());
        self::assertSame(['docker'], $this->addresses->find($second->id)?->chapters());
    }

    public function testRemoveTakesTheEntryAndItsChapters(): void
    {
        $entry = $this->addresses->add('biuro');
        $this->addresses->set($entry->id, 'ssh', 'host', '10.0.0.5');

        self::assertTrue($this->addresses->remove($entry->id));
        self::assertNull($this->addresses->find($entry->id));
        self::assertFalse($this->addresses->remove($entry->id));
    }

    public function testUnknownEntryIsAnAnswerNotAFailure(): void
    {
        self::assertFalse($this->addresses->set('a1b2c3d4e5f6', 'ssh', 'host', '10.0.0.5'));
        self::assertFalse($this->addresses->rename('a1b2c3d4e5f6', 'cokolwiek'));
        self::assertFalse($this->addresses->clear('a1b2c3d4e5f6', 'ssh'));
    }

    public function testRevisionsMoveOnlyWhenSomethingChanges(): void
    {
        $before = $this->addresses->revision();
        $chapters = $this->addresses->chapterGeneration();

        $this->addresses->add('biuro');
        self::assertGreaterThan($before, $this->addresses->revision());

        $this->addresses->declareChapter('ssh', 'module.ssh.name');
        self::assertGreaterThan($chapters, $this->addresses->chapterGeneration());
    }
}
