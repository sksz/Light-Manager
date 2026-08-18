<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\AddressBook;

use LightManager\Infrastructure\Config\StateDocumentService;
use LightManager\Module\AddressBook\Application\AddressBook;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;
use LightManager\Module\AddressBook\Infrastructure\AddressBookStateService;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\TestCase;

/**
 * Sekcja `address-book` dokumentu stanu wraz z **migracją książki hostów**
 * (krok 60).
 *
 * Migracja jest tu rzeczą najważniejszą i najbardziej ryzykowną w całym kroku:
 * dotyczy **danych, które użytkownik już ma**. Testy pilnują trzech jej
 * obietnic: nic nie ginie, poświadczenia nie wędrują do książki (11w), a stara
 * treść zostaje na dysku nietknięta.
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

    public function testAMissingDocumentIsAnEmptyBookWithoutAProblem(): void
    {
        $loaded = AddressBookStateService::getInstance()->load();

        self::assertTrue($loaded->book->isEmpty());
        self::assertNull($loaded->problemKey);
    }

    public function testWhatWasSavedComesBack(): void
    {
        $service = AddressBookStateService::getInstance();
        $service->save(new AddressBook([
            new AddressEntry('0000000a', 'biuro', 'example.com', ['ssh' => ['port' => 2222, 'user' => 'anna']]),
            new AddressEntry('0000000b', '', '10.0.0.5'),
        ]));

        $this->resetSingleton(AddressBookStateService::class);
        $book = AddressBookStateService::getInstance()->load()->book;

        self::assertSame(['0000000a', '0000000b'], $book->ids());

        $first = $book->find('0000000a');
        $second = $book->find('0000000b');
        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame(2222, $first->value('ssh', 'port'));
        self::assertSame('anna', $first->value('ssh', 'user'));
        self::assertSame('', $second->name, 'wpis bez nazwy przeżywa zapis');
    }

    /** Prawa `0600`: książka mówi, do jakich maszyn użytkownik się łączy. */
    public function testTheDocumentIsReadableOnlyByItsOwner(): void
    {
        $service = AddressBookStateService::getInstance();
        $service->save(new AddressBook([new AddressEntry('0000000a', 'biuro', 'example.com')]));

        self::assertSame('0600', substr(sprintf('%o', fileperms($service->location())), -4));
    }

    /**
     * **Migracja książki hostów**: adres wchodzi do wpisu, port i login do
     * rozdziału `ssh`, a nazwa zostaje nazwą.
     */
    public function testTheHostBookMigratesIntoEntriesWithAnSshChapter(): void
    {
        $this->writeDocument(['ssh' => ['hosts' => [
            ['name' => 'biuro', 'host' => 'example.com', 'port' => 2222, 'user' => 'anna', 'auth' => 'key', 'keyPath' => '/home/anna/.ssh/id_ed25519'],
            ['name' => 'dom', 'host' => '192.168.1.10'],
        ]]]);

        $book = AddressBookStateService::getInstance()->load()->book;

        self::assertSame(2, $book->count());

        $first = $book->findByName('biuro');
        self::assertNotNull($first);
        self::assertSame('example.com', $first->address);
        self::assertSame(2222, $first->value('ssh', 'port'));
        self::assertSame('anna', $first->value('ssh', 'user'));
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $first->id);
    }

    /**
     * **Materiał uwierzytelnienia nie wędruje do książki** (11w) — zostaje
     * w sekcji modułu, który się nim przedstawia.
     */
    public function testAuthenticationMaterialDoesNotMigrateIntoTheBook(): void
    {
        $this->writeDocument(['ssh' => ['hosts' => [
            ['name' => 'biuro', 'host' => 'example.com', 'auth' => 'key', 'keyPath' => '/home/anna/.ssh/id_ed25519'],
        ]]]);

        $entry = AddressBookStateService::getInstance()->load()->book->findByName('biuro');

        self::assertNotNull($entry);
        self::assertNull($entry->value('ssh', 'auth'), 'sposób uwierzytelnienia zostaje u modułu');
        self::assertNull($entry->value('ssh', 'keyPath'), 'ścieżka klucza zostaje u modułu');
    }

    /** Stara książka zostaje na dysku nietknięta — nowa sekcja rośnie obok. */
    public function testTheLegacyHostsKeySurvivesTheFirstSave(): void
    {
        $this->writeDocument(['ssh' => ['hosts' => [['name' => 'biuro', 'host' => 'example.com']]]]);

        $service = AddressBookStateService::getInstance();
        $service->save($service->load()->book);

        $document = json_decode((string) file_get_contents($service->location()), true);

        self::assertIsArray($document);

        $ssh = $document['ssh'] ?? null;
        $book = $document['address-book'] ?? null;
        self::assertIsArray($ssh);
        self::assertIsArray($book);
        self::assertSame(
            [['name' => 'biuro', 'host' => 'example.com']],
            $ssh['hosts'] ?? null,
            'książka hostów zostaje tam, gdzie była',
        );

        $entries = $book['entries'] ?? null;
        self::assertIsArray($entries);
        self::assertCount(1, $entries);
    }

    /** Wpis nie do przyjęcia **wypada, a książka zostaje**. */
    public function testASingleBrokenEntryFallsOutAndTheRestSurvives(): void
    {
        $this->writeDocument(['address-book' => ['entries' => [
            ['id' => '0000000a', 'name' => 'dobry', 'address' => 'example.com'],
            ['id' => 'nie-heks', 'name' => 'zły', 'address' => 'example.com'],
            ['id' => '0000000c', 'name' => 'podstęp', 'address' => '-oProxyCommand=touch /tmp/ups'],
            'to nie jest wpis',
        ]]]);

        $book = AddressBookStateService::getInstance()->load()->book;

        self::assertSame(['0000000a'], $book->ids());
    }

    /** Dokument bez sensu znaczy „nie wiem, co tu jest” — i mówi to zamiast wywracać start. */
    public function testAnUnreadableDocumentGivesAnEmptyBookWithAReason(): void
    {
        $directory = $this->home . '/.light-manager';
        mkdir($directory, 0o700, true);
        file_put_contents($directory . '/state.json', 'to nie jest JSON');

        $loaded = AddressBookStateService::getInstance()->load();

        self::assertTrue($loaded->book->isEmpty());
        self::assertSame('module.address-book.book.unreadable', $loaded->problemKey);
    }

    /** @param array<string, mixed> $document */
    private function writeDocument(array $document): void
    {
        $directory = $this->home . '/.light-manager';
        mkdir($directory, 0o700, true);
        file_put_contents($directory . '/state.json', json_encode($document));

        $this->resetSingleton(AddressBookStateService::class);
        $this->resetSingleton(StateDocumentService::class);
    }
}
