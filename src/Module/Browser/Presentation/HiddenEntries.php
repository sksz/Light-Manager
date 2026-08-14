<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation;

use LightManager\Application\UseCase\ChangeModuleSettingUseCase;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Application\UseCase\ToggleHiddenEntriesUseCase;
use LightManager\Presentation\Cli\LoopState;

/**
 * Przełączenie widoczności wpisów ukrytych — czynność, do której prowadzą **dwa
 * wejścia** (krok 32).
 *
 * Klasa powstała, bo wejść zrobiło się drugie: do kropki na liście doszła
 * komenda `browser.hidden`, a czynność jest na tyle nieoczywista, że
 * przepisanie jej drugi raz byłoby przepisaniem także jej pułapki. Pułapka jest
 * jedna i widać ją w kolejności: **odczyt obu katalogów idzie przed zapisem
 * konfiguracji**, bo nieudany odczyt rzuca wyjątek — i wtedy ustawienie zostaje
 * takie, jakie było, zamiast rozjechać się z tym, co widać na liście.
 *
 * Wpisy ukryte są ustawieniem **modułu**, więc dotyczą obu paneli naraz;
 * z drzewem nie ma tu nic do roboty, bo `BrowserTree` sam zauważa zmianę
 * widoczności i porzuca zapamiętane gałęzie (krok 31).
 *
 * Klasa leży w warstwie `Presentation` modułu, a nie w `Application`, bo zna
 * `BrowserPanes` i `LoopState` — dokładnie z tego samego powodu, dla którego
 * leżą tam komendy modułu.
 */
final class HiddenEntries
{
    public function __construct(
        private readonly BrowserPanes $panes,
        private readonly ToggleHiddenEntriesUseCase $toggle,
        private readonly ChangeModuleSettingUseCase $changeSetting,
        private readonly LoopState $state,
    ) {
    }

    /**
     * @return ?Message komunikat o zmienionym ustawieniu — ten sam, który
     *                  wypisuje zakładka ustawień
     *
     * @throws \LightManager\Module\Browser\Domain\Exception\DirectoryNotReadableException
     *                                                                                     gdy któregoś
     *                                                                                     z katalogów nie
     *                                                                                     da się odczytać
     *                                                                                     ponownie
     */
    public function flip(): ?Message
    {
        $show = !$this->panes->focused()->showsHiddenEntries();

        foreach ($this->panes->all() as $pane) {
            $pane->enter($this->toggle->execute($pane->directory(), $show));
        }

        [$settings, $message] = $this->changeSetting->shift(
            $this->state->settings(),
            BrowserSettings::ID,
            BrowserSettings::declaration(),
            1,
        );

        $this->state->applySettings($settings);

        return $message;
    }
}
