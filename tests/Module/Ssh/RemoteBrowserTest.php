<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Ssh;

use LightManager\Module\Ssh\Application\RemoteBrowser;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntry;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntryType;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteNameFilter;
use LightManager\Tests\Support\StubRemoteDirectory;
use LightManager\Tests\Support\StubSshState;
use PHPUnit\Framework\TestCase;

/**
 * Chodzenie po zdalnym katalogu (krok 49) — **na atrapie portu, bez sieci**.
 *
 * Test pilnuje przede wszystkim tego, co odróżnia ten moduł od przeglądarki:
 * lista **przychodzi później**, więc kursor musi istnieć wcześniej, a wpis, na
 * którym ma stanąć po powrocie wyżej, zapisuje się **nazwą**, a nie pozycją.
 */
final class RemoteBrowserTest extends TestCase
{
    private StubRemoteDirectory $directories;

    private StubSshState $storage;

    protected function setUp(): void
    {
        $this->directories = new StubRemoteDirectory([
            '/home/anna' => [
                new RemoteEntry('dokumenty', RemoteEntryType::Directory),
                new RemoteEntry('list.txt', RemoteEntryType::File, 120),
                new RemoteEntry('skrót', RemoteEntryType::Symlink, 8),
            ],
            '/home/anna/dokumenty' => [
                new RemoteEntry('umowa.pdf', RemoteEntryType::File, 4096),
            ],
            '/home' => [
                new RemoteEntry('anna', RemoteEntryType::Directory),
                new RemoteEntry('jan', RemoteEntryType::Directory),
            ],
        ]);
        $this->storage = new StubSshState();
    }

    public function testOpeningAHostAsksForTheStartingDirectory(): void
    {
        $browser = $this->browser();
        $browser->open(self::host());
        $browser->tick();

        self::assertNull($this->directories->requested, 'bez pamięci i bez profilu rozstrzyga serwer');
        self::assertSame('/home/anna', $browser->path()?->value);
        self::assertSame(3, $browser->count());
    }

    /** Zapamiętany katalog ma pierwszeństwo przed startowym z profilu. */
    public function testTheRememberedDirectoryWins(): void
    {
        $this->storage->rememberDirectory('00000001', '/home/anna/dokumenty');

        $browser = $this->browser();
        $browser->open(self::host(remoteDirectory: '/srv'));

        self::assertSame('/home/anna/dokumenty', $this->directories->requested?->value);
    }

    public function testTheProfileDirectoryIsUsedWhenNothingIsRemembered(): void
    {
        $browser = $this->browser();
        $browser->open(self::host(remoteDirectory: '/srv'));

        self::assertSame('/srv', $this->directories->requested?->value);
    }

    /** Ścieżka względna w pliku stanu odpada po cichu — kończy się katalogiem domowym. */
    public function testARelativeStartingDirectoryIsIgnored(): void
    {
        $browser = $this->browser();
        $browser->open(self::host(remoteDirectory: 'srv/dane'));

        self::assertNull($this->directories->requested);
    }

    public function testEnteringADirectoryAsksForItsPath(): void
    {
        $browser = $this->opened();
        $browser->putCursor(0);

        self::assertTrue($browser->enter());
        self::assertSame('/home/anna/dokumenty', $this->directories->requested?->value);

        $browser->tick();

        self::assertSame('umowa.pdf', $browser->selected()?->name);
    }

    /** Plik nie jest katalogiem: `Enter` nie zamawia niczego. */
    public function testEnteringAFileDoesNothing(): void
    {
        $browser = $this->opened();
        $browser->putCursor(1);
        $reads = $this->directories->reads;

        self::assertFalse($browser->enter());
        self::assertSame($reads, $this->directories->reads);
    }

    /**
     * Dowiązanie **próbuje się otworzyć**, choć nie wiadomo, dokąd prowadzi —
     * rozstrzygnięcie użytkownika ze startu kroku.
     */
    public function testASymlinkIsTried(): void
    {
        $browser = $this->opened();
        $browser->putCursor(2);

        self::assertTrue($browser->enter());
        self::assertSame('/home/anna/skrót', $this->directories->requested?->value);
    }

    /** Powrót wyżej stawia kursor na katalogu, z którego się wyszło. */
    public function testGoingUpSelectsTheDirectoryWeCameFrom(): void
    {
        $browser = $this->opened();

        self::assertTrue($browser->goUp());

        $browser->tick();

        self::assertSame('/home', $browser->path()?->value);
        self::assertSame('anna', $browser->selected()?->name, 'kursor wraca na miejsce, z którego wyszliśmy');
    }

    public function testTheRootHasNowhereToGo(): void
    {
        $browser = $this->browser();
        $browser->open(self::host(remoteDirectory: '/'));
        $browser->tick();

        self::assertFalse($browser->goUp());
    }

    /** Odświeżenie zostawia kursor tam, gdzie stał. */
    public function testRefreshKeepsTheCursorOnItsEntry(): void
    {
        $browser = $this->opened();
        $browser->putCursor(1);

        self::assertTrue($browser->refresh());

        $browser->tick();

        self::assertSame('list.txt', $browser->selected()?->name);
    }

    /**
     * Wpisy ukryte kosztują **nowy obieg**, bo serwer bez `ls -a` w ogóle ich nie
     * przysyła — to jest różnica wobec przeglądarki, w której ta sama czynność
     * kosztuje przejście po tablicy.
     */
    public function testTogglingHiddenEntriesAsksTheServerAgain(): void
    {
        $browser = $this->opened();
        $reads = $this->directories->reads;

        $browser->toggleHidden();

        self::assertSame($reads + 1, $this->directories->reads);
        self::assertTrue($this->directories->withHidden);
        self::assertTrue($browser->showsHidden());
    }

    /** Filtr działa na tym, co już przyszło — i nie kosztuje ani jednego obiegu. */
    public function testFilteringCostsNoRoundTrip(): void
    {
        $browser = $this->opened();
        $reads = $this->directories->reads;

        $browser->useFilter(new RemoteNameFilter('txt'));

        self::assertSame($reads, $this->directories->reads);
        self::assertSame(1, $browser->count());
        self::assertSame('list.txt', $browser->selected()?->name);

        $browser->clearFilter();

        self::assertSame(3, $browser->count());
    }

    /** Katalog zapamiętuje się **pod nazwą wpisu książki**, bo to ona jest tożsamością. */
    public function testTheVisitedDirectoryIsRemembered(): void
    {
        $browser = $this->opened();
        $browser->putCursor(0);
        $browser->enter();
        $browser->tick();

        // Katalog zapamiętuje się od kroku 60 pod **identyfikatorem wpisu**
        // książki adresowej, a nie pod nazwą: nazwa bywa pusta i powtórzona.
        self::assertSame('/home/anna/dokumenty', $this->storage->directories['00000001'] ?? null);
    }

    /** Kursor przeżywa oczekiwanie: lista przychodzi później, a ruch po niej wcześniej. */
    public function testTheCursorStaysWithinTheListWhileItIsBeingRead(): void
    {
        $this->directories->keepWorking();

        $browser = $this->browser();
        $browser->open(self::host());
        $browser->tick();
        $browser->moveCursor(5);

        self::assertSame(0, $browser->cursor());
        self::assertNull($browser->selected());
        self::assertFalse($browser->hasListing());
    }

    /** Zerwana sesja kończy się stanem niepowodzenia, a nie wyjątkiem. */
    public function testAFailedReadLeavesNoListing(): void
    {
        $this->directories->failWith('module.ssh.listing.dropped');

        $browser = $this->browser();
        $browser->open(self::host());
        $browser->tick();

        self::assertFalse($browser->hasListing());
        self::assertSame('module.ssh.listing.dropped', $browser->state()->problemKey);
    }

    private function opened(): RemoteBrowser
    {
        $browser = $this->browser();
        $browser->open(self::host());
        $browser->tick();

        return $browser;
    }

    private function browser(): RemoteBrowser
    {
        return new RemoteBrowser($this->directories, $this->storage);
    }

    private static function host(?string $remoteDirectory = null): HostProfile
    {
        return new HostProfile('00000001', 'biuro', 'example.com', 22, 'anna', remoteDirectory: $remoteDirectory);
    }
}
