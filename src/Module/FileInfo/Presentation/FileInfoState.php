<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Presentation;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Port\SettingsPort;
use LightManager\Domain\ValueObject\Preview;
use LightManager\Module\FileInfo\Application\Dto\ChecksumState;
use LightManager\Module\FileInfo\Application\Dto\DiskUsageState;
use LightManager\Module\FileInfo\Application\Dto\EntryDescription;
use LightManager\Module\FileInfo\Application\Dto\EntryKind;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Module\FileInfo\Application\Port\ChecksumPort;
use LightManager\Module\FileInfo\Application\UseCase\InspectSelectedEntryUseCase;
use LightManager\Module\FileInfo\Application\UseCase\MeasureDiskUsageUseCase;
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
 *
 * Od kroku 26 dochodzi **druga praca na tych samych zasadach**: zajętość katalogu
 * liczona poleceniem `du` w procesie tłowym. Trzy punkty powyżej obowiązują ją co
 * do joty, a stawka jest wyższa o rząd wielkości — zapomniane przerwanie zostawia
 * po sobie nie otwarty uchwyt do pliku, tylko **działający proces**. Dlatego
 * uchwyt pracy trzyma ta klasa, a nie przypadek użycia: właścicielem jest ten,
 * kto wie, kiedy zaznaczenie się zmienia i kiedy ekran się zamyka.
 *
 * Obie prace nie mogą trwać naraz i nie jest to przypadek: sumę liczymy wyłącznie
 * dla zwykłych plików, zajętość wyłącznie dla katalogów. Pasek postępu ma więc
 * zawsze najwyżej jednego nadawcę.
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

    /** Uchwyt trwającego pomiaru zajętości; `null` — nic nie zamówiono. */
    private ?BackgroundHandle $diskUsageHandle = null;

    private DiskUsageState $diskUsage;

    public function __construct(
        private readonly InspectSelectedEntryUseCase $inspect,
        private readonly PreviewEntryUseCase $previews,
        private readonly ChecksumPort $checksums,
        private readonly MeasureDiskUsageUseCase $diskUsageJob,
        private readonly SettingsPort $settings,
    ) {
        $this->context = new ModuleContext();
        $this->diskUsage = DiskUsageState::idle();
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

    public function diskUsage(): DiskUsageState
    {
        return $this->diskUsage;
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

        // Zmiana zaznaczenia przerywa obie prace nad poprzednim wpisem. Bez tego
        // przewinięcie listy zostawiałoby za sobą otwarte uchwyty do plików,
        // z których każdy nadal byłby czytany po kawałku w każdej klatce — a od
        // kroku 26 także **działające procesy** `du`, po jednym na każdy katalog,
        // przez który kursor przeszedł.
        $this->checksums->stop();
        $this->stopDiskUsage();
        $this->path = $path;
        $this->description = $this->inspect->execute($context);
    }

    /**
     * Kawałek pracy przypadający na tę klatkę. Gdy nic nie trwa — nie robi nic.
     *
     * Dwa rodzaje pracy i dwa różne czasowniki, choć wołane w tym samym miejscu:
     * sumę kontrolną **posuwamy** (czytamy kolejne bajty), a pomiar zajętości
     * tylko **doglądamy** — pracę wykonuje potomek, a my sprawdzamy, czy już
     * skończył, i opróżniamy jego potoki.
     */
    public function advance(): void
    {
        if ($this->checksums->state()->isRunning()) {
            $this->checksums->advance(self::CHUNK_BYTES);
        }

        if ($this->diskUsageHandle !== null && $this->diskUsage->isRunning()) {
            $this->diskUsage = $this->diskUsageJob->read($this->diskUsageHandle);
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
     * Klawisz „policz zajętość na dysku”.
     *
     * Odmowy są dwie i obie mówią dlaczego — jak przy sumie kontrolnej. Limitu
     * rozmiaru wśród nich nie ma i nie ma go z czego wziąć: przed policzeniem nie
     * wiadomo, jak duże jest drzewo, a to właśnie jest pytanie, które zadajemy.
     * Rolę hamulca gra tu limit **czasu**, nie rozmiaru.
     *
     * Wpis niebędący katalogiem odpada, bo dla zwykłego pliku odpowiedź stoi już
     * w sekcji „Rozmiar”: bloki i-węzła razy 512 to dokładnie zajętość na dysku,
     * policzona z `lstat` bez uruchamiania czegokolwiek. Proces potomny po liczbę,
     * którą użytkownik ma na ekranie, byłby kosztem bez treści.
     *
     * @return string|null klucz katalogu z powodem odmowy; `null` — praca ruszyła
     */
    public function startDiskUsage(): ?string
    {
        $description = $this->description;
        $path = $this->path;

        if (!FileInfoSettings::diskUsage($this->settings->current())) {
            return 'module.file-info.diskUsage.disabled';
        }

        if ($path === null || $description === null || $description->kind !== EntryKind::Directory) {
            return 'module.file-info.diskUsage.notADirectory';
        }

        $this->stopDiskUsage();
        $this->diskUsageHandle = $this->diskUsageJob->begin(
            $path,
            FileInfoSettings::backgroundTimeout($this->settings->current()),
        );
        $this->diskUsage = DiskUsageState::running();

        return null;
    }

    /**
     * Sprzątanie: przerywa obie prace i zapomina opis.
     *
     * Wołane przy każdym otwarciu ekranu, a nie tylko przy zamknięciu — powód
     * jest ten sam, co przed krokiem 25: zmiana ustawień (limit czasu, argumenty,
     * format czasu) ma być widoczna od razu, a nie dopiero po przejściu na inny
     * plik.
     */
    public function reset(): void
    {
        $this->checksums->stop();
        $this->stopDiskUsage();
        $this->path = null;
        $this->description = null;
    }

    /**
     * Przerywa pomiar i zapomina uchwyt.
     *
     * Wołane z trzech miejsc — zmiana zaznaczenia, `reset()` i nowe zamówienie —
     * bo w każdym z nich niedopilnowanie zostawiłoby po sobie proces, o którym
     * nikt już nie pamięta. Wolno je wołać, gdy nic nie trwa.
     */
    private function stopDiskUsage(): void
    {
        if ($this->diskUsageHandle !== null) {
            $this->diskUsageJob->stop($this->diskUsageHandle);
            $this->diskUsageHandle = null;
        }

        $this->diskUsage = DiskUsageState::idle();
    }
}
