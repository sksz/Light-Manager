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
    private const ID = 'a1b2c3d4e5f6';

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

    /**
     * Oglądanie zaczyna się od katalogu zapamiętanego — a pamięć jest
     * **kluczowana identyfikatorem wpisu** (krok 60), więc przeżywa zmianę
     * jego nazwy.
     */
    public function testTheRememberedDirectoryStartsTheListing(): void
    {
        $this->storage->rememberDirectory(self::ID, '/home/anna/dokumenty');

        $browser = $this->browser();
        $browser->open(self::host());

        self::assertSame('/home/anna/dokumenty', $this->directories->requested?->value);
    }

    /** Nic niezapamiętane znaczy „niech rozstrzygnie serwer" — czyli katalog domowy. */
    public function testWithoutMemoryTheServerDecides(): void
    {
        $browser = $this->browser();
        $browser->open(self::host());

        self::assertNull($this->directories->requested);
    }

    /** Katalog zapamiętany pod **cudzym** identyfikatorem nie dotyczy tego wpisu. */
    public function testMemoryOfAnotherEntryIsNotUsed(): void
    {
        $this->storage->rememberDirectory('f6e5d4c3b2a1', '/srv');

        $browser = $this->browser();
        $browser->open(self::host());

        self::assertNull($this->directories->requested);
    }

    /** Ścieżka względna w pamięci odpada po cichu — kończy się katalogiem domowym. */
    public function testARelativeStartingDirectoryIsIgnored(): void
    {
        $this->storage->rememberDirectory(self::ID, 'srv/dane');

        $browser = $this->browser();
        $browser->open(self::host());

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
        $this->storage->rememberDirectory(self::ID, '/');
        $browser->open(self::host());
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

    /**
     * Katalog zapamiętuje się **pod identyfikatorem wpisu książki**, bo to on
     * jest tożsamością (krok 60) — nazwę wolno zmienić, a pamięć ma za nią
     * pójść.
     */
    public function testTheVisitedDirectoryIsRemembered(): void
    {
        $browser = $this->opened();
        $browser->putCursor(0);
        $browser->enter();
        $browser->tick();

        self::assertSame('/home/anna/dokumenty', $this->storage->directories[self::ID] ?? null);
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

    private static function host(): HostProfile
    {
        return new HostProfile(self::ID, 'biuro', 'example.com', 22, 'anna');
    }
}
