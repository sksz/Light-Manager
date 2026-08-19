<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Infrastructure\Config\StateDocumentService;
use LightManager\Module\Docker\Infrastructure\DockerStateService;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\TestCase;

/**
 * Stan modułu Dockera — sekcja `docker` dokumentu stanu (krok 58; od kroku 59
 * w `~/.light-manager/state.json`, **a od kroku 60 bez książki**).
 *
 * Test podstawia `HOME` na katalog tymczasowy — tą samą drogą, co testy sekcji
 * `ssh` i `audio`. Sprawdza to, czego z kodu wołającego nie widać: plik
 * ruszony ręcznie nie wywraca startu, a **klucze, których ta wersja nie zna,
 * przeżywają zapis** — bo krok 61 dopisze do tej samej sekcji książkę
 * rejestrów.
 */
final class DockerStateServiceTest extends TestCase
{
    use ResetsSingletons;

    private string $home = '';

    private string|false $previousHome = false;

    protected function setUp(): void
    {
        $this->previousHome = getenv('HOME');
        $this->home = sys_get_temp_dir() . '/lm-docker-' . getmypid() . '-' . random_int(1000, 9999);

        mkdir($this->home, 0o700, true);
        putenv('HOME=' . $this->home);

        $this->resetSingleton(DockerStateService::class);
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

        $this->resetSingleton(DockerStateService::class);
        $this->resetSingleton(StateDocumentService::class);
    }

    public function testFreshSectionHasNothingToMigrate(): void
    {
        $service = DockerStateService::getInstance();

        self::assertSame([], $service->legacyEnvironments());
        self::assertFalse($service->isMigrated());
        self::assertSame('', $service->current());
    }

    /** Wskazanie bieżącego przeżywa uruchomienie — to cała pamięć tej sekcji. */
    public function testTheChoiceSurvivesARestart(): void
    {
        DockerStateService::getInstance()->makeCurrent('a1b2c3d4e5f6');

        $this->resetSingleton(DockerStateService::class);

        self::assertSame('a1b2c3d4e5f6', DockerStateService::getInstance()->current());
    }

    /** Zapis, który niczego nie zmienia, **nie dotyka dysku**. */
    public function testWritingTheSameChoiceTwiceDoesNotRewriteTheFile(): void
    {
        $service = DockerStateService::getInstance();
        $service->makeCurrent('a1b2c3d4e5f6');

        $document = $this->home . '/.light-manager/state.json';
        $before = filemtime($document);
        clearstatcache(true, $document);

        $service->makeCurrent('a1b2c3d4e5f6');

        self::assertSame($before, filemtime($document));
    }

    /** Stary spis wychodzi **napisami i liczbami**, bo przenoszą go komendy książki. */
    public function testLegacyEnvironmentsComeOutAsPlainRows(): void
    {
        $this->writeSection([
            'environments' => [
                ['name' => 'serwer', 'kind' => 'tunnel', 'target' => 'biuro', 'port' => 2222, 'socket' => '/run/d.sock'],
                ['name' => 'chmura', 'kind' => 'tcp', 'target' => 'daemon.example.com', 'port' => 2376, 'cert' => '/c.pem'],
                ['name' => 'bez rodzaju'],
            ],
            'currentEnvironment' => 'serwer',
        ]);

        $service = DockerStateService::getInstance();
        $legacy = $service->legacyEnvironments();

        self::assertCount(2, $legacy, 'wiersz bez rodzaju wypada, reszta zostaje');
        self::assertSame('serwer', $legacy[0]['name']);
        self::assertSame('biuro', $legacy[0]['target']);
        self::assertSame(2222, $legacy[0]['port']);
        self::assertSame('/c.pem', $legacy[1]['cert']);
        self::assertSame('serwer', $service->current());
    }

    /** **Migracja jest nieniszcząca**: znacznik wchodzi, stary spis zostaje. */
    public function testMarkingTheMigrationLeavesTheOldKeyAlone(): void
    {
        $this->writeSection(['environments' => [['name' => 'serwer', 'kind' => 'local']]]);

        DockerStateService::getInstance()->markMigrated();
        $this->resetSingleton(DockerStateService::class);
        $fresh = DockerStateService::getInstance();

        self::assertTrue($fresh->isMigrated());
        self::assertCount(1, $fresh->legacyEnvironments(), 'stary spis zostaje na dysku');
    }

    public function testForeignSectionsAndUnknownKeysSurviveTheWrite(): void
    {
        $this->writeDocument([
            'address-book' => ['entries' => [['id' => 'a1b2c3d4e5f6', 'name' => 'serwer']]],
            'docker' => ['coNowego' => 'z przyszłej wersji'],
        ]);

        DockerStateService::getInstance()->makeCurrent('a1b2c3d4e5f6');

        $document = json_decode((string) file_get_contents($this->home . '/.light-manager/state.json'), true);

        self::assertIsArray($document);
        self::assertIsArray($document['address-book'] ?? null);

        $section = $document['docker'] ?? null;

        self::assertIsArray($section);
        self::assertSame('z przyszłej wersji', $section['coNowego'] ?? null);
    }

    /** Plik prywatny — prawa `0600`, jak każdy zapis przez `StateFile`. */
    public function testTheFileIsReadableOnlyByItsOwner(): void
    {
        DockerStateService::getInstance()->makeCurrent('a1b2c3d4e5f6');

        $mode = fileperms($this->home . '/.light-manager/state.json') & 0o777;

        self::assertSame(0o600, $mode);
    }

    /** @param array<string, mixed> $section */
    private function writeSection(array $section): void
    {
        $this->writeDocument(['docker' => $section]);
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
