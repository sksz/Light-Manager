<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Ssh;

use LightManager\Infrastructure\Config\StateDocumentService;
use LightManager\Module\Ssh\Application\HostBook;
use LightManager\Module\Ssh\Domain\ValueObject\AuthMethod;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Infrastructure\SshStateService;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\TestCase;

/**
 * Stan modułu sesji zdalnej — sekcja `ssh` dokumentu stanu (krok 48; od kroku
 * 59 w `~/.light-manager/state.json`, D103).
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
            'ssh' => [
                'hosts' => [],
                'transfers' => ['ostatni' => '/var/log/syslog'],
            ],
            'docker' => ['environments' => [['name' => 'cudza', 'kind' => 'local']]],
        ]));

        $this->resetSingleton(SshStateService::class);
        $this->resetSingleton(StateDocumentService::class);
        $service = SshStateService::getInstance();
        $service->load();
        $service->save(new HostBook([new HostProfile('biuro', 'example.com')]));

        $document = json_decode((string) file_get_contents($service->location()), true);

        self::assertIsArray($document);
        self::assertIsArray($document['ssh'] ?? null);
        self::assertSame(['ostatni' => '/var/log/syslog'], $document['ssh']['transfers'] ?? null);
        $hosts = $document['ssh']['hosts'] ?? null;

        self::assertIsArray($hosts);
        self::assertCount(1, $hosts);
        self::assertSame(
            ['environments' => [['name' => 'cudza', 'kind' => 'local']]],
            $document['docker'] ?? null,
            'cudza sekcja przeżywa zapis książki hostów',
        );
    }

    /**
     * Stary `ssh.json` czyta się jak sekcja (D103): książka wraca w całości,
     * a plik zostaje na dysku nietknięty — sekcją dokumentu staje się dopiero
     * przy pierwszym zapisie.
     */
    public function testTheLegacyFileIsReadAsASectionAndSurvivesUntouched(): void
    {
        $directory = $this->home . '/.light-manager';
        mkdir($directory, 0o700, true);
        $legacy = json_encode(['hosts' => [['name' => 'biuro', 'host' => 'example.com']]]);
        file_put_contents($directory . '/ssh.json', $legacy);

        $service = SshStateService::getInstance();
        $loaded = $service->load();

        self::assertSame(['biuro'], $loaded->book->names());
        self::assertFalse($loaded->fresh, 'stara książka nie jest świeżym startem');

        $service->save($loaded->book);

        self::assertSame($legacy, file_get_contents($directory . '/ssh.json'), 'stary plik nietknięty');
        $document = json_decode((string) file_get_contents($directory . '/state.json'), true);

        self::assertIsArray($document);

        $section = $document['ssh'] ?? null;

        self::assertIsArray($section);
        $sectionHosts = $section['hosts'] ?? null;

        self::assertIsArray($sectionHosts);
        self::assertCount(1, $sectionHosts);
    }

    /**
     * **Zapowiedź kroku 48 rozliczona w kroku 49**: ostatni katalog dopisał się
     * do tego samego dokumentu, obok książki, bez migracji.
     */
    public function testTheRememberedDirectoryLivesBesideTheBook(): void
    {
        $service = SshStateService::getInstance();
        $service->save(new HostBook([new HostProfile('biuro', 'example.com')]));
        $service->rememberDirectory('biuro', '/home/anna/dokumenty');

        $this->resetSingleton(SshStateService::class);
        $reloaded = SshStateService::getInstance();

        self::assertSame('/home/anna/dokumenty', $reloaded->lastDirectory('biuro'));
        self::assertCount(1, $reloaded->load()->book->all(), 'książka przeżyła dopisanie katalogu');
    }

    /** Katalog pamięta się **osobno dla każdego wpisu** — nazwa jest tożsamością hosta. */
    public function testEachHostRemembersItsOwnDirectory(): void
    {
        $service = SshStateService::getInstance();
        $service->rememberDirectory('biuro', '/srv/www');
        $service->rememberDirectory('dom', '/home/jan');

        self::assertSame('/srv/www', $service->lastDirectory('biuro'));
        self::assertSame('/home/jan', $service->lastDirectory('dom'));
        self::assertNull($service->lastDirectory('nieznany'));
    }

    /** Zapis, który niczego nie zmienia, nie dotyka dysku — `F5` przepisywałby plik bez końca. */
    public function testRememberingTheSameDirectoryTwiceDoesNotRewriteTheFile(): void
    {
        $service = SshStateService::getInstance();
        $service->rememberDirectory('biuro', '/srv/www');
        $stamp = filemtime($service->location());
        clearstatcache();

        $service->rememberDirectory('biuro', '/srv/www');

        self::assertSame($stamp, filemtime($service->location()));
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
