<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Application;

use LightManager\Application\Port\SettingsPort;
use LightManager\Module\Audio\Application\Port\AudioPort;
use LightManager\Module\Audio\Application\Port\EffectMapPort;
use LightManager\Module\Audio\Application\Port\TrackFilesPort;

/**
 * Odbiorca zdarzeń: co gra i kiedy milczy (krok 46).
 *
 * Klasa jest jedynym miejscem, w którym moduł dźwięku wie o istnieniu zdarzeń —
 * i **nie zna ani jednej ich nazwy**. Dostaje napis, zagląda do mapy, gra albo
 * milczy; słownik należy do tych, którzy go wnoszą, a odbiorca ma go czytać, nie
 * powtarzać. Dzięki temu zdarzenie dołożone w rdzeniu albo w cudzym module
 * pojawia się tutaj bez jednej linii zmiany.
 *
 * **Odbiór nie dotyka dysku** i to jest reguła, nie optymalizacja: zdarzenie pada
 * w środku `LoopState::report()` i w środku czynności na plikach, więc odczyt
 * mapy albo sprawdzenie, czy plik istnieje, wchodziłyby do cudzej pracy. Mapa
 * wczytuje się w **takcie** (`useTime()`, wołane raz na klatkę), a dostępność
 * plików przelicza się przy otwarciu okna modułu. Zdarzenie, które padło przed
 * pierwszym taktem, milczy — i tak ma być, bo to zdarzenia ze startu aplikacji.
 *
 * **Minimalny odstęp** jest po tej samej stronie i z tego samego powodu, dla
 * którego karencja startu utworu jest w `PlaylistPlayer`: publikujący nie ma
 * prawa wiedzieć, że ktoś jego zdarzenie zamienia na dźwięk. Trzymana strzałka
 * daje trzydzieści zdarzeń kursora na sekundę; bez progu klik zamieniłby się
 * w warkot, a funkcja w karę.
 */
final class SoundEffects
{
    /**
     * Ile sekund to samo zdarzenie musi milczeć po zagraniu.
     *
     * Sto milisekund to trzy klatki: trzymana strzałka daje wtedy najwyżej
     * dziesięć kliknięć na sekundę, czyli rytm, a nie ciągły dźwięk. Próg jest
     * **na zdarzenie**, nie na cały odtwarzacz — usunięcie pliku tuż po ruchu
     * kursora ma zagrać, bo to dwie różne rzeczy.
     */
    private const MINIMUM_INTERVAL_SECONDS = 0.1;

    private ?EffectMap $map = null;

    /** Licznik zmian mapy — pokolenie dla kwerendy `audio.effects` (krok 53). */
    private int $revision = 0;

    /** @var array<string, float> zdarzenie → chwila ostatniego zagrania */
    private array $playedAt = [];

    private float $now = 0.0;

    public function __construct(
        private readonly AudioPort $audio,
        private readonly EffectMapPort $storage,
        private readonly TrackFilesPort $files,
        private readonly SettingsPort $settings,
    ) {
    }

    /**
     * Takt modułu: zapamiętanie chwili i — raz — wczytanie mapy.
     *
     * Po wczytaniu takt kosztuje **jedno przypisanie**; dopóki efekty są
     * wyłączone, kosztuje dodatkowo jeden odczyt ustawienia, bo włączenie ich
     * w zakładce ma zadziałać od następnej klatki, a nie od następnego
     * uruchomienia.
     */
    public function useTime(float $now): void
    {
        $this->now = $now;

        if ($this->map !== null || !$this->enabled()) {
            return;
        }

        $this->map = $this->loaded();
    }

    /**
     * Zdarzenie z rdzenia albo z cudzego modułu.
     *
     * Pięć powodów milczenia i wszystkie tanie: efekty wyłączone, mapa jeszcze
     * nieczytana, zdarzenie bez przypisania, przypisanie wyciszone albo
     * wskazujące plik, którego nie ma, oraz to samo zdarzenie zagrane przed
     * chwilą. Dopiero za nimi stoi jedno wywołanie portu.
     *
     * Kłopotu z odtworzeniem **nie zgłaszamy nikomu** i to nie jest przeoczenie:
     * jesteśmy w środku cudzej czynności, a zdanie w pasku stanu nadpisałoby to,
     * które ta czynność właśnie o sobie powiedziała — zdarzenie miałoby wtedy
     * wpływ na aplikację, a nie ma prawa go mieć. Widać to za to w oknie modułu:
     * pozycja z brakującym plikiem stoi wyszarzona.
     */
    public function onEvent(string $event): void
    {
        $assignment = $this->map?->at($event);

        if ($assignment === null || !$assignment->playable() || !$this->enabled()) {
            return;
        }

        $last = $this->playedAt[$event] ?? null;

        if ($last !== null && $this->now - $last < self::MINIMUM_INTERVAL_SECONDS) {
            return;
        }

        $this->playedAt[$event] = $this->now;
        $this->audio->playEffect($assignment->path, AudioSettings::effectsVolume($this->settings->current()));
    }

    /** Mapa przypisań — wczytana przy pierwszym pytaniu (okno modułu, komenda). */
    public function map(): EffectMap
    {
        if ($this->map === null) {
            $this->map = $this->loaded();
            ++$this->revision;
        }

        return $this->map;
    }

    /**
     * Licznik zmian mapy — pokolenie dla kwerendy `audio.effects` (krok 53).
     *
     * Ta sama konstrukcja i ten sam powód, co przy playliście: wiersze przypisań
     * budują się po zmianie, a nie po każdym spojrzeniu na okno.
     */
    public function revision(): int
    {
        return $this->revision;
    }

    /**
     * Przypisuje plik zdarzeniu i zapisuje mapę.
     *
     * Ścieżka wskazująca plik, którego nie ma, **wchodzi** — wyszarzona
     * i milcząca, dokładnie jak pozycja playlisty (D82 nr 6). Odmowa byłaby
     * gorsza: nośnik odpięty w tej chwili bywa podpięty za minutę.
     *
     * @return bool czy ścieżka w ogóle była ścieżką
     */
    public function assign(string $event, string $path): bool
    {
        $path = trim($path);

        if ($path === '') {
            return false;
        }

        $map = $this->map();
        $map->assign($event, $path, !$this->files->exists($path));
        $this->storage->saveEffects($map);
        ++$this->revision;

        return true;
    }

    public function clear(string $event): bool
    {
        $map = $this->map();

        if (!$map->clear($event)) {
            return false;
        }

        $this->storage->saveEffects($map);
        ++$this->revision;

        return true;
    }

    public function toggle(string $event): bool
    {
        $map = $this->map();

        if (!$map->toggle($event)) {
            return false;
        }

        $this->storage->saveEffects($map);
        ++$this->revision;

        return true;
    }

    /** Ponowne sprawdzenie, których plików nie ma — przy otwarciu okna modułu. */
    public function refresh(): void
    {
        $this->map()->refresh($this->files->exists(...));
        ++$this->revision;
    }

    public function enabled(): bool
    {
        return AudioSettings::effectsEnabled($this->settings->current());
    }

    private function loaded(): EffectMap
    {
        $map = $this->storage->loadEffects();
        $map->refresh($this->files->exists(...));

        return $map;
    }
}
