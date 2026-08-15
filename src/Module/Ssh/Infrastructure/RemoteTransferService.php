<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Infrastructure;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\BackgroundStage;
use LightManager\Application\Dto\TransferChoice;
use LightManager\Application\Port\BackgroundProcessPort;
use LightManager\Application\Port\FileOperationsPort;
use LightManager\Domain\Exception\FileOperationException;
use LightManager\Infrastructure\FileSystem\FileOperationsService;
use LightManager\Infrastructure\Process\BackgroundProcessService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Ssh\Application\Port\RemoteTransferPort;
use LightManager\Module\Ssh\Application\RemoteTransferItem;
use LightManager\Module\Ssh\Application\RemoteTransferStage;
use LightManager\Module\Ssh\Application\RemoteTransferState;
use LightManager\Module\Ssh\Application\TransferDirection;
use LightManager\Module\Ssh\Domain\Exception\InvalidRemotePathException;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Domain\ValueObject\RemotePath;

/**
 * Przesył plików **procesem potomnym wchodzącym przez gniazdo mistrza**
 * (krok 50).
 *
 * Trzecia usługa tego modułu rozmawiająca z siecią i pierwsza, która **zmienia**
 * to, co pokazuje. Reguła nadrzędna Fazy XVII zostaje ta sama: żadne wywołanie
 * sieciowe nie pada w rysowaniu klatki — bo żadne nie pada w procesie aplikacji
 * w ogóle. `begin()` uruchamia potomka i wraca, `advance()` robi jedno `poll()`.
 *
 * **Cztery rzeczy, na których stoi bezpieczeństwo tej klasy** — każda wzięta
 * z pomiaru na żywym serwerze (D89), nie z ostrożności:
 *
 * 1. **Treść ląduje pod nazwą tymczasową**, a zatwierdza ją zmiana nazwy.
 *    Przerwanie i awaria zostawiają przez to plik, który nazwą mówi, czym jest —
 *    a nie plik wyglądający na gotowy. Kryterium „`Esc` nie zostawia połówki"
 *    jest spełnione **konstrukcyjnie**; sprzątanie jest drugą linią obrony,
 *    nie pierwszą.
 * 2. **Po dysku lokalnym pisze `sftp`, a nazwy zmienia i kasuje port rdzenia**
 *    (`FileOperationsPort`, krok 41). Moduł nie woła ani `rename()`, ani
 *    `unlink()`, więc wyjątek 15b zostaje przy jednym nazwanym przypadku
 *    (D89 nr 1).
 * 3. **Zatwierdzenie zdalne idzie `rename -l`**, czyli bez rozszerzenia
 *    `posix-rename@openssh.com`, które nadpisuje cicho. Nadpisanie ma być
 *    skutkiem odpowiedzi użytkownika, a nie właściwością protokołu.
 * 4. **Zerwane łącze rozpoznaje kod wyjścia, nie wypis.** `sftp` ginie wtedy od
 *    `SIGPIPE` z kodem 141 i **pustym strumieniem błędów** (zmierzone), więc
 *    powód podaje moduł, a nie klient.
 *
 * **Zastój zamiast limitu czasu dobranego z sufitu.** Limit twardy jest po to,
 * żeby zawieszony potomek nie został na zawsze; o zerwanym łączu rozstrzyga
 * plik, który przestał rosnąć. Widać to wyłącznie przy **pobieraniu** — przy
 * wysyłaniu ile poszło w sieć, nie mówi nikt — i jest to cena drogi, przyjęta
 * jawnie (D89 nr 11).
 */
final class RemoteTransferService extends AbstractSingleton implements RemoteTransferPort
{
    /**
     * Kiedy ubić potomka, który przestał cokolwiek robić.
     *
     * Godzina, bo przesył legalnie trwa minuty, a limit z odczytu katalogu (30 s)
     * ubijałby udaną pracę. Liczba jest sufitem awarii, nie miarą pracy —
     * mierzy ją zastój.
     */
    private const TIMEOUT_SECONDS = 3600;

    /** Ile sekund plik może nie rosnąć, zanim uznamy łącze za milczące. */
    private const STALL_SECONDS = 30.0;

    /** Przedrostek i przyrostek nazwy roboczej — kropka chowa ją przed listą. */
    private const TEMPORARY_PREFIX = '.';

    private const TEMPORARY_SUFFIX = '.lm-part';

    private RemoteTransferState $state;

    private ?BackgroundHandle $handle = null;

    /**
     * Uchwyt **sprzątania zdalnej połówki** — obok pracy, nie w niej.
     *
     * Osobne pole, bo sprzątanie dzieje się po tym, jak użytkownik dostał już
     * zdanie o przerwaniu: stan pracy mówi wtedy „przerwane" i mówi prawdę,
     * a `advance()` woła takt modułu jeszcze przez kilka klatek.
     */
    private ?BackgroundHandle $cleaning = null;

    private ?HostProfile $host = null;

    private TransferDirection $direction = TransferDirection::Download;

    private string $target = '';

    /** @var list<RemoteTransferItem> */
    private array $queue = [];

    private int $index = 0;

    /** @var list<string> */
    private array $occupied = [];

    /** Odpowiedź „dla wszystkich”, jeśli padła — pamięć na czas jednej pracy. */
    private ?TransferChoice $memorised = null;

    /** Ścieżka robocza bieżącego pliku: lokalna przy pobieraniu, zdalna przy wysyłaniu. */
    private string $temporary = '';

    /**
     * Czy bieżący plik ma prawo nadpisać to, co zastanie.
     *
     * Pole, a nie parametr, bo odpowiedź pada **przed** uruchomieniem potomka,
     * a używa się jej **po** jego zakończeniu — przy zatwierdzeniu lokalnym.
     * Bez niej plik, który pojawił się w celu w trakcie przesyłu, zniknąłby bez
     * pytania: dokładnie to ciche nadpisanie, którego `rename -l` zabrania po
     * stronie zdalnej.
     */
    private bool $overwrites = false;

    /** Bajty plików już przeniesionych — podstawa licznika, bo bieżący plik dolicza się osobno. */
    private int $completedBytes = 0;

    private int $lastSize = -1;

    private float $lastGrowthAt = 0.0;

    private ?FileOperationsPort $files = null;

    private ?BackgroundProcessPort $processes = null;

    protected function __construct()
    {
        $this->state = RemoteTransferState::idle();
    }

    /**
     * Podstawienie obu portów — **wyłącznie dla testów**, i to jest jedyne
     * miejsce w tym module, gdzie taki szew istnieje po stronie usługi.
     *
     * `SftpDirectoryService` z kroku 49 go nie ma i słusznie: tamten czyta, więc
     * pomyłka kosztuje pustą listę. Ta klasa **pisze po dwóch systemach plików
     * naraz**, a jej pomyłka kosztuje cudzy plik — więc kolejka, nazwy robocze,
     * kolizje i sprzątanie mają się dać sprawdzić bez jednego bajtu w sieci
     * i bez procesu potomnego.
     */
    public function useSeams(BackgroundProcessPort $processes, FileOperationsPort $files): void
    {
        $this->processes = $processes;
        $this->files = $files;
    }

    public function state(): RemoteTransferState
    {
        return $this->state;
    }

    /**
     * @param list<RemoteTransferItem> $items
     * @param list<string>             $occupied
     */
    public function begin(
        HostProfile $host,
        array $items,
        string $target,
        TransferDirection $direction,
        array $occupied = [],
    ): RemoteTransferState {
        $this->stop();

        if ($items === []) {
            return $this->state;
        }

        $this->host = $host;
        $this->direction = $direction;
        $this->target = $target;
        $this->queue = $items;
        $this->occupied = $occupied;
        $this->index = 0;
        $this->memorised = null;
        $this->completedBytes = 0;
        $this->state = RemoteTransferState::beginning($items);

        return $this->next();
    }

    public function advance(): RemoteTransferState
    {
        $this->pollCleaning();

        if ($this->state->stage !== RemoteTransferStage::Working || $this->handle === null) {
            return $this->state;
        }

        $result = $this->processes()->poll($this->handle);

        if ($result->stage === BackgroundStage::Running) {
            return $this->grew();
        }

        if ($result->stage === BackgroundStage::Idle) {
            // Praca wyparta przez inny moduł — port rdzenia prowadzi jedną naraz
            // (D87 nr 9). Potomka już nie ma, więc połówka została po którejś ze
            // stron i trzeba ją sprzątnąć tak samo, jak po przerwaniu.
            $this->handle = null;

            return $this->broke('module.ssh.transfer.interrupted');
        }

        $this->handle = null;

        if ($result->stage === BackgroundStage::Failed) {
            return $this->broke($result->problemKey ?? 'module.ssh.transfer.failed');
        }

        if (($result->exitCode ?? 0) !== 0) {
            // Wypis pusty przy niezerowym kodzie znaczy **zerwane łącze**:
            // `sftp` ginie wtedy od sygnału i nie zdąży niczego powiedzieć.
            return $this->broke($result->errorOutput === ''
                ? 'module.ssh.transfer.dropped'
                : SftpFailureReader::readTransfer($result->errorOutput));
        }

        return $this->completed();
    }

    public function resolve(TransferChoice $choice, ?string $newName = null): RemoteTransferState
    {
        if ($this->state->stage !== RemoteTransferStage::Colliding) {
            return $this->state;
        }

        if ($choice === TransferChoice::Abort) {
            // Praca stoi na pytaniu, więc potomka nie ma i nie ma czego sprzątać;
            // kolejkę zapomni `stop()`, wołany przez tego, kto odbierze zdanie.
            return $this->state = $this->state->done();
        }

        if ($choice === TransferChoice::OverwriteAll || $choice === TransferChoice::SkipAll) {
            $this->memorised = $choice;
        }

        if ($choice === TransferChoice::Skip || $choice === TransferChoice::SkipAll) {
            $this->state = $this->state->withSkipped();
            ++$this->index;

            return $this->next();
        }

        if ($choice === TransferChoice::Rename) {
            $name = $newName === null ? '' : trim($newName);

            if ($name === '') {
                // Nazwa jest treścią odpowiedzi, nie jej ozdobą — pytanie zostaje.
                return $this->state;
            }

            return $this->launch($name, overwrite: false);
        }

        return $this->launch($this->queue[$this->index]->name, overwrite: true);
    }

    public function stop(): void
    {
        if ($this->handle !== null) {
            $this->processes()->stop($this->handle);
            $this->handle = null;
            $this->sweep();
        }

        $this->host = null;
        $this->queue = [];
        $this->occupied = [];
        $this->index = 0;
        $this->memorised = null;
        $this->temporary = '';
        $this->target = '';
        $this->completedBytes = 0;
        $this->overwrites = false;
        $this->state = RemoteTransferState::idle();
    }

    /**
     * Bierze następny plik z kolejki: pyta o zajętą nazwę albo od razu rusza.
     *
     * Kolizję rozstrzyga **strona, która wie za darmo**: przy pobieraniu dysk
     * (`file_exists`), przy wysyłaniu lista, którą panel ma na ekranie. Pytanie
     * o nią serwerowi kosztowałoby obieg na każdy plik — czyli tyle, co przesył
     * małego pliku.
     */
    private function next(): RemoteTransferState
    {
        if ($this->index >= count($this->queue)) {
            return $this->state = $this->state->done();
        }

        $name = $this->queue[$this->index]->name;

        if (!$this->isTaken($name)) {
            return $this->launch($name, overwrite: false);
        }

        if ($this->memorised === TransferChoice::OverwriteAll) {
            return $this->launch($name, overwrite: true);
        }

        if ($this->memorised === TransferChoice::SkipAll) {
            $this->state = $this->state->withSkipped();
            ++$this->index;

            return $this->next();
        }

        return $this->state = $this->state->colliding($name);
    }

    private function isTaken(string $name): bool
    {
        if ($this->direction->isDownload()) {
            clearstatcache(true, $this->localPath($name));

            return file_exists($this->localPath($name));
        }

        return in_array($name, $this->occupied, true);
    }

    /** Uruchamia potomka dla bieżącego pliku pod wskazaną nazwą docelową. */
    private function launch(string $targetName, bool $overwrite): RemoteTransferState
    {
        $item = $this->queue[$this->index];
        $host = $this->host;

        if ($host === null) {
            return $this->state = $this->state->failed('module.ssh.transfer.failed');
        }

        try {
            $command = $this->commandFor($host, $item, $targetName, $overwrite);
        } catch (InvalidRemotePathException) {
            // Port nie rzuca przez granicę (reguła 8): ścieżka nie do przyjęcia
            // jest tu stanem tak samo zwykłym, jak brak prawa zapisu.
            return $this->state = $this->state->failed('module.ssh.transfer.badPath', ['name' => $targetName]);
        }

        $this->lastSize = -1;
        $this->lastGrowthAt = microtime(true);
        $this->overwrites = $overwrite;
        $this->state = $this->state->working($item->name);
        $this->handle = $this->processes()->start($command, self::TIMEOUT_SECONDS);

        return $this->state;
    }

    /** @throws InvalidRemotePathException */
    private function commandFor(
        HostProfile $host,
        RemoteTransferItem $item,
        string $targetName,
        bool $overwrite,
    ): string {
        $socket = ControlSocket::pathFor($host);

        if ($this->direction->isDownload()) {
            $this->temporary = $this->localPath(self::temporaryName($targetName));

            return SftpCommand::download($host, RemotePath::of($item->path), $this->temporary, $socket);
        }

        $directory = RemotePath::of($this->target);
        $temporary = $directory->child(self::temporaryName($targetName));
        $this->temporary = $temporary->value;

        return SftpCommand::upload(
            $host,
            $item->path,
            $temporary,
            $directory->child($targetName),
            $overwrite,
            $socket,
        );
    }

    /**
     * Postęp bieżącego pliku — **wyłącznie przy pobieraniu**, i to samo pytanie
     * rozstrzyga o zastoju.
     *
     * Odczyt jest lokalny i darmowy: plik roboczy rośnie na dysku, więc `stat`
     * mówi o nim dokładnie tyle, ile pasek postępu potrzebuje. Przy wysyłaniu tej
     * odpowiedzi nie ma i pasek liczy tam pliki, nie bajty.
     */
    private function grew(): RemoteTransferState
    {
        if (!$this->direction->isDownload() || $this->temporary === '') {
            return $this->state;
        }

        clearstatcache(true, $this->temporary);
        $size = @filesize($this->temporary);
        $size = $size === false ? 0 : $size;

        if ($size > $this->lastSize) {
            $this->lastSize = $size;
            $this->lastGrowthAt = microtime(true);

            return $this->state = $this->state->withBytes($this->completedBytes + $size);
        }

        if (microtime(true) - $this->lastGrowthAt < self::STALL_SECONDS) {
            return $this->state;
        }

        $handle = $this->handle;
        $this->handle = null;

        if ($handle !== null) {
            $this->processes()->stop($handle);
        }

        return $this->broke('module.ssh.transfer.stalled');
    }

    /**
     * Plik doszedł: zatwierdzenie i następny z kolejki.
     *
     * Zatwierdzenie zdalne stało się już w wsadzie potomka; lokalne pada tutaj
     * i idzie **portem rdzenia** — najpierw zwolnienie nazwy, jeśli użytkownik
     * zgodził się nadpisać, potem zmiana nazwy pliku roboczego.
     */
    private function completed(): RemoteTransferState
    {
        if ($this->direction->isDownload()) {
            $problem = $this->commit($this->queue[$this->index]->name);

            if ($problem !== null) {
                return $this->state = $this->state->failed($problem[0], $problem[1]);
            }
        }

        $this->completedBytes += $this->queue[$this->index]->sizeInBytes;
        $this->temporary = '';
        $this->state = $this->state->withFinished($this->completedBytes);
        ++$this->index;

        return $this->next();
    }

    /**
     * Zamiana nazwy roboczej na docelową po stronie lokalnej.
     *
     * Nazwa docelowa bierze się z pliku roboczego, a nie z pamięci pracy, bo to
     * ona przeżyła zmianę nazwy przez użytkownika — i jest jedynym miejscem,
     * w którym ta odpowiedź została zapisana.
     *
     * @return ?array{string, array<string, string>} klucz powodu z parametrami albo `null`
     */
    private function commit(string $fallbackName): ?array
    {
        $name = self::finalName(basename($this->temporary), $fallbackName);
        $target = $this->localPath($name);

        clearstatcache(true, $target);

        if (file_exists($target) && !$this->overwrites) {
            // Nazwa wolna w chwili pytania bywa zajęta w chwili zatwierdzenia —
            // przy pliku ściąganym przez minutę to nie jest rzadkość. Odmawiamy
            // tak samo, jak `rename -l` po stronie zdalnej: cudzy plik nie ma
            // prawa zniknąć bez odpowiedzi użytkownika.
            $this->sweep();

            return ['module.ssh.transfer.nameTaken', ['name' => $name]];
        }

        try {
            if (file_exists($target)) {
                $this->files()->delete($target);
            }

            $this->files()->rename($this->temporary, $name);
        } catch (FileOperationException $exception) {
            return [$exception->problemKey(), $exception->problemParameters()];
        }

        return null;
    }

    /**
     * Niepowodzenie albo przerwanie: powód do stanu, połówka do sprzątnięcia.
     *
     * @param array<string, string> $parameters
     */
    private function broke(string $problemKey, array $parameters = []): RemoteTransferState
    {
        $this->sweep();

        return $this->state = $this->state->failed($problemKey, $parameters);
    }

    /**
     * Sprzątanie połówki: lokalna znika od razu, zdalna kolejnym potomkiem.
     *
     * Zdalne sprzątanie **nie zatrzymuje niczyjej odpowiedzi**: praca ma już swój
     * stan, a użytkownik zdanie. Uchwyt czeka na `advance()`, bo takt modułu
     * chodzi niezależnie od tego, czy okno postępu jeszcze stoi.
     */
    private function sweep(): void
    {
        $temporary = $this->temporary;
        $this->temporary = '';

        if ($temporary === '') {
            return;
        }

        if ($this->direction->isDownload()) {
            clearstatcache(true, $temporary);

            if (!file_exists($temporary)) {
                return;
            }

            try {
                $this->files()->delete($temporary);
            } catch (FileOperationException) {
                // Plik roboczy, którego nie da się usunąć, jest przykrością —
                // ale nie powodem, żeby zamiast zdania o przerwaniu pokazać
                // zdanie o sprzątaniu.
            }

            return;
        }

        $host = $this->host;

        if ($host === null) {
            return;
        }

        try {
            $this->cleaning = $this->processes()->start(
                SftpCommand::remove($host, RemotePath::of($temporary), ControlSocket::pathFor($host)),
                self::TIMEOUT_SECONDS,
            );
        } catch (InvalidRemotePathException) {
            $this->cleaning = null;
        }
    }

    /** Dogląda sprzątania — jedyna praca tej klasy, która nikogo nie obchodzi poza nią samą. */
    private function pollCleaning(): void
    {
        if ($this->cleaning === null) {
            return;
        }

        if ($this->processes()->poll($this->cleaning)->stage === BackgroundStage::Running) {
            return;
        }

        $this->cleaning = null;
    }

    /** Nazwa robocza: kropka z przodu chowa ją przed listą, przyrostek mówi, czyja jest. */
    private static function temporaryName(string $name): string
    {
        return self::TEMPORARY_PREFIX . $name . self::TEMPORARY_SUFFIX;
    }

    /** Odwrotność powyższej — z nazwą zapasową na wypadek pliku nazwanego inaczej. */
    private static function finalName(string $temporary, string $fallback): string
    {
        $prefix = strlen(self::TEMPORARY_PREFIX);
        $suffix = strlen(self::TEMPORARY_SUFFIX);

        if (!str_starts_with($temporary, self::TEMPORARY_PREFIX) || !str_ends_with($temporary, self::TEMPORARY_SUFFIX)) {
            return $fallback;
        }

        $name = substr($temporary, $prefix, strlen($temporary) - $prefix - $suffix);

        return $name === '' ? $fallback : $name;
    }

    private function localPath(string $name): string
    {
        return rtrim($this->target, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
    }

    private function files(): FileOperationsPort
    {
        return $this->files ??= FileOperationsService::getInstance();
    }

    private function processes(): BackgroundProcessPort
    {
        return $this->processes ??= BackgroundProcessService::getInstance();
    }
}
