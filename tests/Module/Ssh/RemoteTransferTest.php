<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Ssh;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\TransferChoice;
use LightManager\Application\Event\EventRegistry;
use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ContextOrigin;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Query\QueryRegistry;
use LightManager\Module\Ssh\Application\RemoteBrowser;
use LightManager\Module\Ssh\Application\RemoteTransferItem;
use LightManager\Module\Ssh\Application\RemoteTransferState;
use LightManager\Module\Ssh\Application\SshEvent;
use LightManager\Module\Ssh\Application\SshSettings;
use LightManager\Module\Ssh\Application\TransferDirection;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntry;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntryType;
use LightManager\Module\Ssh\Presentation\LocalPlace;
use LightManager\Module\Ssh\Presentation\Query\EntriesQuery;
use LightManager\Module\Ssh\Presentation\Query\TransferQuery;
use LightManager\Module\Ssh\Presentation\RemoteTransfer;
use LightManager\Module\Ssh\Presentation\SshQueries;
use LightManager\Presentation\Ui\Component\TextInput;
use LightManager\Presentation\Ui\Overlay\ChoiceOverlay;
use LightManager\Presentation\Ui\Overlay\ProgressOverlay;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Tests\Support\StubHostBook;
use LightManager\Tests\Support\StubRemoteDirectory;
use LightManager\Tests\Support\StubRemoteTransfer;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Łańcuch okien przesyłu (krok 50) — **na atrapie portu**, więc bez sieci
 * i bez dysku.
 *
 * Sprawdza to, czego nie widać w usłudze: skąd bierze się druga strona przesyłu,
 * które okno staje po którym i co zostaje w pasku stanu. Najważniejsze zdanie
 * całego pliku dotyczy jednak czegoś innego — **że lokalna strona bierze się
 * z zatrzaśniętego kontekstu**, bo kontekst sesji jest jeden, a ekran zdalny
 * nadpisuje go własnym (D89 nr 8).
 */
final class RemoteTransferTest extends TestCase
{
    /** Wpis 0 zdalnej listy jest katalogiem, wpis 1 — plikiem. */
    private const FILE_ROW = 1;

    private const DIRECTORY_ROW = 0;

    private StubRemoteDirectory $directories;

    private LocalPlace $local;

    private EventRegistry $events;

    private RecordingTransferListener $listener;

    protected function setUp(): void
    {
        $this->directories = new StubRemoteDirectory([
            '/home/anna' => [
                new RemoteEntry('dokumenty', RemoteEntryType::Directory),
                new RemoteEntry('list.txt', RemoteEntryType::File, 120),
            ],
        ]);
        $this->local = new LocalPlace();
        $this->listener = new RecordingTransferListener();
        $this->events = new EventRegistry();
        $this->events->declare('ssh', SshEvent::declarations());
        $this->events->useModules([$this->listener]);
    }

    /** Pobranie pyta o katalog docelowy i podpowiada ten, w którym stoi przeglądarka. */
    public function testDownloadAsksForTheTargetAndSuggestsTheLocalDirectory(): void
    {
        $this->local->remember(new ModuleContext('/home/anna/pobrane'));
        [$transfers] = $this->transfers();

        $outcome = $transfers->downloadRequest();

        self::assertInstanceOf(PromptOverlay::class, $outcome->next);
        self::assertSame('/home/anna/pobrane', self::valueOf($outcome->next));
    }

    /** Zdalny kontekst **nie jest** stroną lokalną — zatrzask go nie przyjmuje. */
    public function testTheRemoteContextIsNeverMistakenForTheLocalOne(): void
    {
        $this->local->remember(new ModuleContext('/home/anna/pobrane'));
        $this->local->remember(new ModuleContext(
            '/var/log',
            'syslog',
            ContextEntryKind::File,
            origin: ContextOrigin::Remote,
        ));

        self::assertSame('/home/anna/pobrane', $this->local->path());
        self::assertNull($this->local->fileName(), 'zdalne zaznaczenie nie jest plikiem do wysłania');
    }

    /** Katalog pod kursorem odmawia zdaniem, a nie pustą pracą (D89 nr 5). */
    public function testADirectoryIsRefusedWithASentence(): void
    {
        [$transfers, $port] = $this->transfers(cursor: self::DIRECTORY_ROW);

        $outcome = $transfers->downloadRequest();

        self::assertNull($outcome->next);
        self::assertSame([], $port->started);
        self::assertStringContainsString('transfer.onlyFiles', (string) $outcome->message?->text);
    }

    /** Ze ścieżką w argumencie praca rusza od razu i staje okno postępu. */
    public function testAPathInTheArgumentStartsTheWorkAtOnce(): void
    {
        [$transfers, $port] = $this->transfers();

        $outcome = $transfers->downloadRequest('/home/anna/pobrane');

        self::assertInstanceOf(ProgressOverlay::class, $outcome->next);
        self::assertCount(1, $port->started);

        [$items, $target, $direction] = $port->started[0];

        self::assertSame('/home/anna/list.txt', $items[0]->path);
        self::assertSame(120, $items[0]->sizeInBytes, 'rozmiar z wypisu listy jest mianownikiem paska');
        self::assertSame('/home/anna/pobrane', $target);
        self::assertSame(TransferDirection::Download, $direction);
    }

    /** Wysłanie bierze źródło z kontekstu, a zajętość celu — z listy, którą panel ma na ekranie. */
    public function testUploadTakesTheSourceFromTheContextAndTheOccupiedNamesFromTheListing(): void
    {
        $this->local->remember(new ModuleContext(
            '/home/anna',
            'raport.pdf',
            ContextEntryKind::File,
            selectionBytes: 900,
        ));
        [$transfers, $port] = $this->transfers();

        $transfers->uploadRequest('/home/anna');

        [$items, $target, $direction, $occupied] = $port->started[0];

        self::assertSame('/home/anna/raport.pdf', $items[0]->path);
        self::assertSame(900, $items[0]->sizeInBytes);
        self::assertSame('/home/anna', $target);
        self::assertSame(TransferDirection::Upload, $direction);
        self::assertSame(['dokumenty', 'list.txt'], $occupied);
    }

    /** Katalog inny niż otwarty oddaje **pustą** listę zajętych: „nie wiem", a nie „nic tam nie ma". */
    public function testAnotherRemoteDirectoryMeansNoKnowledgeOfOccupiedNames(): void
    {
        $this->local->remember(new ModuleContext('/home/anna', 'raport.pdf', ContextEntryKind::File));
        [$transfers, $port] = $this->transfers();

        $transfers->uploadRequest('/srv/dane');

        self::assertSame([], $port->started[0][3]);
    }

    /** Bez zaznaczonego pliku lokalnego wysyłanie mówi dlaczego i niczego nie zaczyna. */
    public function testUploadWithoutALocalSelectionSaysWhy(): void
    {
        [$transfers, $port] = $this->transfers();

        $outcome = $transfers->uploadRequest('/home/anna');

        self::assertSame([], $port->started);
        self::assertStringContainsString('transfer.noLocal', (string) $outcome->message?->text);
    }

    /** Zajęta nazwa otwiera okno wyboru, a pierwsza odpowiedź wraca do pracy jako „nadpisz”. */
    public function testATakenNameOpensTheChoiceWindow(): void
    {
        $colliding = RemoteTransferState::beginning([new RemoteTransferItem('/home/anna/list.txt', 'list.txt', 120)])
            ->colliding('list.txt');
        [$transfers, $port] = $this->transfers(port: new StubRemoteTransfer($colliding));

        $outcome = $transfers->downloadRequest('/home/anna/pobrane');

        self::assertInstanceOf(ChoiceOverlay::class, $outcome->next);

        $port->willStep(RemoteTransferState::idle()->done());
        $outcome->next->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame([[TransferChoice::Overwrite, null]], $port->answers);
    }

    /** Praca skończona zamyka okno zdaniem i **publikuje zdarzenie modułu**. */
    public function testAFinishedTransferReportsAndPublishesAnEvent(): void
    {
        $done = RemoteTransferState::beginning([new RemoteTransferItem('/home/anna/list.txt', 'list.txt', 120)])
            ->withFinished(120)
            ->done();
        [$transfers, $port] = $this->transfers(port: new StubRemoteTransfer($done));

        $outcome = $transfers->downloadRequest('/home/anna/pobrane');

        self::assertNull($outcome->next, 'praca skończona nie otwiera okna postępu na jedną klatkę');
        self::assertStringContainsString('transfer.download.done', (string) $outcome->message?->text);
        self::assertSame(1, $port->stopCount, 'praca skończona zapomina kolejkę');
        self::assertSame([SshEvent::TransferDone->value], $this->listener->events);
    }

    /** Niepowodzenie mówi powodem z pracy i publikuje **drugie** zdarzenie. */
    public function testAFailureKeepsTheReasonFromTheWork(): void
    {
        $failed = RemoteTransferState::beginning([new RemoteTransferItem('/home/anna/list.txt', 'list.txt', 120)])
            ->failed('module.ssh.transfer.denied');
        [$transfers] = $this->transfers(port: new StubRemoteTransfer($failed));

        $outcome = $transfers->downloadRequest('/home/anna/pobrane');

        self::assertStringContainsString('transfer.denied', (string) $outcome->message?->text);
        self::assertSame([SshEvent::TransferFailed->value], $this->listener->events);
    }

    /** @return array{RemoteTransfer, StubRemoteTransfer} */
    private function transfers(?StubRemoteTransfer $port = null, int $cursor = self::FILE_ROW): array
    {
        $port ??= new StubRemoteTransfer();

        $browser = new RemoteBrowser($this->directories, new StubHostBook(), false);
        $browser->open(new HostProfile('biuro', 'example.com', 22, 'anna'));
        $browser->tick();
        $browser->putCursor($cursor);

        // Rejestr z kwerendami modułu — **jedyna droga odczytu także w teście**
        // (reguła 15g: moduł niezarejestrowany nie widzi własnych danych).
        // Kolejność jest tu wymuszona tak samo, jak w module: fasada trzyma sam
        // rejestr, więc powstaje **przed** kwerendami, a `TransferQuery` dostaje
        // gotowy obiekt pracy.
        $registry = new QueryRegistry();
        $transfers = new RemoteTransfer(
            $browser,
            $port,
            $this->local,
            new StubTranslator(),
            $this->events,
            new SshQueries($registry),
        );
        $registry->add(SshSettings::ID, [
            new EntriesQuery($browser, new StubTranslator()),
            new TransferQuery($transfers, new StubTranslator()),
        ]);

        return [$transfers, $port];
    }

    private static function valueOf(PromptOverlay $overlay): string
    {
        $property = new \ReflectionProperty($overlay, 'input');
        $input = $property->getValue($overlay);

        return $input instanceof TextInput ? $input->value() : '';
    }
}

/** Odbiorca zdarzeń, który wyłącznie je zapisuje — po to, żeby dało się je policzyć. */
final class RecordingTransferListener implements \LightManager\Application\Module\ModuleInterface, \LightManager\Application\Module\ListensToEvents
{
    /** @var list<string> */
    public array $events = [];

    public function id(): string
    {
        return 'listener';
    }

    public function nameKey(): string
    {
        return 'listener.name';
    }

    public function descriptionKey(): string
    {
        return 'listener.description';
    }

    public function shortcut(): ?\LightManager\Application\Module\ModuleShortcut
    {
        return null;
    }

    public function translations(): ?string
    {
        return null;
    }

    public function onEvent(string $event): void
    {
        $this->events[] = $event;
    }
}
