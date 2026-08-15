<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Infrastructure;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\BackgroundStage;
use LightManager\Application\Port\BackgroundProcessPort;
use LightManager\Infrastructure\Process\BackgroundProcessService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Ssh\Application\Port\RemoteDirectoryPort;
use LightManager\Module\Ssh\Application\RemoteListingState;
use LightManager\Module\Ssh\Domain\Aggregate\RemoteDirectory;
use LightManager\Module\Ssh\Domain\Exception\InvalidRemotePathException;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Domain\ValueObject\RemotePath;

/**
 * Odczyt zdalnego katalogu **procesem potomnym wchodzącym przez gniazdo
 * mistrza** (krok 49).
 *
 * Cała klasa sprowadza się do jednego zdania, tego samego, które rządzi całą
 * Fazą XVII: **żadne wywołanie sieciowe nie pada w rysowaniu klatki** — bo żadne
 * nie pada w procesie aplikacji w ogóle. `begin()` uruchamia potomka i wraca,
 * `advance()` robi jedno `poll()`, które z definicji nie czeka.
 *
 * **Co pokazał pomiar i co z niego wynika dla kształtu tej klasy** (kontener
 * SFTP na pętli zwrotnej, maszyna projektu):
 *
 * | Rzecz | Koszt |
 * |---|---|
 * | otwarcie kanału przez stojącego mistrza | ~0,94 s (`ssh … true` kosztuje tyle samo — to sshd kontenera, nie `sftp`) |
 * | pięć tysięcy wpisów ponad to | ~0,1 s |
 * | rozczytanie pięciu tysięcy wpisów w PHP | 3,2 ms |
 * | `ssh -O check` (bez otwierania kanału) | 0,00 s |
 *
 * Wniosek jest jednoznaczny i przesądza o tym, czego tu **nie ma**: koszt siedzi
 * w **wywołaniu**, a nie we wpisie. Plan kroku przewidywał pracę dwustopniową
 * z budżetem kawałka mierzonym zegarem — miała chronić przed tysiącem obiegów na
 * katalog. Obiegów jest jeden, a rozczytanie wypisu jest o dwa rzędy wielkości
 * tańsze od klatki, więc kawałkowanie chroniłoby przed kosztem, którego nie ma.
 * Zostaje z tamtego wzorca to, co się liczy: **praca jest daną oglądaną co
 * klatkę, nie procesem** (D46).
 *
 * **Strumieni nie scalamy** i powód tego zakazu jest zmierzony, nie teoretyczny
 * — stoi przy `SftpCommand`. W skrócie: `2>&1` przenosiło na wyjście `sftp`
 * tryb nieblokujący, który mistrz połączenia nakłada deskryptorom klienta,
 * a wtedy z dużego katalogu dochodziła jedna trzecia, bez śladu w kodzie wyjścia.
 *
 * `TZ=UTC` w wierszu polecenia nie jest ozdobą: to **potomek** formatuje czas
 * i robi to w swojej strefie. Bez narzucenia daty zdalne rozjeżdżałyby się
 * z lokalnymi o tyle, ile wynosi różnica stref, a rozjazd o dwie godziny
 * w kolumnie „zmieniony" jest dokładnie tym rodzajem błędu, którego nikt nie
 * zauważy.
 */
final class SftpDirectoryService extends AbstractSingleton implements RemoteDirectoryPort
{
    /**
     * Ile czekać na odczyt katalogu.
     *
     * Liczba jest **z pomiaru, nie z sufitu**: katalog o pięciu tysiącach wpisów
     * kosztował sekundę na pętli zwrotnej, więc trzydzieści sekund starcza na
     * łącze wolniejsze o rząd wielkości. Limit istnieje dla przypadku, w którym
     * sesja umarła w sposób, którego klient nie zauważa — wtedy potomek stałby
     * bez końca, a port tłowy jest jeden na całą aplikację.
     */
    private const TIMEOUT_SECONDS = 30;

    private RemoteListingState $state;

    private ?BackgroundHandle $handle = null;

    /** Katalog, o który toczy się bieżąca praca; `null` — pytamy o startowy. */
    private ?RemotePath $pending = null;

    private ?RemoteEntryComparator $comparator = null;

    protected function __construct()
    {
        $this->state = RemoteListingState::idle();
    }

    public function state(): RemoteListingState
    {
        return $this->state;
    }

    public function begin(HostProfile $host, ?RemotePath $path, bool $includeHidden): void
    {
        $this->stop();

        $this->pending = $path;
        $this->state = RemoteListingState::listing($path);
        $this->handle = $this->processes()->start(
            'TZ=UTC ' . SftpCommand::listing($host, $path, $includeHidden, ControlSocket::pathFor($host)),
            self::TIMEOUT_SECONDS,
        );
    }

    public function advance(): void
    {
        if (!$this->state->isWorking() || $this->handle === null) {
            return;
        }

        $result = $this->processes()->poll($this->handle);

        if ($result->stage === BackgroundStage::Running) {
            return;
        }

        if ($result->stage === BackgroundStage::Idle) {
            // Praca wyparta przez inny moduł — port rdzenia prowadzi jedną naraz
            // (D87 nr 9), a cudzego wyniku nie wolno wziąć za swój.
            $this->handle = null;
            $this->state = RemoteListingState::failed($this->pending, 'module.ssh.listing.interrupted');

            return;
        }

        $this->handle = null;

        if ($result->stage === BackgroundStage::Failed) {
            $this->state = RemoteListingState::failed(
                $this->pending,
                $result->problemKey ?? 'module.ssh.listing.failed',
            );

            return;
        }

        $this->finish($result->output, $result->errorOutput, $result->exitCode ?? 0);
    }

    public function stop(): void
    {
        if ($this->handle !== null) {
            $this->processes()->stop($this->handle);
            $this->handle = null;
        }

        $this->pending = null;
        $this->state = RemoteListingState::idle();
    }

    /**
     * Rozstrzyga, czym skończył się odczyt.
     *
     * **Kod wyjścia rozstrzyga o powodzeniu, a wypis o powodzie** — i ta
     * kolejność jest istotna: `sftp` w trybie wsadowym przerywa na pierwszym
     * błędzie i kończy się jedynką, ale wypisuje przy tym zdanie, które umie
     * odróżnić katalog bez prawa wejścia od sesji, której już nie ma.
     *
     * Wypis pusty przy kodzie zero jest **poprawnym stanem**, a nie awarią:
     * tak wygląda katalog bez wpisów.
     */
    private function finish(string $output, string $errorOutput, int $exitCode): void
    {
        $listing = SftpListingParser::parse($output, $this->pending, time());

        if ($exitCode !== 0) {
            // Powód bierze się ze **strumienia błędów**, a wiersze nie do
            // rozczytania z wyjścia są tylko uzupełnieniem: klient narzeka na
            // tym pierwszym, a scalenie ich w wierszu polecenia jest tu
            // zakazane (patrz `SftpCommand`).
            $this->state = RemoteListingState::failed(
                $this->pending,
                SftpFailureReader::read($errorOutput === '' ? $listing->messageText() : $errorOutput),
            );

            return;
        }

        $path = $this->pathOf($listing);

        if ($path === null) {
            // Kod zero, a katalogu roboczego nie ma czym nazwać — zdarza się
            // wtedy, gdy `pwd` nie doszło. Zdanie jest tu lepsze niż lista
            // pokazana pod ścieżką zmyśloną.
            $this->state = RemoteListingState::failed(null, 'module.ssh.listing.failed');

            return;
        }

        $this->state = RemoteListingState::ready(new RemoteDirectory(
            $path,
            $this->comparator()->sort($listing->entries),
        ));
    }

    /** Ścieżka odczytanego katalogu: ta zamówiona albo ta, którą podał `pwd`. */
    private function pathOf(SftpListing $listing): ?RemotePath
    {
        if ($this->pending !== null) {
            return $this->pending;
        }

        $working = $listing->workingDirectory;

        if ($working === null) {
            return null;
        }

        try {
            return RemotePath::of($working);
        } catch (InvalidRemotePathException) {
            // Port nie rzuca przez granicę (reguła 8), a ścieżka nie do przyjęcia
            // jest tu stanem tak samo zwykłym jak katalog bez prawa wejścia.
            return null;
        }
    }

    private function comparator(): RemoteEntryComparator
    {
        return $this->comparator ??= RemoteEntryComparator::create();
    }

    private function processes(): BackgroundProcessPort
    {
        return BackgroundProcessService::getInstance();
    }
}
