<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Presentation;

use LightManager\Application\Module\ListensToEvents;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleSettingsTab;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Application\Module\NeedsTick;
use LightManager\Application\Module\ProvidesCommands;
use LightManager\Application\Module\ProvidesSettingsTab;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\PlaylistPlayer;
use LightManager\Module\Audio\Application\Port\AudioPort;
use LightManager\Module\Audio\Application\Port\EffectMapPort;
use LightManager\Module\Audio\Application\Port\PlaylistPort;
use LightManager\Module\Audio\Application\Port\TrackFilesPort;
use LightManager\Module\Audio\Application\SoundEffects;
use LightManager\Module\Audio\Application\UseCase\ChangeVolumeUseCase;
use LightManager\Module\Audio\Infrastructure\AudioStateService;
use LightManager\Module\Audio\Infrastructure\GlAudioService;
use LightManager\Module\Audio\Infrastructure\SilentAudioService;
use LightManager\Module\Audio\Infrastructure\TrackFileService;
use LightManager\Module\Audio\Presentation\Command\AddTrackCommand;
use LightManager\Module\Audio\Presentation\Command\HookCommand;
use LightManager\Module\Audio\Presentation\Command\MusicCommand;
use LightManager\Module\Audio\Presentation\Command\VolumeCommand;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Module\ProvidesHelpTab;
use LightManager\Presentation\Ui\Module\ProvidesScreen;
use LightManager\Presentation\Ui\ScreenInterface;

/**
 * Muzyka jako moduł — bez ekranu w kroku 36, z ekranem i playlistą od kroku 45.
 *
 * Krok 36 planowany był jako rozbudowa rdzenia i rozstrzygnięcie użytkownika
 * odwróciło to w całości (D70): dźwięk jest nową funkcją, więc jest modułem.
 * Rdzeń kosztował przez to dokładnie tyle, ile reguła 15 przewiduje — jedną
 * pozycję na liście w `Bootstrapie` — a modułowi zostały dwie komendy, zakładka
 * ustawień i **żadnego wywołania spoza własnych komend**. Ceną było „autostartu
 * nie ma” i milcząca playlista, której nie było.
 *
 * **Krok 45 dokłada trzy zdolności i jedna z nich zmienia rdzeń.** `ProvidesScreen`
 * i skrót `Ctrl`+`A` dają modułowi okno; `NeedsTick` daje mu takt — i to jest
 * jawne odwrócenie D70 (D71, D82 nr 1). Różnica, na której to stoi: tam zdolność
 * miała jednego użytkownika i wyłącznie dla wygody, tutaj bez niej funkcja nie
 * istnieje. Playlista, która nie wie, że utwór się skończył, jest listą ścieżek.
 *
 * Moduł jest przez to sprawdzianem kontraktu z **dwóch** stron naraz: rysuje
 * (jak przeglądarka z kroku 21) i **pracuje, gdy go nie widać** — czego przed tym
 * krokiem nie umiał żaden.
 *
 * Składa się leniwie, jak wszystkie: napisy wchodzą do katalogu **po** zbudowaniu
 * rejestru modułów, więc komenda ani ekran zbudowane zachłannie mogłyby wypisać
 * użytkownikowi surowy klucz. Playlisty przy tym **nie czyta**, dopóki ktoś
 * o nią nie zapyta — uruchomienie z wyłączonym autostartem nie kosztuje ani
 * jednego odczytu z dysku.
 */
final class AudioModule implements
    ModuleInterface,
    ProvidesSettingsTab,
    ProvidesCommands,
    ProvidesHelpTab,
    ProvidesScreen,
    NeedsTick,
    ListensToEvents
{
    /**
     * Litera skrótu.
     *
     * `a` była wolna: zajęte są `b` (przeglądarka) i `d` (opis pliku), a `c`, `h`,
     * `i`, `j`, `m` i `z` odpadają z przyczyn terminalowych
     * (`ModuleRegistry::FORBIDDEN_CHARACTERS`).
     */
    private const SHORTCUT = 'a';

    /** @var list<\LightManager\Application\Command\CommandInterface>|null */
    private ?array $commands = null;

    private ?PlaylistPlayer $player = null;

    private ?SoundEffects $effects = null;

    private ?AudioScreen $screen = null;

    /**
     * @param ?AudioPort      $audio    wstrzyknięcie istnieje dla testów, które nie mają
     *                                  prawa uruchomić silnika audio — tak samo, jak testy
     *                                  `FileInfo` nie mają prawa uruchomić `du`. `null`
     *                                  znaczy „wybierz implementację wedle środowiska”
     * @param ?PlaylistPort   $storage  jw. — test nie ma prawa dotknąć pliku w katalogu domowym
     * @param ?TrackFilesPort $files    jw. — ani przeglądać dysku w poszukiwaniu utworów
     * @param ?EffectMapPort  $effectStorage jw. — mapa przypisań mieszka w tym samym pliku, co playlista
     */
    public function __construct(
        private readonly LoopState $state,
        private readonly TranslatorPort $translator,
        private readonly SettingsPort $settings,
        private readonly ?AudioPort $audio = null,
        private readonly ?PlaylistPort $storage = null,
        private readonly ?TrackFilesPort $files = null,
        private readonly ?EffectMapPort $effectStorage = null,
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
     * `Ctrl`+`A` otwiera playlistę.
     *
     * Do kroku 45 skrótu nie było i było to uczciwe: moduł nie miał ekranu, który
     * miałby otworzyć, a skrót bez ekranu zajmowałby literę w przestrzeni, w której
     * jest ich dwadzieścia kilka, i nie robiłby nic.
     */
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
        return new ModuleSettingsTab($this->nameKey(), AudioSettings::declarations());
    }

    public function commands(): array
    {
        return $this->commands ??= $this->assemble();
    }

    public function screen(): ScreenInterface
    {
        return $this->screen ??= new AudioScreen(
            $this->player(),
            $this->effects(),
            $this->state->events(),
            $this->translator,
        );
    }

    /**
     * Takt modułu — **jedno wywołanie na klatkę i niczego więcej rdzeń nie robi**.
     *
     * Cała praca dzieje się w `PlaylistPlayer::tick()` i sprowadza się do
     * porównania stanu; wejścia-wyjścia nie ma tu ani jednego. Wyjątek nie ma
     * prawa stąd wylecieć w takiej postaci, żeby przerwał pętlę — łapie go
     * `ModuleTicker`, tą samą drogą, którą łapane są wyjątki ekranu.
     */
    public function tick(float $now): void
    {
        $this->player()->tick($now);
        $this->effects()->useTime($now);
    }

    /**
     * Zdarzenie z rdzenia albo z cudzego modułu (krok 46).
     *
     * Moduł **nie zna ani jednej nazwy zdarzenia** i nie ma jej poznać: przekazuje
     * napis odtwarzaczowi efektów, a ten zagląda do mapy przypisań. Dzięki temu
     * zdarzenie dołożone gdziekolwiek indziej pojawia się w oknie modułu bez ani
     * jednej zmiany tutaj.
     *
     * Czasu ta metoda nie dostaje i nie potrzebuje: minimalnym odstępem między
     * efektami rządzi chwila zapamiętana w takcie, o którą ten moduł i tak już
     * prosi (`NeedsTick`). Dwie drogi do zegara byłyby dwiema prawdami o tym,
     * która jest teraz klatka.
     */
    public function onEvent(string $event): void
    {
        $this->effects()->onEvent($event);
    }

    /**
     * Część własna zakładki pomocy — to, czego z deklaracji wyczytać się nie da.
     *
     * Cztery zdania i każde odpowiada na pytanie, które użytkownik zada: czym się
     * to otwiera, skąd biorą się utwory, co znaczy tryb odtwarzania i dlaczego
     * suwak głośności z zakładki nie rusza tego, co właśnie gra.
     */
    public function helpKeys(): array
    {
        return [
            'module.' . AudioSettings::ID . '.help.start',
            'module.' . AudioSettings::ID . '.help.playlist',
            'module.' . AudioSettings::ID . '.help.mode',
            'module.' . AudioSettings::ID . '.help.volume',
            'module.' . AudioSettings::ID . '.help.effects',
        ];
    }

    /**
     * Odtwarzacz playlisty — **jeden na moduł**.
     *
     * Jeden, bo inaczej takt pilnowałby jednego stanu, okno pokazywało drugi,
     * a komenda przełączała trzeci. To ta sama zasada, dla której w kroku 36 obie
     * komendy dostawały ten sam port: dwa obiekty znaczą dwie prawdy.
     */
    private function player(): PlaylistPlayer
    {
        return $this->player ??= new PlaylistPlayer(
            $this->audio ?? self::engine(),
            $this->storage ?? AudioStateService::getInstance(),
            $this->files ?? TrackFileService::getInstance(),
            $this->settings,
            $this->translator,
        );
    }

    /**
     * Odtwarzacz efektów — **jeden na moduł**, z tego samego powodu, co odtwarzacz
     * playlisty: takt karmi go czasem, zdarzenia wołają, okno pokazuje jego mapę,
     * a komenda ją zmienia. Cztery obiekty znaczyłyby cztery prawdy.
     *
     * Port dźwięku dostaje **ten sam**, co muzyka, i to jest warunek, żeby efekt
     * zagrał **na** utworze: dwa silniki znaczyłyby dwa niezależne miksery.
     */
    private function effects(): SoundEffects
    {
        return $this->effects ??= new SoundEffects(
            $this->audio ?? self::engine(),
            $this->effectStorage ?? AudioStateService::getInstance(),
            $this->files ?? TrackFileService::getInstance(),
            $this->settings,
        );
    }

    /**
     * @return list<\LightManager\Application\Command\CommandInterface>
     */
    private function assemble(): array
    {
        $player = $this->player();

        return [
            new MusicCommand($player, $this->translator),
            new AddTrackCommand($player, $this->files ?? TrackFileService::getInstance(), $this->translator),
            new HookCommand(
                $this->effects(),
                $this->state->events(),
                $this->files ?? TrackFileService::getInstance(),
                $this->translator,
            ),
            new VolumeCommand(
                $this->audio ?? self::engine(),
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
     * dalej cały moduł rozmawia wyłącznie z portem i nie wie, z którym. Obie drogi
     * oddają Singleton, więc odtwarzacz playlisty i komenda głośności dostają ten
     * sam obiekt — inaczej suwak zmieniałby głośność czemuś, co nie gra.
     */
    private static function engine(): AudioPort
    {
        $engine = GlAudioService::getInstance();

        return $engine->isAvailable() ? $engine : SilentAudioService::getInstance();
    }
}
