<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Presentation;

use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleSettingsTab;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Application\Module\ProvidesCommands;
use LightManager\Application\Module\ProvidesSettingsTab;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Module\FileInfo\Presentation\Command\JumpCommand;
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

    public function __construct(
        private readonly FileInfoScreen $screen,
        private readonly JumpCommand $jump,
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

    public function commands(): array
    {
        return [$this->jump];
    }

    public function screen(): ScreenInterface
    {
        return $this->screen;
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
            'module.' . FileInfoSettings::ID . '.help.jump',
        ];
    }
}
