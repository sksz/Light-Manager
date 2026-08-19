<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\AddressBook;

use LightManager\Infrastructure\Config\StateDocumentService;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;
use LightManager\Module\AddressBook\Infrastructure\AddressBookStateService;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\TestCase;

/**
 * Wpisy książki — sekcja `address-book` dokumentu stanu (krok 60).
 *
 * Test podstawia `HOME` na katalog tymczasowy, tą samą drogą, co testy sekcji
 * `docker`, `ssh` i `k8s`. Sprawdza to, czego z kodu wołającego nie widać:
 * **wpis leży w całości w jednej sekcji** (razem z wartościami wszystkich
 * rozdziałów), plik ruszony ręcznie nie wywraca startu, jeden zepsuty wpis nie
 * zabiera reszty, a klucze, których ta wersja nie zna, przeżywają zapis.
 */
final class AddressBookStateServiceTest extends TestCase
{
    use ResetsSingletons;

    private string $home = '';

    private string|false $previousHome = false;

    protected function setUp(): void
    {
        $this->previousHome = getenv('HOME');
        $this->home = sys_get_temp_dir() . '/lm-book-' . getmypid() . '-' . random_int(1000, 9999);

        mkdir($this->home, 0o700, true);
        putenv('HOME=' . $this->home);

        $this->resetSingleton(AddressBookStateService::class);
        $this->resetSingleton(StateDocumentService::class);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->home . '/.light-manager/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->home . '/.light-manager')) {
            rmdir($this->home . '/.light-manager');
        }

        rmdir($this->home);
        putenv($this->previousHome === false ? 'HOME' : 'HOME=' . $this->previousHome);

        $this->resetSingleton(AddressBookStateService::class);
        $this->resetSingleton(StateDocumentService::class);
    }

    public function testFreshInstallationHasNoEntriesAndNoProblem(): void
    {
        $loaded = AddressBookStateService::getInstance()->load();

        self::assertSame([], $loaded->entries);
        self::assertNull($loaded->problemKey);
    }

    public function testEntryTravelsWithAllItsChapters(): void
    {
        $service = AddressBookStateService::getInstance();
        $service->save([
            new AddressEntry('a1b2c3d4e5f6', 'biuro', [
                'ssh' => ['host' => '10.0.0.5', 'port' => 2222, 'keyPath' => '/home/x/.ssh/id_ed25519'],
                'docker' => ['kind' => 'tunnel'],
            ]),
        ]);

        $this->resetSingleton(AddressBookStateService::class);
        $entries = AddressBookStateService::getInstance()->load()->entries;

        self::assertCount(1, $entries);
        self::assertSame('biuro', $entries[0]->name);
        self::assertSame(2222, $entries[0]->value('ssh', 'port'));
        self::assertSame('/home/x/.ssh/id_ed25519', $entries[0]->value('ssh', 'keyPath'));
        self::assertSame('tunnel', $entries[0]->value('docker', 'kind'));
    }

    public function testEmptyValuesDoNotClutterTheDocument(): void
    {
        AddressBookStateService::getInstance()->save([new AddressEntry('a1b2c3d4e5f6', 'biuro')]);

        $document = file_get_contents($this->home . '/.light-manager/state.json');

        self::assertIsString($document);
        self::assertStringNotContainsString('values', $document);
    }

    public function testBrokenEntryFallsOutAndTheRestStays(): void
    {
        $this->writeDocument([
            'address-book' => [
                'entries' => [
                    ['id' => 'NIE-JEST-IDENTYFIKATOREM', 'name' => 'zepsuty'],
                    ['id' => 'a1b2c3d4e5f6', 'name' => 'dobry'],
                    'wcale nie wiersz',
                ],
            ],
        ]);

        $entries = AddressBookStateService::getInstance()->load()->entries;

        self::assertCount(1, $entries);
        self::assertSame('dobry', $entries[0]->name);
    }

    /** Wartość, której ta wersja nie umie unieść (tablica), wypada — wpis zostaje. */
    public function testUnreadableValueDoesNotTakeTheEntryDown(): void
    {
        $this->writeDocument([
            'address-book' => [
                'entries' => [
                    ['id' => 'a1b2c3d4e5f6', 'name' => 'biuro', 'values' => [
                        'ssh' => ['host' => '10.0.0.5', 'dziwne' => ['zagnieżdżone']],
                    ]],
                ],
            ],
        ]);

        $entries = AddressBookStateService::getInstance()->load()->entries;

        self::assertCount(1, $entries);
        self::assertSame('10.0.0.5', $entries[0]->value('ssh', 'host'));
        self::assertNull($entries[0]->value('ssh', 'dziwne'));
    }

    public function testUnreadableDocumentIsSaidOutLoudInsteadOfLookingEmpty(): void
    {
        mkdir($this->home . '/.light-manager', 0o700, true);
        file_put_contents($this->home . '/.light-manager/state.json', 'to nie jest JSON');

        $loaded = AddressBookStateService::getInstance()->load();

        self::assertSame([], $loaded->entries);
        self::assertSame('module.address-book.book.unreadable', $loaded->problemKey);
    }

    public function testForeignSectionsAndUnknownKeysSurviveTheWrite(): void
    {
        $this->writeDocument([
            'ssh' => ['directories' => ['a1b2c3d4e5f6' => '/srv']],
            'address-book' => ['entries' => [], 'coNowego' => 'z przyszłej wersji'],
        ]);

        AddressBookStateService::getInstance()->save([new AddressEntry('a1b2c3d4e5f6', 'biuro')]);

        $document = json_decode((string) file_get_contents($this->home . '/.light-manager/state.json'), true);

        self::assertIsArray($document);
        self::assertSame(['directories' => ['a1b2c3d4e5f6' => '/srv']], $document['ssh'] ?? null);

        $section = $document['address-book'] ?? null;

        self::assertIsArray($section);
        self::assertSame('z przyszłej wersji', $section['coNowego'] ?? null);
    }

    /** @param array<string, mixed> $document */
    private function writeDocument(array $document): void
    {
        mkdir($this->home . '/.light-manager', 0o700, true);
        file_put_contents(
            $this->home . '/.light-manager/state.json',
            (string) json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }
}
