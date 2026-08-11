<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Presentation;

use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleSettingsTab;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Application\Module\ProvidesCommands;
use LightManager\Application\Module\ProvidesSettingsTab;
use LightManager\Application\Port\ImagePreviewPort;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Module\FileInfo\Application\Port\ChecksumPort;
use LightManager\Module\FileInfo\Application\Port\FileInspectorPort;
use LightManager\Module\FileInfo\Application\Port\FileStatPort;
use LightManager\Module\FileInfo\Application\UseCase\InspectSelectedEntryUseCase;
use LightManager\Module\FileInfo\Application\UseCase\PreviewEntryUseCase;
use LightManager\Module\FileInfo\Infrastructure\ChecksumService;
use LightManager\Module\FileInfo\Infrastructure\FileInspectorService;
use LightManager\Module\FileInfo\Infrastructure\FileStatService;
use LightManager\Module\FileInfo\Presentation\Component\PreviewPane;
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
    ProvidesScreen,
    ProvidesHelpTab
{
    /** „Detail information” — litera `d` jest wolna, bo `0x04` nie znaczy w trybie surowym EOF-u. */
    private const SHORTCUT = 'd';

    private ?FileInfoScreen $assembled = null;

    /**
     * @param FileInspectorPort|null $inspector wstrzyknięcie istnieje dla testów,
     *                                          które składają moduł bez uruchamiania
     *                                          procesu potomnego; `null` znaczy
     *                                          „prawdziwe polecenie `file`”
     */
    public function __construct(
        private readonly TranslatorPort $translator,
        private readonly SettingsPort $settings,
        private readonly ImagePreviewPort $images,
        private readonly ?FileInspectorPort $inspector = null,
        private readonly ?FileStatPort $stats = null,
        private readonly ?ChecksumPort $checksums = null,
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
     * Dziś pusta — i deklaracja zostaje mimo to.
     *
     * `file-info.jump` przeniosła się w kroku 21 do modułu przeglądarki, bo po
     * wyprowadzeniu nawigacji tylko ona umie zmienić katalog. Zdolność zostaje,
     * bo moduł ma się rozrastać (krok 25: pełny obraz stanu pliku), a rejestr
     * komend znosi pustą listę bez szkody.
     */
    public function commands(): array
    {
        return [];
    }

    public function screen(): ScreenInterface
    {
        return $this->assembled ??= $this->assemble();
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
        $state = new FileInfoState(
            new InspectSelectedEntryUseCase(
                $this->inspector ?? FileInspectorService::getInstance(),
                $this->stats ?? FileStatService::getInstance(),
                $this->settings,
                $this->translator,
            ),
            new PreviewEntryUseCase($this->images, $this->translator),
            $this->checksums ?? ChecksumService::getInstance(),
            $this->settings,
        );

        return new FileInfoScreen($state, new PreviewPane($state, $this->translator), $this->translator);
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
        ];
    }
}
