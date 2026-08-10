<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\UseCase;

use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Port\SettingsPort;
use LightManager\Module\FileInfo\Application\Dto\EntryDescription;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Module\FileInfo\Application\Port\FileInspectorPort;

/**
 * Opis zaznaczonego pliku — treść ekranu modułu.
 *
 * Do kroku 20 przypadek użycia przyjmował `Directory` i sam wyciągał z niego
 * zaznaczenie. Dziś dostaje `ModuleContext`, czyli **dane pierwotne**: ścieżkę,
 * nazwę i rodzaj. Zmiana nie jest kosmetyczna — to ona sprawia, że moduł nie
 * zna agregatu, który w kroku 21 zejdzie z rdzenia do modułu przeglądarki
 * (D40, P5).
 *
 * Katalogi pomija: opis `file` dla katalogu mówi tylko tyle, że jest katalogiem,
 * a użytkownik widzi to już na liście.
 */
final class InspectSelectedEntryUseCase
{
    public function __construct(
        private readonly FileInspectorPort $inspector,
        private readonly SettingsPort $settings,
    ) {
    }

    public function execute(ModuleContext $context): ?EntryDescription
    {
        $path = $context->selectionPath();

        if ($path === null || $context->kind !== ContextEntryKind::File) {
            return null;
        }

        $settings = $this->settings->current();
        $description = $this->inspector->describe(
            $path,
            FileInfoSettings::timeout($settings),
            FileInfoSettings::arguments($settings),
        );

        return new EntryDescription($context->selection ?? '', explode("\n", $description));
    }
}
