<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Config;

use LightManager\Infrastructure\Config\StateDocumentService;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\TestCase;

/**
 * Dokument stanu `~/.light-manager/state.json` (krok 59, D103).
 *
 * Test podstawia `HOME` na katalog tymczasowy, jak testy konfiguracji.
 * Sprawdza mechanizm, który do kroku 59 stał skopiowany w trzech usługach
 * modułów: sekcje niezależne, cudze sekcje przeżywają zapis, stary plik modułu
 * czyta się jak sekcja i zostaje nietknięty, a plik jest prywatny.
 */
final class StateDocumentServiceTest extends TestCase
{
    use ResetsSingletons;

    private string $home = '';

    private string|false $previousHome = false;

    protected function setUp(): void
    {
        $this->previousHome = getenv('HOME');
        $this->home = sys_get_temp_dir() . '/lm-state-' . getmypid() . '-' . random_int(1000, 9999);

        mkdir($this->home, 0o700, true);
        putenv('HOME=' . $this->home);

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

        $this->resetSingleton(StateDocumentService::class);
    }

    public function testAMissingDocumentGivesEmptySectionsWithoutAProblem(): void
    {
        $service = StateDocumentService::getInstance();

        self::assertSame([], $service->section('ssh'));
        self::assertFalse($service->hasSection('ssh'));
    }

    public function testWhatWasSavedComesBackAfterARestart(): void
    {
        StateDocumentService::getInstance()->saveSection('docker', ['environments' => [['name' => 'praca']]]);

        $this->resetSingleton(StateDocumentService::class);
        $section = StateDocumentService::getInstance()->section('docker');

        self::assertNotNull($section);
        self::assertSame([['name' => 'praca']], $section['environments'] ?? null);
    }

    /** Zapis jednej sekcji nie ma prawa skasować drugiej — na tym stoi wspólny plik. */
    public function testSavingOneSectionLeavesTheOthersUntouched(): void
    {
        $service = StateDocumentService::getInstance();
        $service->saveSection('ssh', ['hosts' => [['name' => 'biuro']]]);
        $service->saveSection('audio', ['playlist' => []]);

        $this->resetSingleton(StateDocumentService::class);
        $fresh = StateDocumentService::getInstance();

        self::assertSame([['name' => 'biuro']], $fresh->section('ssh')['hosts'] ?? null);
        self::assertSame([], $fresh->section('audio')['playlist'] ?? null);
    }

    /** Sekcja, której ta wersja nie zna, przeżywa cudzy zapis nietknięta. */
    public function testAnUnknownSectionSurvivesASave(): void
    {
        $directory = $this->home . '/.light-manager';
        mkdir($directory, 0o700, true);
        file_put_contents($directory . '/state.json', json_encode([
            'przyszly-modul' => ['klucz' => 'wartość'],
        ]));

        $service = StateDocumentService::getInstance();
        $service->saveSection('ssh', ['hosts' => []]);

        $document = json_decode((string) file_get_contents($service->location()), true);

        self::assertIsArray($document);
        self::assertSame(['klucz' => 'wartość'], $document['przyszly-modul'] ?? null);
    }

    /** Stary plik modułu (`<sekcja>.json`) czyta się jak sekcja i zostaje na dysku. */
    public function testALegacyModuleFileIsReadAsItsSection(): void
    {
        $directory = $this->home . '/.light-manager';
        mkdir($directory, 0o700, true);
        $legacy = json_encode(['hosts' => [['name' => 'biuro', 'host' => 'example.com']]]);
        file_put_contents($directory . '/ssh.json', $legacy);

        $service = StateDocumentService::getInstance();

        self::assertTrue($service->hasSection('ssh'));
        self::assertSame([['name' => 'biuro', 'host' => 'example.com']], $service->section('ssh')['hosts'] ?? null);

        // Pierwszy zapis czegokolwiek utrwala przygarniętą sekcję w dokumencie…
        $service->saveSection('audio', ['playlist' => []]);
        $document = json_decode((string) file_get_contents($service->location()), true);

        self::assertIsArray($document);
        self::assertIsArray($document['ssh'] ?? null);
        // …a stary plik zostaje nietknięty (nikt go już nie czyta).
        self::assertSame($legacy, file_get_contents($directory . '/ssh.json'));
    }

    /** Sekcja w dokumencie wygrywa ze starym plikiem — migracja pada raz. */
    public function testASectionInTheDocumentShadowsTheLegacyFile(): void
    {
        $directory = $this->home . '/.light-manager';
        mkdir($directory, 0o700, true);
        file_put_contents($directory . '/state.json', json_encode(['ssh' => ['hosts' => []]]));
        file_put_contents($directory . '/ssh.json', json_encode(['hosts' => [['name' => 'stary', 'host' => 'x']]]));

        self::assertSame([], StateDocumentService::getInstance()->section('ssh')['hosts'] ?? null);
    }

    /** Dokument bez sensu znaczy „nie wiem, co tu jest" — sekcje wracają `null`em. */
    public function testAnUnreadableDocumentSaysSoInsteadOfPretendingItIsEmpty(): void
    {
        $directory = $this->home . '/.light-manager';
        mkdir($directory, 0o700, true);
        file_put_contents($directory . '/state.json', 'to nie jest JSON');

        self::assertNull(StateDocumentService::getInstance()->section('ssh'));
    }

    /** Stary plik bez sensu też mówi `null` — właściciel złoży z tego zdanie. */
    public function testAnUnreadableLegacyFileSaysSoToo(): void
    {
        $directory = $this->home . '/.light-manager';
        mkdir($directory, 0o700, true);
        file_put_contents($directory . '/ssh.json', 'to nie jest JSON');

        self::assertNull(StateDocumentService::getInstance()->section('ssh'));
        self::assertTrue(StateDocumentService::getInstance()->hasSection('ssh'));
    }

    /** Właściciel czyta i pisze, reszta świata nic — wpisy mówią o maszynach i kluczach. */
    public function testTheDocumentIsPrivateToItsOwner(): void
    {
        $service = StateDocumentService::getInstance();
        $service->saveSection('ssh', ['hosts' => []]);

        self::assertSame(0o600, fileperms($service->location()) & 0o777);
    }
}
