<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Presentation\Ui\FocusableInterface;
use LightManager\Presentation\Ui\KeyBinding;

/**
 * Przycisk: etykieta, którą `Enter` zamienia w czynność.
 *
 * Pierwszy komponent aplikacji, który **coś robi**, a nie tylko pokazuje.
 * Czynność przychodzi z zewnątrz jako wywoływalny obiekt — przycisk nie wie,
 * co uruchamia, i dzięki temu nie musi znać ani ustawień, ani plików.
 *
 * Rysuje się jak pozycja listy, nie jak prostokąt z obwódką: w oknie wysokim na
 * trzydzieści wierszy obwódka wokół jednej etykiety kosztowałaby dwa wiersze,
 * a nie niosłaby żadnej informacji, której nie niesie pasek kursora.
 */
final class Button implements FocusableInterface
{
    /** @param callable(): void $action */
    public function __construct(
        private readonly string $label,
        private readonly mixed $action,
        private readonly string $descriptionKey,
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
            '',
            $this->selected ? Role::SelectionText : Role::Accent,
        ))->draw($line) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    public function bindings(): array
    {
        return [KeyBinding::of([Key::Enter], $this->descriptionKey)];
    }

    public function handle(KeyPress $key): bool
    {
        if (!$this->selected || $key->key !== Key::Enter) {
            return false;
        }

        ($this->action)();

        return true;
    }
}
