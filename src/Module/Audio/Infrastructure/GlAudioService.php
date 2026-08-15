<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Infrastructure;

use GL\Audio\Engine;
use GL\Audio\Sound;
use LightManager\Infrastructure\I18n\TranslatorService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\Port\AudioPort;
use Throwable;

/**
 * Muzyka przez moduł audio rozszerzenia PHP-GLFW (krok 36).
 *
 * Trzy rzeczy sprawdzone na starcie kroku i wszystkie trzy widać w tym kodzie.
 * **Pierwsza: silnik nie potrzebuje okna** — `Engine` startuje bez `glfwInit()`
 * i bez kontekstu OpenGL, więc muzyka gra we wszystkich trzech torach, także
 * w terminalu. Zależność od Fazy IX przez to nie stwardniała.
 * **Druga: `Sound::stop()` jest pauzą, nie przewinięciem** — kursor zostaje tam,
 * gdzie był, więc `play()` wznawia. Stąd jedna komenda-przełącznik zamiast pary
 * „graj” i „zatrzymaj”. **Trzecia: MIDI nie wchodzi** — miniaudio czyta WAV, MP3
 * i FLAC, więc plik `.mid` z tego samego katalogu jest odrzucany opisem, a nie
 * wyjątkiem lecącym przez pół aplikacji.
 *
 * Referencja do `Sound` **musi przeżyć całą grę** i dlatego jest polem, a nie
 * zmienną lokalną: obiekt zebrany przez odśmiecacz w trakcie odtwarzania zabiera
 * ze sobą dźwięk, a testu, który by to złapał, napisać się nie da.
 *
 * Ścieżkę względną rozstrzyga `TrackFileService`, a nie ta klasa (krok 45).
 * Reguła („licz od korzenia projektu, bo tam leży `assets/`”) ma od tamtego kroku
 * **dwóch użytkowników**: tę usługę i sprawdzanie dostępności plików playlisty.
 * Rozjechana dawałaby listę pokazującą pozycję jako obecną, której zagrać się nie
 * da — więc mieszka w jednym miejscu.
 *
 * Silnik startuje **przy pierwszym użyciu**, nie przy budowie modułu: moduł
 * powstaje przy każdym uruchomieniu aplikacji, a wątek audio ma powstać wtedy,
 * gdy ktoś naprawdę poprosi o muzykę.
 *
 * Sprzątanie idzie **dwiema drogami naraz** (D47): jawnie, gdy woła je moduł,
 * i przez `register_shutdown_function` rejestrowaną przy starcie silnika — na
 * wyjścia, których pierwsza droga nie dosięga. Wyjście sygnałem przechodzi przez
 * obie, bo pętla kończy się wtedy normalnie.
 */
final class GlAudioService extends AbstractSingleton implements AudioPort
{
    /** Klasa rozszerzenia sprawdzana **napisem**, żeby stub PHPStana jej nie „udowodnił”. */
    private const ENGINE_CLASS = 'GL\Audio\Engine';

    private ?Engine $engine = null;

    /** Wczytany utwór — pole, bo referencja musi przeżyć całą grę. */
    private ?Sound $sound = null;

    /** Ścieżka utworu trzymanego w `$sound`; pusta, dopóki nic nie wczytano. */
    private string $loaded = '';

    /**
     * Drugi uchwyt — **efekt** (krok 46).
     *
     * Osobne pole, a nie drugie użycie `$sound`, bo tamten trzyma muzykę i jego
     * kursor jest pamięcią pauzy: zagranie efektu przez ten sam obiekt kasowałoby
     * miejsce, w którym stanął utwór. Referencja musi przy tym przeżyć całą grę
     * z tego samego powodu, co tamta — obiekt zebrany przez odśmiecacz zabiera ze
     * sobą dźwięk.
     */
    private ?Sound $effect = null;

    /** Ścieżka efektu trzymanego w `$effect`. */
    private string $loadedEffect = '';

    private bool $cleanupRegistered = false;

    public function isAvailable(): bool
    {
        return class_exists(self::ENGINE_CLASS);
    }

    public function play(string $path, int $volume, bool $loop): ?string
    {
        if (!$this->isAvailable()) {
            return $this->text('module.' . AudioSettings::ID . '.problem.unavailable');
        }

        $resolved = TrackFileService::resolved($path);
        $sound = $this->soundOf($resolved);

        if ($sound === null) {
            return $this->text('module.' . AudioSettings::ID . '.problem.load', ['path' => $path]);
        }

        $sound->setVolume(self::asFraction($volume));
        $sound->setLoop($loop);
        $sound->play();

        return null;
    }

    /**
     * Efekt gra **na** muzyce: drugi `Sound` z tego samego silnika, bez pytania
     * o to, czy cokolwiek innego akurat gra.
     *
     * Wczytanie idzie tą samą drogą, co utwór, więc ten sam plik odpalony drugi
     * raz nie dotyka dysku — a że efekt zaczyna się zawsze od początku, kursor
     * trzeba **cofnąć ręcznie**: `play()` na dźwięku, który dobiegł końca, wraca
     * do zera sam, ale `play()` na przerwanym w połowie wznowiłby go od miejsca
     * przerwania. Dla klika trwającego pół sekundy różnica jest słyszalna.
     */
    public function playEffect(string $path, int $volume): ?string
    {
        if (!$this->isAvailable()) {
            return $this->text('module.' . AudioSettings::ID . '.problem.unavailable');
        }

        $resolved = TrackFileService::resolved($path);
        $effect = $this->effectOf($resolved);

        if ($effect === null) {
            return $this->text('module.' . AudioSettings::ID . '.problem.load', ['path' => $path]);
        }

        $effect->stop();
        /** @phpstan-ignore method.notFound */
        $effect->seekTo(0);
        $effect->setVolume(self::asFraction($volume));
        $effect->setLoop(false);
        $effect->play();

        return null;
    }

    public function stop(): void
    {
        $this->sound?->stop();
    }

    /**
     * Czy coś teraz gra — pytamy o to **silnik**, a nie własną flagę, bo utwór
     * kończy się sam i flaga kłamałaby przy wyłączonym zapętleniu.
     *
     * Tu stoi **pierwsze w projekcie punktowe wyciszenie analizy** (reguła 14)
     * i ma konkretny powód, nie wygodę: stuby `phpgl/ide-stubs` są
     * **starsze od rozszerzenia**, które
     * projekt ma zainstalowane. `GL\Audio\Sound` w wersji 2.2.0 wystawia
     * `isPlaying()`, `getCursor()` i `seekTo()`; stub zna wyłącznie starszy
     * zestaw metod. Wyjściem alternatywnym było liczenie stanu po swojemu —
     * i to właśnie ono byłoby obejściem analizy kosztem zachowania. Uwaga znika,
     * gdy stuby dogonią rozszerzenie (krok 34 obszedł ich dwie błędne stałe
     * literałami — to ta sama klasa problemu).
     */
    public function isPlaying(): bool
    {
        /** @phpstan-ignore method.notFound */
        return $this->sound?->isPlaying() === true;
    }

    public function useVolume(int $volume): void
    {
        $this->sound?->setVolume(self::asFraction($volume));
    }

    /**
     * Zatrzymanie silnika wraz z jego wątkiem.
     *
     * Utwór puszczamy **przed** silnikiem i to nie jest kosmetyka: `Sound`
     * trzyma dekoder karmiony przez silnik, więc zwolnienie ich w odwrotnej
     * kolejności zostawiałoby obiekt wskazujący na zatrzymane źródło.
     * Wolno wołać wielokrotnie — druga próba nie ma czego zatrzymać.
     */
    public function shutdown(): void
    {
        $this->sound?->stop();
        $this->sound = null;
        $this->loaded = '';

        $this->effect?->stop();
        $this->effect = null;
        $this->loadedEffect = '';

        $this->engine?->stop();
        $this->engine = null;
    }

    /**
     * Wczytany utwór albo `null`, gdy pliku nie da się odtworzyć.
     *
     * Ten sam plik drugi raz **nie wchodzi na dysk**: wczytany zostaje w polu,
     * a jego kursor jest pamięcią pauzy, więc powtórne wczytanie kasowałoby
     * miejsce, w którym użytkownik przerwał.
     */
    private function soundOf(string $path): ?Sound
    {
        if ($this->sound !== null && $this->loaded === $path) {
            return $this->sound;
        }

        $engine = $this->started();

        if ($engine === null) {
            return null;
        }

        try {
            $sound = $engine->soundFromDisk($path);
        } catch (Throwable) {
            // Brak pliku, plik nieczytelny albo format spoza miniaudio (na
            // przykład MIDI) — wszystkie trzy są tu tym samym: nie ma czego grać.
            return null;
        }

        $this->sound?->stop();
        $this->sound = $sound;
        $this->loaded = $path;

        return $sound;
    }

    /**
     * Wczytany efekt albo `null`, gdy pliku nie da się odtworzyć.
     *
     * Bliźniak `soundOf()` i celowo **nie jest z nim scalony**: obie metody
     * różnią się polem, w którym trzymają wynik, a scalone przez parametr
     * kazałyby wołającemu podawać, czym jest to, co puszcza — czyli przenosiłyby
     * decyzję z tej klasy do jej użytkowników.
     */
    private function effectOf(string $path): ?Sound
    {
        if ($this->effect !== null && $this->loadedEffect === $path) {
            return $this->effect;
        }

        $engine = $this->started();

        if ($engine === null) {
            return null;
        }

        try {
            $effect = $engine->soundFromDisk($path);
        } catch (Throwable) {
            return null;
        }

        $this->effect?->stop();
        $this->effect = $effect;
        $this->loadedEffect = $path;

        return $effect;
    }

    /** Silnik uruchomiony leniwie; `null`, gdy nie da się go postawić. */
    private function started(): ?Engine
    {
        if ($this->engine !== null) {
            return $this->engine;
        }

        try {
            $engine = new Engine();
            $engine->start();
        } catch (Throwable) {
            return null;
        }

        $this->engine = $engine;
        $this->registerCleanup();

        return $engine;
    }

    /**
     * Druga droga sprzątania — na wyjścia, których nie dosięga jawne wołanie
     * modułu (błąd krytyczny, `exit()` w miejscu, którego nikt nie przewidział).
     * Rejestrujemy ją **raz i leniwie**, dokładnie jak usługa procesu tłowego.
     */
    private function registerCleanup(): void
    {
        if ($this->cleanupRegistered) {
            return;
        }

        $this->cleanupRegistered = true;
        register_shutdown_function($this->shutdown(...));
    }

    /** Procenty pozycji ustawień na ułamek, którego chce silnik. */
    private static function asFraction(int $volume): float
    {
        return max(0, min(100, $volume)) / 100;
    }

    /** @param array<string, string> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return TranslatorService::getInstance()->translate($key, $parameters);
    }
}
