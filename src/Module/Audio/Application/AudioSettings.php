<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Application;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleSetting;

/**
 * Ustawienia modułu dźwięku: jak głośno, co po utworze i czy grać od startu.
 *
 * Deklaracja i odczyt stoją obok siebie, wzorem `BrowserSettings`
 * i `FileInfoSettings` — są dwiema stronami tej samej umowy i rozdzielone
 * rozjechałyby się przy pierwszej zmianie listy wartości.
 *
 * **Krok 45 zmienia tu trzy rzeczy naraz i każda ma powód poza wygodą.**
 *
 * *Utwór wyszedł z zakładki.* Klucz `track` nie jest już pozycją ustawień, bo
 * wyboru utworu nie da się dłużej zapisać jedną ścieżką — od tego jest playlista.
 * Stała została **wyłącznie jako źródło migracji** (zakres 5 kroku): jej dawna
 * wartość zasila playlistę przy pierwszym uruchomieniu po zmianie, bo ustawienie,
 * które użytkownik świadomie ustawił, nie ma prawa zniknąć przy przenosinach
 * mechanizmu.
 *
 * *Zapętlenie zamieniło się w tryb odtwarzania.* Przełącznik `loop` odpowiadał na
 * pytanie „czy w kółko”, bo utwór był jeden; przy playliście odpowiedzi są trzy
 * (`PlaybackMode`). Dawna wartość przekłada się bez pytania użytkownika o zdanie:
 * `true` to pętla listy, `false` — zatrzymanie po utworze.
 *
 * *Autostart wreszcie ma kogo obudzić.* Krok 36 wykluczył go dlatego, że kontrakt
 * modułu nie znał cyklu życia (D70); krok 45 dokłada takt (`NeedsTick`), więc
 * pozycja przestała być obietnicą bez pokrycia (D82 nr 7). Domyślnie **wyłączony**:
 * aplikacja grająca bez pytania przy pierwszym uruchomieniu zaskakuje, a pomiar
 * czyta tę samą konfigurację.
 *
 * Głośność jest **liczbą z listy przystanków**, a nie dowolną wartością z zakresu,
 * i to wynika wprost z kontraktu ustawień modułu: `ModuleSetting::valueFrom()`
 * sprowadza wartość spoza listy do domyślnej, więc zapisane 63 przepadłoby przy
 * pierwszym odczycie.
 */
final class AudioSettings
{
    public const ID = 'audio';

    /**
     * Klucz utworu z kroku 36 — **już nie jest pozycją zakładki**.
     *
     * Zostaje tu po to, żeby migracja miała czego szukać w konfiguracji
     * użytkownika. Wartość spod niego wchodzi na playlistę raz, przy pierwszym
     * uruchomieniu po zmianie; potem nie jest już czytana.
     */
    public const TRACK = 'track';

    public const VOLUME = 'volume';

    /** Klucz zapętlenia z kroku 36 — czytany wyłącznie przez migrację do `MODE`. */
    public const LOOP = 'loop';

    public const MODE = 'mode';

    public const AUTOSTART = 'autostart';

    /**
     * Utwór domyślny leży w repozytorium, w `assets/audio/`.
     *
     * Ścieżka jest **względna wobec korzenia projektu**, a nie bezwzględna:
     * bezwzględna zapisałaby się do stanu modułu przy pierwszym zapisie
     * i przestałaby działać po przeniesieniu katalogu z aplikacją.
     */
    public const DEFAULT_TRACK = 'assets/audio/Deep Purple - Smoke On The Water.mp3';

    /** Formaty, które czyta miniaudio — i zarazem jedyne podpowiadane przy wpisywaniu ścieżki. */
    public const TRACK_EXTENSIONS = ['mp3', 'wav', 'flac'];

    /** Przystanki strzałki na zakładce — i zarazem jedyne wartości, które przyjmuje `audio.volume`. */
    public const VOLUME_CHOICES = [0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100];

    public const DEFAULT_VOLUME = 50;

    /** Utwór ma pięć i pół minuty, a praca z menadżerem plików trwa dłużej. */
    public const DEFAULT_MODE = PlaybackMode::LoopList;

    public const DEFAULT_AUTOSTART = false;

    /**
     * Wzorzec ścieżki: przechodzi wszystko poza znakami sterującymi — dokładnie
     * ten sam, którym `FileInfo` pilnuje argumentów polecenia `file`. Ścieżki nie
     * sprawdzamy tu głębiej: czy plik istnieje i czy da się go odtworzyć,
     * rozstrzyga próba odtworzenia, a nie wzorzec.
     */
    public const TRACK_PATTERN = '/^[^\x00-\x1F\x7F]*$/u';

    public const TRACK_MAX_LENGTH = 255;

    /** @return list<ModuleSetting> */
    public static function declarations(): array
    {
        return [
            ModuleSetting::choice(
                self::MODE,
                'module.' . self::ID . '.setting.' . self::MODE,
                PlaybackMode::choices(),
                self::DEFAULT_MODE->value,
            ),
            ModuleSetting::number(
                self::VOLUME,
                'module.' . self::ID . '.setting.' . self::VOLUME,
                self::VOLUME_CHOICES,
                self::DEFAULT_VOLUME,
            ),
            ModuleSetting::toggle(
                self::AUTOSTART,
                'module.' . self::ID . '.setting.' . self::AUTOSTART,
                self::DEFAULT_AUTOSTART,
            ),
        ];
    }

    /**
     * Deklaracja pod kluczem — **po nazwie, nie po numerze**.
     *
     * Do kroku 45 odczyty wskazywały deklaracje numerem i wystarczało to dopóty,
     * dopóki lista rosła wyłącznie na końcu. Ten krok wyjmuje pozycję ze środka,
     * więc numer przestał cokolwiek znaczyć; szukanie po kluczu kosztuje trzy
     * porównania raz na odczyt i nie ma jak się rozjechać.
     */
    public static function declarationOf(string $key): ModuleSetting
    {
        foreach (self::declarations() as $declaration) {
            if ($declaration->key === $key) {
                return $declaration;
            }
        }

        // Nieosiągalne: klucze pochodzą ze stałych tej samej klasy. Gdyby jednak
        // ktoś dopisał odczyt bez deklaracji, ma dostać coś, co zachowuje się
        // przewidywalnie, a nie `null` do rozgałęziania w każdym wołającym.
        return ModuleSetting::text($key, 'module.' . self::ID . '.setting.' . $key);
    }

    /** Deklaracja głośności — potrzebna komendzie, żeby sprawdzić wartość tą samą listą. */
    public static function volumeDeclaration(): ModuleSetting
    {
        return self::declarationOf(self::VOLUME);
    }

    public static function volume(Settings $settings): int
    {
        $value = self::volumeDeclaration()->valueFrom($settings->moduleValue(self::ID, self::VOLUME));

        return is_int($value) ? $value : self::DEFAULT_VOLUME;
    }

    /**
     * Tryb odtwarzania — z nowego klucza, a gdy go nie ma, **z dawnego
     * przełącznika zapętlenia**.
     *
     * Migracja jest tu, a nie w osobnym przebiegu przepisującym plik, i to jest
     * świadome: klucz `mode` zapisze się sam przy pierwszej zmianie w zakładce,
     * a do tego czasu wartość z kroku 36 nadal rządzi. Konfiguracja użytkownika
     * nie zmienia się przez to bez jego udziału.
     */
    public static function mode(Settings $settings): PlaybackMode
    {
        $stored = $settings->moduleValue(self::ID, self::MODE);

        if (is_string($stored)) {
            $mode = PlaybackMode::tryFrom($stored);

            if ($mode !== null) {
                return $mode;
            }
        }

        $legacy = $settings->moduleValue(self::ID, self::LOOP);

        if (is_bool($legacy)) {
            return $legacy ? PlaybackMode::LoopList : PlaybackMode::StopAfterTrack;
        }

        return self::DEFAULT_MODE;
    }

    public static function autostarts(Settings $settings): bool
    {
        $value = self::declarationOf(self::AUTOSTART)
            ->valueFrom($settings->moduleValue(self::ID, self::AUTOSTART));

        return is_bool($value) ? $value : self::DEFAULT_AUTOSTART;
    }

    /**
     * Utwór z konfiguracji kroku 36 — **wyłącznie dla migracji playlisty**.
     *
     * Pusty napis znaczy „użytkownik nic nie ustawił”; wtedy playlista rodzi się
     * z utworem domyślnym, bo pusta lista przy pierwszym otwarciu okna nie mówi
     * nic o tym, do czego to okno służy.
     */
    public static function legacyTrack(Settings $settings): string
    {
        $stored = $settings->moduleValue(self::ID, self::TRACK);

        return is_string($stored) && trim($stored) !== '' ? $stored : self::DEFAULT_TRACK;
    }
}
