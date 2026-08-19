<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Ssh;

use LightManager\Infrastructure\Config\StateDocumentService;
use LightManager\Module\Ssh\Infrastructure\SshStateService;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\TestCase;

/**
 * Stan modułu sesji zdalnej — sekcja `ssh` dokumentu stanu (krok 48; od kroku
 * 59 w `~/.light-manager/state.json`, **a od kroku 60 bez książki**).
 *
 * Sekcji zostały dwie rzeczy i obie są tu sprawdzane: **zapamiętany katalog per
 * identyfikator wpisu** oraz **stary spis hostów, czytany do przeniesienia
 * i nigdy niekasowany**. Test podstawia `HOME` na katalog tymczasowy — tą samą
 * drogą, którą robi to test pliku dźwięku.
 */
final class SshStateServiceTest extends TestCase
{
    use ResetsSingletons;

    private string $home = '';

    private string|false $previousHome = false;

    protected function setUp(): void
    {
        $this->previousHome = getenv('HOME');
        $this->home = sys_get_temp_dir() . '/lm-ssh-' . getmypid() . '-' . random_int(1000, 9999);

        mkdir($this->home, 0o700, true);
        putenv('HOME=' . $this->home);

        $this->resetSingleton(SshStateService::class);
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

        $this->resetSingleton(SshStateService::class);
        $this->resetSingleton(StateDocumentService::class);
    }

    public function testFreshSectionHasNothingToMigrate(): void
    {
        $service = SshStateService::getInstance();

        self::assertSame([], $service->legacyHosts());
        self::assertFalse($service->isMigrated());
    }

    /**
     * Stary spis wychodzi stąd **napisami**, a nie profilami: przenosi go do
     * książki komendami ten, kto go tu zostawił (krok 60).
     */
    public function testLegacyHostsComeOutAsPlainRows(): void
    {
        $this->writeSection([
            'hosts' => [
                ['name' => 'biuro', 'host' => 'example.com', 'port' => 2222, 'user' => 'anna', 'auth' => 'key', 'keyPath' => '/klucz'],
                ['name' => 'dom', 'host' => '10.0.0.1'],
            ],
        ]);

        $hosts = SshStateService::getInstance()->legacyHosts();

        self::assertCount(2, $hosts);
        self::assertSame('biuro', $hosts[0]['name']);
        self::assertSame(2222, $hosts[0]['port']);
        self::assertSame('/klucz', $hosts[0]['keyPath']);
        self::assertSame(['name' => 'dom', 'host' => '10.0.0.1'], $hosts[1]);
    }

    public function testBrokenLegacyRowFallsOutAndTheRestSurvives(): void
    {
        $this->writeSection([
            'hosts' => [
                ['name' => 'bez adresu'],
                'wcale nie wiersz',
                ['name' => 'dobry', 'host' => 'example.com'],
            ],
        ]);

        $hosts = SshStateService::getInstance()->legacyHosts();

        self::assertCount(1, $hosts);
        self::assertSame('dobry', $hosts[0]['name']);
    }

    /**
     * **Migracja jest nieniszcząca** (D103): znacznik mówi, że się odbyła,
     * a stary klucz zostaje na dysku nietknięty.
     */
    public function testMarkingTheMigrationLeavesTheOldKeyAlone(): void
    {
        $this->writeSection(['hosts' => [['name' => 'biuro', 'host' => 'example.com']]]);

        $service = SshStateService::getInstance();
        $service->markMigrated();

        $this->resetSingleton(SshStateService::class);
        $fresh = SshStateService::getInstance();

        self::assertTrue($fresh->isMigrated());
        self::assertCount(1, $fresh->legacyHosts(), 'stary spis zostaje na dysku');
    }

    /** Katalog jest kluczowany **identyfikatorem wpisu**, więc przeżywa zmianę nazwy. */
    public function testRememberedDirectoryTravelsUnderTheEntryIdentifier(): void
    {
        $service = SshStateService::getInstance();
        $service->rememberDirectory('a1b2c3d4e5f6', '/srv/www');

        $this->resetSingleton(SshStateService::class);

        self::assertSame('/srv/www', SshStateService::getInstance()->lastDirectory('a1b2c3d4e5f6'));
        self::assertNull(SshStateService::getInstance()->lastDirectory('nieznany'));
    }

    /**
     * Zapis, który niczego nie zmienia, **nie dotyka dysku** — metoda woła się
     * przy każdym przyjęciu listy.
     */
    public function testWritingTheSameDirectoryTwiceDoesNotRewriteTheFile(): void
    {
        $service = SshStateService::getInstance();
        $service->rememberDirectory('a1b2c3d4e5f6', '/srv/www');

        $document = $this->home . '/.light-manager/state.json';
        $before = filemtime($document);
        clearstatcache(true, $document);

        $service->rememberDirectory('a1b2c3d4e5f6', '/srv/www');

        self::assertSame($before, filemtime($document));
    }

    public function testForeignSectionsAndUnknownKeysSurviveTheWrite(): void
    {
        $this->writeDocument([
            'address-book' => ['entries' => [['id' => 'a1b2c3d4e5f6', 'name' => 'biuro']]],
            'ssh' => ['coNowego' => 'z przyszłej wersji'],
        ]);

        SshStateService::getInstance()->rememberDirectory('a1b2c3d4e5f6', '/srv');

        $document = json_decode((string) file_get_contents($this->home . '/.light-manager/state.json'), true);

        self::assertIsArray($document);
        self::assertIsArray($document['address-book'] ?? null);

        $section = $document['ssh'] ?? null;

        self::assertIsArray($section);
        self::assertSame('z przyszłej wersji', $section['coNowego'] ?? null);
    }

    /** @param array<string, mixed> $section */
    private function writeSection(array $section): void
    {
        $this->writeDocument(['ssh' => $section]);
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
