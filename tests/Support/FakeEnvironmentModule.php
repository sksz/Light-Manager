<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Application\Module\RequiresEnvironment;

/**
 * Moduł, który bywa niedostępny — atrapa zdolności `RequiresEnvironment`
 * (krok 48).
 *
 * Istnieje osobno od `FakeModule`, a nie jako jego parametr, i to jest celowe:
 * zdolność deklaruje się **osobno**, jak `NeedsTick`, więc moduł jej
 * niedeklarujący nie ma o środowisku nic mówić. Atrapa łącząca oba przypadki
 * sprawdzałaby rejestr w kształcie, którego kontrakt nie przewiduje.
 *
 * Liczy przy tym, ile razy ją zapytano — bo jedna z reguł tej zdolności brzmi
 * „odpowiedź pada raz na uruchomienie", a moduł wyłączony nie ma być pytany
 * w ogóle.
 */
final class FakeEnvironmentModule implements ModuleInterface, RequiresEnvironment
{
    public int $asked = 0;

    public function __construct(
        private readonly string $id,
        private readonly ?string $reason = null,
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

    public function unavailableReason(): ?string
    {
        ++$this->asked;

        return $this->reason;
    }
}
