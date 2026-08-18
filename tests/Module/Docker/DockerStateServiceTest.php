<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Infrastructure\Config\StateDocumentService;
use LightManager\Module\Docker\Application\EnvironmentBook;
use LightManager\Module\Docker\Domain\ValueObject\DockerEnvironment;
use LightManager\Module\Docker\Domain\ValueObject\EnvironmentKind;
use LightManager\Module\Docker\Infrastructure\DockerStateService;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\TestCase;

/**
 * Stan modułu Dockera — sekcja `docker` dokumentu stanu (krok 58; od kroku 59
 * w `~/.light-manager/state.json`, D103).
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

    public function testAMissingFileIsAFreshStartWithoutAProblem(): void
    {
        $loaded = DockerStateService::getInstance()->load();

        self::assertSame([], $loaded->book->all());
        self::assertSame(EnvironmentBook::DEFAULT_NAME, $loaded->book->current());
        self::assertNull($loaded->problemKey);
    }

    public function testWhatWasSavedComesBackIncludingTheChoice(): void
    {
        $book = new EnvironmentBook([
            DockerEnvironment::sshTunnel('serwer', 'anna@example.com', 2222, '/run/docker.sock'),
            DockerEnvironment::tcp('chmura', 'daemon.example.com', 2376, '/c/cert.pem', '/c/key.pem', '/c/ca.pem'),
        ]);
        $book->makeCurrent('serwer');
        DockerStateService::getInstance()->save($book);

        $this->resetSingleton(DockerStateService::class);
        $loaded = DockerStateService::getInstance()->load()->book;

        self::assertSame('serwer', $loaded->current());

        $tunnel = $loaded->find('serwer');
        self::assertNotNull($tunnel);
        self::assertSame(EnvironmentKind::SshTunnel, $tunnel->kind);
        self::assertSame('anna@example.com', $tunnel->target);
        self::assertSame(2222, $tunnel->port);
        self::assertSame('/run/docker.sock', $tunnel->socketPath);

        $tcp = $loaded->find('chmura');
        self::assertNotNull($tcp);
        self::assertSame('/c/key.pem', $tcp->keyPath);
    }

    /** Prawa `0600`: wpisy mówią, z jakimi maszynami użytkownik rozmawia. */
    public function testTheFileIsReadableOnlyByItsOwner(): void
    {
        $service = DockerStateService::getInstance();
        $service->save(new EnvironmentBook([DockerEnvironment::localSocket('praca')]));

        self::assertSame(0o600, fileperms($service->location()) & 0o777);
    }

    /** Krok 60 dopisze rejestry kluczami tej samej sekcji — dokument ma to unieść bez migracji. */
    public function testUnknownKeysSurviveASave(): void
    {
        $service = DockerStateService::getInstance();
        $location = $service->location();

        mkdir(dirname($location), 0o700, true);
        file_put_contents($location, json_encode([
            'docker' => [
                'registries' => [['name' => 'ghcr']],
                'environments' => [],
            ],
        ]));

        $service->load();
        $service->save(new EnvironmentBook([DockerEnvironment::localSocket('praca')]));

        /** @var array<string, mixed> $document */
        $document = json_decode((string) file_get_contents($location), true);
        $section = $document['docker'] ?? null;

        self::assertIsArray($section);
        self::assertSame([['name' => 'ghcr']], $section['registries'] ?? null);
        self::assertIsArray($section['environments'] ?? null);
    }

    /** Jeden zepsuty wpis nie odbiera użytkownikowi całej książki. */
    public function testABrokenEntryFallsOutAndTheRestStays(): void
    {
        $service = DockerStateService::getInstance();
        $location = $service->location();

        mkdir(dirname($location), 0o700, true);
        file_put_contents($location, json_encode([
            'docker' => [
                'environments' => [
                    ['name' => '-zly', 'kind' => 'tunnel', 'target' => 'example.com'],
                    ['name' => 'dobry', 'kind' => 'local', 'socket' => '/var/run/docker.sock'],
                    ['kind' => 'local'],
                ],
            ],
        ]));

        $book = $service->load()->book;

        self::assertCount(1, $book->all());
        self::assertNotNull($book->find('dobry'));
    }

    /**
     * Stary `docker.json` czyta się jak sekcja (D103): książka i wybór wracają,
     * a plik zostaje na dysku nietknięty.
     */
    public function testTheLegacyFileIsReadAsASectionAndSurvivesUntouched(): void
    {
        $directory = $this->home . '/.light-manager';
        mkdir($directory, 0o700, true);
        $legacy = json_encode([
            'environments' => [['name' => 'praca', 'kind' => 'local', 'socket' => '/var/run/docker.sock']],
            'currentEnvironment' => 'praca',
        ]);
        file_put_contents($directory . '/docker.json', $legacy);

        $service = DockerStateService::getInstance();
        $loaded = $service->load();

        self::assertSame('praca', $loaded->book->current());
        self::assertNotNull($loaded->book->find('praca'));

        $service->save($loaded->book);

        self::assertSame($legacy, file_get_contents($directory . '/docker.json'), 'stary plik nietknięty');
    }
}
