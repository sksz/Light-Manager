<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Ssh;

use LightManager\Module\Ssh\Application\HostBook;
use LightManager\Module\Ssh\Domain\ValueObject\AuthMethod;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Infrastructure\SshStateService;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\TestCase;

/**
 * Plik stanu modułu `~/.light-manager/ssh.json` (krok 48).
 *
 * Test podstawia `HOME` na katalog tymczasowy — tą samą drogą, którą robi to
 * test pliku dźwięku. Sprawdza dwie rzeczy, których z kodu wołającego nie
 * widać: **plik ruszony ręcznie nie wywraca startu** i **klucze, których ta
 * wersja nie zna, przeżywają zapis** — bo kroki 49 i 50 dopiszą do tego samego
 * dokumentu ostatni katalog zdalny i historię przesyłów.
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
    }

    public function testAMissingFileIsAFreshStartWithoutAProblem(): void
    {
        $loaded = SshStateService::getInstance()->load();

        self::assertTrue($loaded->book->isEmpty());
        self::assertNull($loaded->problemKey);
        self::assertTrue($loaded->fresh);
    }

    public function testWhatWasSavedComesBack(): void
    {
        $service = SshStateService::getInstance();
        $service->save(new HostBook([
            new HostProfile('biuro', 'example.com', 2222, 'anna', AuthMethod::Key, '/home/anna/.ssh/id_ed25519'),
            new HostProfile('dom', '192.168.1.10'),
        ]));

        $this->resetSingleton(SshStateService::class);
        $book = SshStateService::getInstance()->load()->book;

        self::assertSame(['biuro', 'dom'], $book->names());

        $first = $book->find('biuro');
        self::assertNotNull($first);
        self::assertSame(2222, $first->port);
        self::assertSame('anna', $first->user);
        self::assertSame(AuthMethod::Key, $first->auth);
        self::assertSame('/home/anna/.ssh/id_ed25519', $first->keyPath);
    }

    /** Prawa `0600`: wpisy mówią, do jakich maszyn i jako kto użytkownik się loguje. */
    public function testTheFileIsReadableOnlyByItsOwner(): void
    {
        $service = SshStateService::getInstance();
        $service->save(new HostBook([new HostProfile('biuro', 'example.com')]));

        self::assertSame('0600', substr(sprintf('%o', fileperms($service->location())), -4));
    }

    /**
     * **Warunek, bez którego kroki 49 i 50 musiałyby założyć drugi plik.**
     * Zapis książki nie ma prawa skasować klucza, którego ta wersja nie zna.
     */
    public function testUnknownKeysSurviveASave(): void
    {
        $service = SshStateService::getInstance();
        $directory = $this->home . '/.light-manager';
        mkdir($directory, 0o700, true);

        file_put_contents($service->location(), json_encode([
            'hosts' => [],
            'transfers' => ['ostatni' => '/var/log/syslog'],
        ]));

        $this->resetSingleton(SshStateService::class);
        $service = SshStateService::getInstance();
        $service->load();
        $service->save(new HostBook([new HostProfile('biuro', 'example.com')]));

        $document = json_decode((string) file_get_contents($service->location()), true);

        self::assertIsArray($document);
        self::assertArrayHasKey('transfers', $document);
        self::assertSame(['ostatni' => '/var/log/syslog'], $document['transfers']);
        self::assertIsArray($document['hosts']);
        self::assertCount(1, $document['hosts']);
    }

    /** Dokument bez sensu znaczy „nie wiem, co tu jest” — i mówi to zamiast wywracać start. */
    public function testAnUnreadableFileGivesAnEmptyBookWithAReason(): void
    {
        $directory = $this->home . '/.light-manager';
        mkdir($directory, 0o700, true);
        file_put_contents($directory . '/ssh.json', 'to nie jest JSON');

        $loaded = SshStateService::getInstance()->load();

        self::assertTrue($loaded->book->isEmpty());
        self::assertSame('module.ssh.book.unreadable', $loaded->problemKey);
        self::assertFalse($loaded->fresh);
    }

    /**
     * Wpis nie do przyjęcia **wypada, a książka zostaje** — ta sama reguła, co
     * przy pozycji playlisty bez ścieżki. Port nie rzuca (reguła 8), więc profil
     * z hostem zaczynającym się od myślnika ginie po cichu zamiast wywrócić
     * odczyt całego spisu.
     */
    public function testASingleBrokenEntryFallsOutAndTheRestSurvives(): void
    {
        $directory = $this->home . '/.light-manager';
        mkdir($directory, 0o700, true);

        file_put_contents($directory . '/ssh.json', json_encode(['hosts' => [
            ['name' => 'dobry', 'host' => 'example.com'],
            ['name' => 'zły', 'host' => '-oProxyCommand=touch /tmp/ups'],
            ['name' => 'bez hosta'],
            'to nie jest wpis',
        ]]));

        $book = SshStateService::getInstance()->load()->book;

        self::assertSame(['dobry'], $book->names());
    }
}
