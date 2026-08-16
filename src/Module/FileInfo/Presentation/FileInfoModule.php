<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Presentation;

use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleSettingsTab;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Application\Module\ProvidesCommands;
use LightManager\Application\Module\ProvidesQueries;
use LightManager\Application\Module\ProvidesSettingsTab;
use LightManager\Application\Port\BackgroundProcessPort;
use LightManager\Application\Port\ImagePreviewPort;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\UseCase\ChangeModuleSettingUseCase;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Module\FileInfo\Application\Port\ChecksumPort;
use LightManager\Module\FileInfo\Application\Port\FileInspectorPort;
use LightManager\Module\FileInfo\Application\Port\FileStatPort;
use LightManager\Module\FileInfo\Application\Port\TextPreviewPort;
use LightManager\Module\FileInfo\Application\UseCase\InspectSelectedEntryUseCase;
use LightManager\Module\FileInfo\Application\UseCase\MeasureDiskUsageUseCase;
use LightManager\Module\FileInfo\Application\UseCase\PreviewEntryUseCase;
use LightManager\Module\FileInfo\Application\UseCase\PreviewTextUseCase;
use LightManager\Module\FileInfo\Infrastructure\ChecksumService;
use LightManager\Module\FileInfo\Infrastructure\FileInspectorService;
use LightManager\Module\FileInfo\Infrastructure\FileStatService;
use LightManager\Module\FileInfo\Infrastructure\TextPreviewService;
use LightManager\Module\FileInfo\Presentation\Command\ShowCommand;
use LightManager\Module\FileInfo\Presentation\Component\PreviewPane;
use LightManager\Module\FileInfo\Presentation\Query\ChecksumQuery;
use LightManager\Module\FileInfo\Presentation\Query\DescriptionQuery;
use LightManager\Module\FileInfo\Presentation\Query\DiskUsageQuery;
use LightManager\Module\FileInfo\Presentation\Query\PreviewQuery;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Module\ProvidesHelpTab;
use LightManager\Presentation\Ui\Module\ProvidesScreen;
use LightManager\Presentation\Ui\ScreenInterface;

/**
 * Pierwszy moduł projektu — dowód, że kontrakt wystarcza.
 *
 * Opis zaznaczonego pliku wyprowadził się z rdzenia w całości: przypadek użycia,
 * port, usługa i DTO leżą w `src/Module/FileInfo/`, a w rdzeniu nie została po
 * nich ani jedna klasa. Moduł deklaruje **wszystkie pięć punktów zaczepienia**
 * naraz — własne okno wraz ze skrótem, zakładkę ustawień, zakładkę pomocy,
 * własne napisy i własną komendę — więc migracja przeciera każdą ścieżkę
 * kontraktu jednocześnie, zamiast zostawiać cztery z nich na domysł.
 *
 * Klasa modułu leży w jego warstwie `Presentation`, bo implementuje zdolności
 * wymieniające typy z `Presentation/Ui`. Jest **zwykłym obiektem**, tworzonym
 * `new`-em w `Bootstrap` z wstrzykniętymi zależnościami: nie jest Singletonem
 * i nie woła `getInstance()`. Singletonami pozostają usługi w jej własnej
 * warstwie `Infrastructure`, na dotychczasowych zasadach.
 *
 * **Od kroku 25 moduł składa się sam i leniwie**, jak `BrowserModule` po kroku
 * 21 — to jest spłata długu zapisanego wprost w dzienniku tamtego kroku
 * („do wyrównania przy okazji rozbudowy”). `Bootstrap` widzi odtąd jedną klasę
 * zamiast czterech, a leniwość ma tę samą twardą przyczynę, co w przeglądarce:
 * napisy modułu wchodzą do katalogu **po** zbudowaniu rejestru, więc moduł
 * składany zachłannie mógłby wypisać użytkownikowi surowy klucz.
 */
final class FileInfoModule implements
    ModuleInterface,
    ProvidesSettingsTab,
    ProvidesCommands,
    ProvidesQueries,
    ProvidesScreen,
    ProvidesHelpTab
{
    /** „Detail information” — litera `d` jest wolna, bo `0x04` nie znaczy w trybie surowym EOF-u. */
    private const SHORTCUT = 'd';

    private ?FileInfoScreen $assembled = null;

    private ?FileInfoState $inner = null;

    private ?FileInfoQueries $reader = null;

    /**
     * `BackgroundProcessPort` stoi wśród zależności **obowiązkowych**, a nie
     * wśród wstrzyknięć testowych poniżej, i jest to różnica z rozmysłu: to port
     * rdzenia, taki sam jak podgląd obrazów, więc podaje go `Bootstrap` w jedynej
     * linii, którą rdzeń o tym module wie. Domyślnej wartości nie ma także
     * dlatego, że test, który zapomniałby podać atrapę, uruchomiłby prawdziwe
     * `du` — a test uruchamiający procesy jest testem, który kiedyś zawiesi
     * potok ciągłej integracji.
     *
     * @param FileInspectorPort|null $inspector wstrzyknięcie istnieje dla testów,
     *                                          które składają moduł bez uruchamiania
     *                                          procesu potomnego; `null` znaczy
     *                                          „prawdziwe polecenie `file`”
     */
    public function __construct(
        /**
         * Stan pętli — potrzebny **wyłącznie** po to, by `Alt`+`Z` zmieniało tę
         * samą pozycję ustawień, którą pokazuje zakładka modułu (poprawka
         * z 2026-08-12). Ekran ustawień czyta konfigurację stąd, więc moduł
         * zapisujący ją samym portem rozjechałby się z zakładką. Ta sama
         * zależność i z tego samego powodu stoi w `BrowserModule` od kroku 21.
         */
        private readonly LoopState $state,
        private readonly TranslatorPort $translator,
        private readonly SettingsPort $settings,
        private readonly ImagePreviewPort $images,
        private readonly BackgroundProcessPort $processes,
        private readonly ?FileInspectorPort $inspector = null,
        private readonly ?FileStatPort $stats = null,
        private readonly ?ChecksumPort $checksums = null,
        private readonly ?TextPreviewPort $texts = null,
    ) {
    }

    public function id(): string
    {
        return FileInfoSettings::ID;
    }

    public function nameKey(): string
    {
        return 'module.' . FileInfoSettings::ID . '.name';
    }

    public function descriptionKey(): string
    {
        return 'module.' . FileInfoSettings::ID . '.description';
    }

    public function shortcut(): ModuleShortcut
    {
        return new ModuleShortcut(self::SHORTCUT);
    }

    /** Napisy modułu leżą obok jego kodu, a nie w katalogu rdzenia. */
    public function translations(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lang';
    }

    public function settingsTab(): ModuleSettingsTab
    {
        return new ModuleSettingsTab($this->nameKey(), FileInfoSettings::declarations());
    }

    /**
     * Pusta była do kroku 32 — `file-info.jump` przeniosła się w kroku 21 do
     * modułu przeglądarki, bo po wyprowadzeniu nawigacji tylko ona umie zmienić
     * katalog, a zdolność została w zapasie „na wyrost”.
     *
     * Zapas się przydał: `file-info.show` nazywa czynność, którą moduł umiał od
     * kroku 20, ale wyłącznie pod skrótem `Ctrl`+`D`. Komenda **nie dokłada
     * funkcji** — dokłada jej nazwę, bez której menu kontekstowe nie miałoby
     * czego pokazać (krok 32).
     */
    public function commands(): array
    {
        return [new ShowCommand()];
    }

    public function screen(): ScreenInterface
    {
        return $this->assembled ??= $this->assemble();
    }

    /**
     * Cztery źródła danych modułu (krok 53): opis wpisu, miniatura oraz **stan**
     * dwóch prac tłowych.
     *
     * Kwerendy powstają na tym samym stanie, co ekran, i to jest tu warunek
     * poprawności, a nie oszczędność: dwa stany znaczyłyby dwie sumy kontrolne
     * liczone równolegle dla tego samego pliku.
     */
    public function queries(): array
    {
        $state = $this->inner();

        return [
            new DescriptionQuery($state),
            new PreviewQuery($state),
            new ChecksumQuery($state),
            new DiskUsageQuery($state),
        ];
    }

    /**
     * Ekran wraz z całym wnętrzem modułu — przypadki użycia, stan i komponenty.
     *
     * Wszystko powstaje tutaj, a nie w `Bootstrapie`, i to jest cały sens tej
     * metody: rdzeń nie ma prawa poznać ani `FileStatService`, ani `ChecksumPort`,
     * ani tego, że opis pliku w ogóle składa się z sekcji.
     */
    private function assemble(): FileInfoScreen
    {
        $state = $this->inner();

        return new FileInfoScreen(
            $state,
            new PreviewPane($state, $this->reader(), $this->translator),
            $this->reader(),
            $this->translator,
        );
    }

    /** Odczyt własnych danych — przez rejestr kwerend (D92 nr 3). */
    private function reader(): FileInfoQueries
    {
        return $this->reader ??= new FileInfoQueries($this->state->queries());
    }

    /**
     * Stan modułu — **jeden na moduł**, wspólny dla ekranu i dla kwerend.
     *
     * Powstaje leniwie z tego samego powodu, co reszta modułu: napisy wchodzą do
     * katalogu po zbudowaniu rejestru modułów.
     */
    private function inner(): FileInfoState
    {
        return $this->inner ??= new FileInfoState(
            new InspectSelectedEntryUseCase(
                $this->inspector ?? FileInspectorService::getInstance(),
                $this->stats ?? FileStatService::getInstance(),
                $this->settings,
                $this->translator,
            ),
            new PreviewEntryUseCase($this->images, $this->translator),
            $this->checksums ?? ChecksumService::getInstance(),
            new MeasureDiskUsageUseCase($this->processes),
            $this->settings,
            new PreviewTextUseCase(
                $this->texts ?? TextPreviewService::getInstance(),
                $this->settings,
            ),
            new ChangeModuleSettingUseCase($this->settings, $this->translator),
            $this->state,
        );
    }

    /**
     * Część własna zakładki pomocy — to, czego z deklaracji wyczytać się nie da.
     *
     * Skrótu ani pozycji ustawień tu nie ma i być nie powinno: rdzeń wypisuje je
     * sam, z deklaracji, więc przepisane ręcznie skłamałyby przy pierwszej
     * zmianie.
     */
    public function helpKeys(): array
    {
        return [
            'module.' . FileInfoSettings::ID . '.help.enter',
            'module.' . FileInfoSettings::ID . '.help.sections',
            'module.' . FileInfoSettings::ID . '.help.preview',
        ];
    }
}
