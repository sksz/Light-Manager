<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleSetting;
use LightManager\Module\Ssh\Domain\ValueObject\AuthMethod;

/**
 * Ustawienia modułu sesji zdalnej (krok 48).
 *
 * Deklaracja i odczyt stoją obok siebie, wzorem `AudioSettings`
 * i `BrowserSettings` — są dwiema stronami tej samej umowy i rozdzielone
 * rozjechałyby się przy pierwszej zmianie listy wartości.
 *
 * **Pozycji było trzy przy trzech przewidzianych planem kroku 48** — „czy łączyć
 * się przy starcie z ostatnim hostem" nie powstało, bo rozstrzygnięcie D87 nr 10
 * brzmiało „nie", a pozycja ustawień dla funkcji, której nie ma, jest obietnicą
 * bez pokrycia. Uruchomienie aplikacji nie sięga przez to do sieci ani razu.
 * **Czwartą dołożył krok 49** (wpisy ukryte) i jest ona pierwszą pozycją tego
 * modułu, która ma zarazem klawisz.
 *
 * Limit czasu jest **liczbą z listy przystanków**, a nie dowolną wartością
 * z zakresu, i to wynika wprost z kontraktu ustawień modułu:
 * `ModuleSetting::valueFrom()` sprowadza wartość spoza listy do domyślnej, więc
 * zapisane 17 przepadłoby przy pierwszym odczycie.
 */
final class SshSettings
{
    public const ID = 'ssh';

    /** Ile sekund czekać na odpowiedź hosta — `ConnectTimeout` oraz `-T` keyscanu. */
    public const TIMEOUT = 'timeout';

    /** Sposób uwierzytelnienia dla nowych wpisów książki. */
    public const AUTH = 'auth';

    /**
     * Czy wolno dopisać odcisk nowego hosta do `~/.ssh/known_hosts`.
     *
     * **Wyłączenie tej pozycji nie osłabia sprawdzania — odbiera drogę.** Host
     * nieznany kończy się wtedy komunikatem, a nie połączeniem bez weryfikacji,
     * bo „połącz, ale nie zapamiętuj" znaczyłoby przy każdym połączeniu pytanie
     * o odcisk, którego nie ma z czym porównać. Kto tak chce, dopisuje host
     * `ssh`em i aplikacja go zobaczy.
     */
    public const REMEMBER = 'remember';

    /**
     * Czy zdalna lista pokazuje wpisy zaczynające się kropką (krok 49).
     *
     * Pozycja ustawień **i** klawisz `Ctrl`+`H`, jak w przeglądarce — z jedną
     * różnicą, którą trzeba znać: tutaj przełączenie znaczy **nowy obieg do
     * serwera**, bo `sftp ls` bez `-a` wpisów ukrytych w ogóle nie przysyła.
     * W przeglądarce ta sama czynność kosztuje przejście po tablicy.
     */
    public const SHOW_HIDDEN = 'showHidden';

    /** @var list<int> */
    public const TIMEOUT_CHOICES = [5, 10, 15, 20, 30, 60];

    public const DEFAULT_TIMEOUT = 10;

    public const DEFAULT_AUTH = AuthMethod::Agent;

    public const DEFAULT_REMEMBER = true;

    public const DEFAULT_SHOW_HIDDEN = false;

    /** @return list<ModuleSetting> */
    public static function declarations(): array
    {
        return [
            ModuleSetting::number(
                self::TIMEOUT,
                'module.' . self::ID . '.setting.' . self::TIMEOUT,
                self::TIMEOUT_CHOICES,
                self::DEFAULT_TIMEOUT,
            ),
            ModuleSetting::choice(
                self::AUTH,
                'module.' . self::ID . '.setting.' . self::AUTH,
                AuthMethod::choices(),
                self::DEFAULT_AUTH->value,
            ),
            ModuleSetting::toggle(
                self::REMEMBER,
                'module.' . self::ID . '.setting.' . self::REMEMBER,
                self::DEFAULT_REMEMBER,
            ),
            ModuleSetting::toggle(
                self::SHOW_HIDDEN,
                'module.' . self::ID . '.setting.' . self::SHOW_HIDDEN,
                self::DEFAULT_SHOW_HIDDEN,
            ),
        ];
    }

    public static function timeoutFrom(Settings $settings): int
    {
        $value = self::timeoutDeclaration()->valueFrom($settings->moduleValue(self::ID, self::TIMEOUT));

        return is_int($value) ? $value : self::DEFAULT_TIMEOUT;
    }

    public static function authFrom(Settings $settings): AuthMethod
    {
        $stored = $settings->moduleValue(self::ID, self::AUTH);

        return is_string($stored) ? AuthMethod::of($stored) ?? self::DEFAULT_AUTH : self::DEFAULT_AUTH;
    }

    public static function remembersFrom(Settings $settings): bool
    {
        $stored = $settings->moduleValue(self::ID, self::REMEMBER);

        return is_bool($stored) ? $stored : self::DEFAULT_REMEMBER;
    }

    public static function showsHiddenFrom(Settings $settings): bool
    {
        $stored = $settings->moduleValue(self::ID, self::SHOW_HIDDEN);

        return is_bool($stored) ? $stored : self::DEFAULT_SHOW_HIDDEN;
    }

    private static function timeoutDeclaration(): ModuleSetting
    {
        return ModuleSetting::number(
            self::TIMEOUT,
            'module.' . self::ID . '.setting.' . self::TIMEOUT,
            self::TIMEOUT_CHOICES,
            self::DEFAULT_TIMEOUT,
        );
    }
}
