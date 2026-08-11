<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Presentation\Component;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Rect;
use LightManager\Module\FileInfo\Presentation\FileInfoState;
use LightManager\Presentation\Ui\Component\ImageBox;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Prawy panel opisu: miniatura opisywanego pliku albo zdanie, dlaczego jej nie
 * ma.
 *
 * Miniatura liczy się **dopiero przy rysowaniu**, tak samo jak w przeglądarce od
 * kroku 21: ekran pytany o strefy przed podziałem okna nie wie jeszcze, czy prawy
 * panel w ogóle powstanie, a odczyt nagłówka pliku, którego nikt nie zobaczy,
 * jest wejściem-wyjściem za darmo.
 *
 * Komponent leży w katalogu modułu, bo zna jego stan — a `ImageBox`, którego
 * używa, należy do rdzenia i jest tym, co oba moduły z podglądem mają wspólnego.
 */
final class PreviewPane implements ComponentInterface
{
    public function __construct(
        private readonly FileInfoState $state,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $preview = $this->state->preview();

        if ($preview === null) {
            return (new Label($this->translator->translate('module.file-info.preview.none')))->draw($bounds);
        }

        return (new ImageBox($preview->path, $preview->caption))->draw($bounds);
    }
}
