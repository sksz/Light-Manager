<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Presentation;

use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Port\SettingsPort;
use LightManager\Domain\ValueObject\Preview;
use LightManager\Module\FileInfo\Application\Dto\ChecksumState;
use LightManager\Module\FileInfo\Application\Dto\EntryDescription;
use LightManager\Module\FileInfo\Application\Dto\EntryKind;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Module\FileInfo\Application\Port\ChecksumPort;
use LightManager\Module\FileInfo\Application\UseCase\InspectSelectedEntryUseCase;
use LightManager\Module\FileInfo\Application\UseCase\PreviewEntryUseCase;

/**
 * Opisywany wpis i praca, która nad nim trwa.
 *
 * Klasa powstała z tego samego powodu, co `BrowserState` w kroku 21: **stan
 * przeżywający klatkę ma jedno miejsce**, a ekran zostaje przy rysowaniu
 * i klawiszach. Bez niej ekran robiłby cztery rzeczy naraz — rysował, obsługiwał
 * klawisze, składał opis i prowadził liczenie sumy kontrolnej — a test tego
 * ostatniego musiałby przechodzić przez rysowanie klatki.
 *
 * **Suma kontrolna liczy się po kawałku na klatkę** i to ta klasa pilnuje trzech
 * rzeczy, bez których byłaby wyciekiem, a nie funkcją:
 *
 * 1. zmiana zaznaczenia **przerywa** liczenie poprzedniego pliku,
 * 2. zamknięcie ekranu (`reset()`) też,
 * 3. liczenie w ogóle nie zaczyna się dla pliku większego od limitu — i mówi
 *    dlaczego, zamiast milczeć.
 */
final class FileInfoState
{
    /**
     * Ile bajtów czytamy w jednej klatce.
     *
     * Cztery mebibajty to około 3 ms na typowym dysku — mniej niż jedna dziesiąta
     * budżetu taktu, a przy tym 120 MB/s, więc suma pliku stumegabajtowego
     * kończy się w niecałą sekundę. Liczba jest kompromisem między „nie zatnij
     * klatki” a „nie licz do wieczora” i jedno i drugie ma znaczenie.
     */
    private const CHUNK_BYTES = 4 * 1024 * 1024;

    /** Ścieżka, dla której policzono opis; `null` — jeszcze niczego nie liczono. */
    private ?string $path = null;

    private ?EntryDescription $description = null;

    /** Ostatni kontekst sesji — potrzebny górnemu pasowi klatki i podglądowi. */
    private ModuleContext $context;

    public function __construct(
        private readonly InspectSelectedEntryUseCase $inspect,
        private readonly PreviewEntryUseCase $previews,
        private readonly ChecksumPort $checksums,
        private readonly SettingsPort $settings,
    ) {
        $this->context = new ModuleContext();
    }

    public function context(): ModuleContext
    {
        return $this->context;
    }

    public function description(): ?EntryDescription
    {
        return $this->description;
    }

    public function checksum(): ChecksumState
    {
        return $this->checksums->state();
    }

    /**
     * Miniatura opisywanego pliku albo `null`.
     *
     * Liczy się **leniwie**, przy pytaniu, bo pyta o nią prawy panel podziału,
     * a ten powstaje tylko w dostatecznie szerokim oknie.
     */
    public function preview(): ?Preview
    {
        $description = $this->description;

        return $description === null
            ? null
            : $this->previews->execute($this->path, $description->sizeInBytes);
    }

    /**
     * Nowy kontekst sesji. Opis liczy się **wyłącznie przy zmianie ścieżki** —
     * kontekst przychodzi co klatkę, a za opisem stoi proces potomny (`file`).
     */
    public function useContext(ModuleContext $context): void
    {
        $this->context = $context;
        $path = $context->selectionPath();

        if ($path === $this->path && $this->path !== null) {
            return;
        }

        // Zmiana zaznaczenia przerywa liczenie poprzedniego pliku. Bez tego
        // przewinięcie listy zostawiałoby za sobą otwarte uchwyty do plików,
        // z których każdy nadal byłby czytany po kawałku w każdej klatce.
        $this->checksums->stop();
        $this->path = $path;
        $this->description = $this->inspect->execute($context);
    }

    /** Kawałek pracy przypadający na tę klatkę. Gdy nic nie trwa — nie robi nic. */
    public function advance(): void
    {
        if ($this->checksums->state()->isRunning()) {
            $this->checksums->advance(self::CHUNK_BYTES);
        }
    }

    /**
     * Klawisz „policz sumę kontrolną”.
     *
     * Odmowy są trzy i każda mówi **dlaczego**: wyłączone w ustawieniach, wpis
     * nie jest zwykłym plikiem, plik przekracza limit rozmiaru. Milczące
     * nierozpoczęcie pracy byłoby najgorszą z możliwych odpowiedzi — użytkownik
     * nacisnął klawisz i ma prawo wiedzieć, co się stało.
     *
     * @return string|null klucz katalogu z powodem odmowy; `null` — praca ruszyła
     */
    public function startChecksum(): ?string
    {
        $settings = $this->settings->current();
        $description = $this->description;

        if (!FileInfoSettings::checksum($settings)) {
            return 'module.file-info.checksum.disabled';
        }

        if ($description === null || $description->kind !== EntryKind::File) {
            return 'module.file-info.checksum.notAFile';
        }

        if ($description->sizeInBytes > FileInfoSettings::checksumLimitBytes($settings)) {
            return 'module.file-info.checksum.tooLarge';
        }

        $path = $this->path;

        if ($path === null) {
            return 'module.file-info.checksum.notAFile';
        }

        $this->checksums->begin($path);

        return null;
    }

    /**
     * Sprzątanie: przerywa pracę i zapomina opis.
     *
     * Wołane przy każdym otwarciu ekranu, a nie tylko przy zamknięciu — powód
     * jest ten sam, co przed krokiem 25: zmiana ustawień (limit czasu, argumenty,
     * format czasu) ma być widoczna od razu, a nie dopiero po przejściu na inny
     * plik.
     */
    public function reset(): void
    {
        $this->checksums->stop();
        $this->path = null;
        $this->description = null;
    }
}
