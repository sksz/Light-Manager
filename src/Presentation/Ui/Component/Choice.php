<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Pole wyboru opcji: etykieta po lewej, wybrana wartość po prawej.
 *
 * Komponent pokazuje wartość, ale jej nie zmienia — zmiana jest czynnością
 * aplikacji (`ChangeSettingUseCase`), nie rysowaniem. Gdyby pole trzymało
 * wartość u siebie, ekran ustawień miałby dwa źródła prawdy: pole i plik
 * konfiguracyjny.
 */
final class Choice implements ComponentInterface
{
    public function __construct(
        private readonly string $label,
        private readonly string $value,
        private readonly bool $selected = false,
    ) {
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $line = $bounds->line(0);
        $primitives = $this->selected ? Highlight::under($line) : [];

        foreach ((new Label(
            $this->label,
            $this->value,
            $this->selected ? Role::SelectionText : Role::Text,
        ))->draw($line) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }
}
