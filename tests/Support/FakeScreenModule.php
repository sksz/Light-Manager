<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Application\Ui\Rect;
use LightManager\Presentation\Ui\Module\ProvidesScreen;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScreenZone;

/**
 * Moduł z ekranem — na potrzeby testów wyboru dna stosu.
 *
 * `FakeModule` deklaruje samą tożsamość i wystarcza rejestrowi; wybór dna pyta za
 * to o `ProvidesScreen`, więc potrzebuje modułu, który ekran naprawdę wnosi.
 * Ekran jest tu najprostszy z możliwych: bez stref, bez klawiszy, z samym
 * identyfikatorem — bo tylko po nim test rozpoznaje, kto stanął na dnie.
 */
final class FakeScreenModule implements ModuleInterface, ProvidesScreen, ScreenInterface
{
    public function __construct(
        private readonly string $id,
        private readonly ?ModuleShortcut $shortcut = null,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function nameKey(): string
    {
        return 'module.' . $this->id . '.name';
    }

    public function descriptionKey(): string
    {
        return 'module.' . $this->id . '.description';
    }

    public function shortcut(): ?ModuleShortcut
    {
        return $this->shortcut;
    }

    public function translations(): ?string
    {
        return null;
    }

    public function screen(): ScreenInterface
    {
        return $this;
    }

    public function labelKey(): string
    {
        return 'module.' . $this->id . '.name';
    }

    public function header(): ?ScreenZone
    {
        return null;
    }

    public function preview(): ?ScreenZone
    {
        return null;
    }

    public function bindings(): array
    {
        return [];
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        return ScreenOutcome::stay();
    }

    public function draw(Rect $bounds): array
    {
        return [];
    }
}
