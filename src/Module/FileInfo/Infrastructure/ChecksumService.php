<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Infrastructure;

use HashContext;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\FileInfo\Application\Dto\ChecksumState;
use LightManager\Module\FileInfo\Application\Port\ChecksumPort;

/**
 * `sha256` liczona własnym odczytem, po kawałku na klatkę.
 *
 * Wybór między tym a `sha256sum` w procesie potomnym padł na starcie kroku 25
 * i miał jeden powód: **postęp**. Polecenie zewnętrzne nie mówi o sobie nic, aż
 * skończy, więc pasek postępu chodziłby w trybie „nie wiadomo ile jeszcze”
 * akurat dla źródła, które postęp zna z natury — bajty przeczytane wobec
 * rozmiaru pliku.
 *
 * Drugą korzyść widać dopiero przy sprzątaniu: **nie ma procesu, więc nie ma
 * czego osierocić.** Przerwanie to zamknięcie uchwytu, a uchwyt zamyka się sam,
 * gdy proces PHP kończy pracę — w odróżnieniu od potomka, który przeżyłby wyjście
 * z aplikacji.
 *
 * Cena jest jedna i trzeba ją znać: odczyt dzieje się **w klatce**, więc kawałek
 * musi być na tyle mały, żeby zmieścił się w budżecie taktu, i na tyle duży, żeby
 * suma dla dużego pliku nie liczyła się w nieskończoność. Rozmiar kawałka podaje
 * wołający, bo tylko on wie, ile czasu klatki mu zostało.
 */
final class ChecksumService extends AbstractSingleton implements ChecksumPort
{
    private const ALGORITHM = 'sha256';

    private ?HashContext $context = null;

    /** @var resource|null */
    private $handle = null;

    private int $sizeInBytes = 0;

    private int $readBytes = 0;

    private ChecksumState $state;

    protected function __construct()
    {
        $this->state = ChecksumState::idle();
    }

    public function begin(string $path): ChecksumState
    {
        $this->stop();

        $size = @filesize($path);
        $handle = @fopen($path, 'rb');

        if ($handle === false || $size === false) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            return $this->state = ChecksumState::failed('module.file-info.checksum.unreadable');
        }

        $this->handle = $handle;
        $this->context = hash_init(self::ALGORITHM);
        $this->sizeInBytes = $size;
        $this->readBytes = 0;

        // Plik pusty jest gotowy od razu: `advance()` nie miałoby czego czytać,
        // a suma pustego ciągu jest przecież prawidłową odpowiedzią.
        return $size === 0 ? $this->finish() : $this->state = ChecksumState::running(0.0);
    }

    public function advance(int $bytes): ChecksumState
    {
        if (!$this->state->isRunning() || $this->context === null || !is_resource($this->handle)) {
            return $this->state;
        }

        $read = hash_update_stream($this->context, $this->handle, max(1, $bytes));
        $this->readBytes += $read;

        if ($read === 0 || $this->readBytes >= $this->sizeInBytes || feof($this->handle)) {
            return $this->finish();
        }

        return $this->state = ChecksumState::running($this->readBytes / max(1, $this->sizeInBytes));
    }

    public function state(): ChecksumState
    {
        return $this->state;
    }

    public function stop(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }

        $this->handle = null;
        $this->context = null;
        $this->sizeInBytes = 0;
        $this->readBytes = 0;
        $this->state = ChecksumState::idle();
    }

    private function finish(): ChecksumState
    {
        $digest = $this->context === null ? null : hash_final($this->context);

        if (is_resource($this->handle)) {
            fclose($this->handle);
        }

        $this->handle = null;
        $this->context = null;

        return $this->state = $digest === null
            ? ChecksumState::failed('module.file-info.checksum.unreadable')
            : ChecksumState::done($digest);
    }
}
