<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Ssh;

use LightManager\Infrastructure\Config\StateDocumentService;
use LightManager\Module\Ssh\Domain\ValueObject\AuthMethod;
use LightManager\Module\Ssh\Domain\ValueObject\HostCredentials;
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
 * wersja nie zna, przeżywają zapis**. Od kroku 59 dochodzi trzecia: stary
 * `ssh.json` czyta się jak sekcja, a na dysku zostaje nietknięty.
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

    public function testAnEmptySectionGivesDefaultCredentials(): void
    {
        $credentials = SshStateService::getInstance()->credentials('0000000a');

        self::assertSame(AuthMethod::Agent, $credentials->auth);
        self::assertNull($credentials->keyPath);
    }

    public function testWhatWasSavedComesBack(): void
    {
        $service = SshStateService::getInstance();
        $service->saveCredentials('0000000a', new HostCredentials(AuthMethod::Key, '/home/anna/.ssh/id_ed25519'));

        $this->resetSingleton(SshStateService::class);
        $credentials = SshStateService::getInstance()->credentials('0000000a');

        self::assertSame(AuthMethod::Key, $credentials->auth);
        self::assertSame('/home/anna/.ssh/id_ed25519', $credentials->keyPath);
    }

    /** Prawa `0600`: wpisy mówią, do jakich maszyn i jako kto użytkownik się loguje. */
    public function testTheFileIsReadableOnlyByItsOwner(): void
    {
        $service = SshStateService::getInstance();
        $service->saveCredentials('0000000a', new HostCredentials(AuthMethod::Password));

        self::assertSame('0600', substr(sprintf('%o', fileperms($service->location())), -4));
    }

    /**
     * Zapis poświadczeń nie ma prawa skasować klucza, którego ta wersja nie zna
     * — ani w sekcji, ani obok niej (cudza sekcja dokumentu, D103).
     *
     * **Od kroku 60 dotyczy to także klucza `hosts`**: książka wyprowadziła się
     * do własnej sekcji, a jej stary zapis zostaje na dysku nietknięty, bo jest
     * drogą awaryjną odczytu poświadczeń wpisów sprzed migracji.
     */
    public function testUnknownKeysSurviveASave(): void
    {
        $service = SshStateService::getInstance();
        $directory = $this->home . '/.light-manager';
        mkdir($directory, 0o700, true);

        file_put_contents($service->location(), json_encode([
            'ssh' => [
                'hosts' => [['name' => 'biuro', 'host' => 'example.com']],
                'transfers' => ['ostatni' => '/var/log/syslog'],
            ],
            'docker' => ['environments' => [['name' => 'cudza', 'kind' => 'local']]],
        ]));

        $this->resetSingleton(SshStateService::class);
        $this->resetSingleton(StateDocumentService::class);
        $service = SshStateService::getInstance();
        $service->saveCredentials('0000000a', new HostCredentials(AuthMethod::Password));

        $document = json_decode((string) file_get_contents($service->location()), true);

        self::assertIsArray($document);
        self::assertIsArray($document['ssh'] ?? null);
        self::assertSame(['ostatni' => '/var/log/syslog'], $document['ssh']['transfers'] ?? null);
        self::assertSame(
            [['name' => 'biuro', 'host' => 'example.com']],
            $document['ssh']['hosts'] ?? null,
            'stara książka zostaje nietknięta — jest drogą awaryjną odczytu poświadczeń',
        );
        self::assertSame(
            ['environments' => [['name' => 'cudza', 'kind' => 'local']]],
            $document['docker'] ?? null,
            'cudza sekcja przeżywa zapis',
        );
    }

    /**
     * **Droga awaryjna po nazwie** (krok 60): wpis sprzed migracji nie ma jak
     * mieć poświadczeń pod nowym identyfikatorem, bo ten powstał losowo dopiero
     * przy przenosinach — więc czyta się je z nietkniętego klucza `hosts`.
     */
    public function testCredentialsOfAMigratedEntryAreFoundByName(): void
    {
        $directory = $this->home . '/.light-manager';
        mkdir($directory, 0o700, true);
        file_put_contents($directory . '/state.json', json_encode(['ssh' => ['hosts' => [
            ['name' => 'biuro', 'host' => 'example.com', 'auth' => 'key', 'keyPath' => '/home/anna/.ssh/id_ed25519'],
        ]]]));

        $credentials = SshStateService::getInstance()->credentials('0000000a', 'biuro');

        self::assertSame(AuthMethod::Key, $credentials->auth);
        self::assertSame('/home/anna/.ssh/id_ed25519', $credentials->keyPath);
    }

    /** Zapisane poświadczenie **wygrywa** ze starym wpisem o tej samej nazwie. */
    public function testSavedCredentialsWinOverTheLegacyEntry(): void
    {
        $directory = $this->home . '/.light-manager';
        mkdir($directory, 0o700, true);
        file_put_contents($directory . '/state.json', json_encode(['ssh' => ['hosts' => [
            ['name' => 'biuro', 'host' => 'example.com', 'auth' => 'password'],
        ]]]));

        $service = SshStateService::getInstance();
        $service->saveCredentials('0000000a', new HostCredentials(AuthMethod::Agent));

        self::assertSame(AuthMethod::Agent, $service->credentials('0000000a', 'biuro')->auth);
    }

    /** Katalog pamięta się **osobno dla każdego wpisu** — identyfikator jest tożsamością. */
    public function testEachEntryRemembersItsOwnDirectory(): void
    {
        $service = SshStateService::getInstance();
        $service->rememberDirectory('0000000a', '/srv/www');
        $service->rememberDirectory('0000000b', '/home/jan');

        self::assertSame('/srv/www', $service->lastDirectory('0000000a'));
        self::assertSame('/home/jan', $service->lastDirectory('0000000b'));
        self::assertNull($service->lastDirectory('0000000c'));
    }

    /** Katalog zapamiętany przed migracją znajduje się po nazwie wpisu. */
    public function testADirectoryRememberedBeforeTheMigrationIsFoundByName(): void
    {
        $directory = $this->home . '/.light-manager';
        mkdir($directory, 0o700, true);
        file_put_contents($directory . '/state.json', json_encode([
            'ssh' => ['directories' => ['biuro' => '/home/anna/dokumenty']],
        ]));

        self::assertSame('/home/anna/dokumenty', SshStateService::getInstance()->lastDirectory('0000000a', 'biuro'));
    }

    /** Zapis, który niczego nie zmienia, nie dotyka dysku — `F5` przepisywałby plik bez końca. */
    public function testRememberingTheSameDirectoryTwiceDoesNotRewriteTheFile(): void
    {
        $service = SshStateService::getInstance();
        $service->rememberDirectory('0000000a', '/srv/www');
        $stamp = filemtime($service->location());
        clearstatcache();

        $service->rememberDirectory('0000000a', '/srv/www');

        self::assertSame($stamp, filemtime($service->location()));
    }
}
