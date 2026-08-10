<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Rect;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Przełącznik dwustanowy — pole wyboru o zamkniętej liście dwóch wartości.
 *
 * Osobny komponent, choć rysuje się jak `Choice`, bo znaczy co innego: wartość
 * ma dokładnie dwa stany i nazwy tych stanów są **napisami interfejsu**, a nie
 * danymi. Ekran, który podaje przełącznikowi „tak” i „nie”, wziął je z katalogu
 * napisów; ekran, który podaje polu wyboru nazwę motywu, wziął ją z konfiguracji.
 */
final class Toggle implements ComponentInterface
{
    public function __construct(
        private readonly string $label,
        private readonly bool $value,
        private readonly string $whenOn,
        private readonly string $whenOff,
        private readonly bool $selected = false,
    ) {
    }

    public function draw(Rect $bounds): array
    {
        return (new Choice(
            $this->label,
            $this->value ? $this->whenOn : $this->whenOff,
            $this->selected,
        ))->draw($bounds);
    }
}
