<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Presentation;

use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleSettingsTab;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Application\Module\ProvidesCommands;
use LightManager\Application\Module\ProvidesSettingsTab;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\Port\AudioPort;
use LightManager\Module\Audio\Application\UseCase\ChangeVolumeUseCase;
use LightManager\Module\Audio\Infrastructure\GlAudioService;
use LightManager\Module\Audio\Infrastructure\SilentAudioService;
use LightManager\Module\Audio\Presentation\Command\MusicCommand;
use LightManager\Module\Audio\Presentation\Command\VolumeCommand;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Module\ProvidesHelpTab;

/**
 * Muzyka jako moduł — **trzeci w projekcie i pierwszy bez ekranu** (krok 36).
 *
 * Krok planowany był jako rozbudowa rdzenia: port w `Application`, usługa
 * w `Infrastructure`, autostart w `Bootstrapie`, cztery klucze ustawień
 * i komendy `core.*`. Rozstrzygnięcie użytkownika ze startu kroku odwróciło to
 * w całości (D70) i było zgodne z regułą 15: **dźwięk jest nową funkcją, więc
 * jest modułem**. Rdzeń kosztuje przez to dokładnie tyle, ile reguła przewiduje
 * — jedną pozycję na liście w `Bootstrapie` — a moduł dostaje za darmo to,
 * o co krok w wersji rdzeniowej musiałby się dopiero postarać: zakładkę ustawień
 * z **pozycją tekstową** na ścieżkę utworu, której ustawienia rdzenia nie mają.
 *
 * Moduł jest sprawdzianem kontraktu z drugiej strony niż krok 21: tamten pytał,
 * czy kontrakt udźwignie **główną funkcję aplikacji**, ten — czy udźwignie moduł,
 * który **nie rysuje niczego**. Odpowiedź: udźwignął bez zmiany, bo `shortcut()`
 * wolno oddać `null`, a zdolności deklaruje się osobno („moduł bez ani jednej
 * zdolności jest legalny”). Ekranu nie ma, skrótu nie ma, a jest zakładka
 * ustawień, zakładka pomocy, dwie komendy i własne napisy.
 *
 * Autostartu **nie ma** i to jest cena wybranego wariantu: kontrakt modułu nie
 * zna cyklu życia, więc nikt modułu przy starcie nie budzi. Muzyka rusza
 * komendą `audio.music`.
 *
 * Moduł składa się leniwie, jak dwa poprzednie: napisy wchodzą do katalogu **po**
 * zbudowaniu rejestru modułów, więc komenda zbudowana zachłannie mogłaby
 * wypisać użytkownikowi surowy klucz.
 */
final class AudioModule implements ModuleInterface, ProvidesSettingsTab, ProvidesCommands, ProvidesHelpTab
{
    /** @var list<\LightManager\Application\Command\CommandInterface>|null */
    private ?array $commands = null;

    /**
     * @param ?AudioPort $audio wstrzyknięcie istnieje dla testów, które nie mają
     *                          prawa uruchomić silnika audio — tak samo, jak testy
     *                          `FileInfo` nie mają prawa uruchomić `du`. `null`
     *                          znaczy „wybierz implementację wedle środowiska”
     */
    public function __construct(
        private readonly LoopState $state,
        private readonly TranslatorPort $translator,
        private readonly SettingsPort $settings,
        private readonly ?AudioPort $audio = null,
    ) {
    }

    public function id(): string
    {
        return AudioSettings::ID;
    }

    public function nameKey(): string
    {
        return 'module.' . AudioSettings::ID . '.name';
    }

    public function descriptionKey(): string
    {
        return 'module.' . AudioSettings::ID . '.description';
    }

    /**
     * Skrótu nie ma, bo nie ma ekranu, który miałby otworzyć.
     *
     * `Ctrl`+litera otwiera **okno modułu** (krok 20), a ten moduł okna nie
     * wnosi; skrót bez ekranu zajmowałby literę w przestrzeni, w której jest ich
     * dwadzieścia kilka, i nie robiłby nic.
     */
    public function shortcut(): ?ModuleShortcut
    {
        return null;
    }

    /** Napisy modułu leżą obok jego kodu, a nie w katalogu rdzenia. */
    public function translations(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lang';
    }

    public function settingsTab(): ModuleSettingsTab
    {
        return new ModuleSettingsTab($this->nameKey(), AudioSettings::declarations());
    }

    public function commands(): array
    {
        return $this->commands ??= $this->assemble();
    }

    /**
     * Część własna zakładki pomocy — to, czego z deklaracji wyczytać się nie da.
     *
     * Trzy zdania i każde odpowiada na pytanie, które użytkownik zada: czym się
     * to włącza (bo skrótu nie ma), skąd bierze się utwór i dlaczego suwak
     * głośności z zakładki nie rusza tego, co właśnie gra.
     */
    public function helpKeys(): array
    {
        return [
            'module.' . AudioSettings::ID . '.help.start',
            'module.' . AudioSettings::ID . '.help.track',
            'module.' . AudioSettings::ID . '.help.volume',
        ];
    }

    /**
     * Obie komendy dostają **ten sam** port — inaczej przełącznik pytałby jeden
     * obiekt o to, czy gra, a głośność zmieniałaby drugiemu.
     *
     * @return list<\LightManager\Application\Command\CommandInterface>
     */
    private function assemble(): array
    {
        $audio = $this->audio ?? self::player();

        return [
            new MusicCommand($audio, $this->state, $this->translator),
            new VolumeCommand(
                $audio,
                new ChangeVolumeUseCase($this->settings),
                $this->state,
                $this->translator,
            ),
        ];
    }

    /**
     * Odtwarzacz wedle środowiska: prawdziwy, gdy rozszerzenie `glfw` jest
     * załadowane, cisza w przeciwnym razie.
     *
     * To jest **jedyne miejsce**, w którym brak rozszerzenia jest pytaniem;
     * dalej cały moduł rozmawia wyłącznie z portem i nie wie, z którym.
     */
    private static function player(): AudioPort
    {
        $engine = GlAudioService::getInstance();

        return $engine->isAvailable() ? $engine : SilentAudioService::getInstance();
    }
}
