<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleSetting;

/**
 * Ustawienia modułu Dockera (krok 51).
 *
 * Deklaracja i odczyt stoją obok siebie, wzorem `SshSettings` i `AudioSettings`
 * — są dwiema stronami tej samej umowy i rozdzielone rozjechałyby się przy
 * pierwszej zmianie listy wartości.
 *
 * **Pozycja jest jedna** i to jest liczba wynikająca z rozstrzygnięć startowych,
 * a nie z oszczędności: granicę bufora logów użytkownik ustawił jako pozycję
 * (D90 nr 3), a częstotliwość odświeżania — świadomie **nie** (D90 nr 7),
 * bo druga pozycja w kroku, który i tak jest największy w projekcie, kupowałaby
 * niewiele. Ścieżki pliku compose tu nie ma z trzeciego powodu: bierze się
 * z kontekstu i z pola tekstowego (D90 nr 5), a wartość zapisana w ustawieniach
 * byłaby **trzecią drogą do tej samej rzeczy**.
 *
 * **Krok 54 dokłada trzy pozycje i wszystkie trzy opisują rejestr obrazów**
 * (D94 nr 1 i 2): adres, użytkownika i token. Weszły razem z `docker.push`,
 * bez którego obraz zbudowany na demonie hosta jest dla klastra niewidoczny —
 * minikube prowadzi własnego demona w kontenerze. Poświadczenia stoją
 * w ustawieniach, a **nie** czyta się ich z `~/.docker/config.json`, i jest to
 * rozstrzygnięcie użytkownika podjęte z ceną wypisaną przed wyborem: demon
 * tamtego pliku nie czyta w ogóle (to plik **klienta**), więc `X-Registry-Auth`
 * i tak trzeba złożyć samemu — pytanie brzmiało wyłącznie, skąd wziąć wartości.
 *
 * Token jest **pozycją zasłoniętą** (`ModuleSetting::secret()`, D94 nr 7).
 * Zasłona broni przed spojrzeniem, nie przed odczytem pliku: `settings.json`
 * trzyma wartość jawnie i tak ma być — to nie jest magazyn sekretów i nie udaje
 * go.
 */
final class DockerSettings
{
    public const ID = 'docker';

    /** Ile wierszy logu trzymamy w pamięci, zanim najstarsze zaczną wypadać. */
    public const LOG_LINES = 'logLines';

    /**
     * Przystanki, po których chodzą strzałki.
     *
     * Wartość jest **liczbą z listy, a nie dowolną z zakresu**, i wynika to
     * wprost z kontraktu ustawień modułu: `ModuleSetting::valueFrom()` sprowadza
     * wartość spoza listy do domyślnej, więc zapisane 1500 przepadłoby przy
     * pierwszym odczycie.
     *
     * @var list<int>
     */
    public const LOG_LINES_CHOICES = [500, 1000, 2000, 5000, 10000];

    public const DEFAULT_LOG_LINES = 2000;

    /** Adres rejestru obrazów — host, ewentualnie z portem. */
    public const REGISTRY = 'registry';

    /** Użytkownik rejestru. */
    public const REGISTRY_USER = 'registryUser';

    /** Token rejestru — **pozycja zasłonięta**. */
    public const REGISTRY_TOKEN = 'registryToken';

    /**
     * Rejestr domyślny: GHCR (D94 nr 1).
     *
     * Wybrany, bo pakiety publiczne są tam bez limitu i **nie wymagają
     * `imagePullSecret`** po stronie klastra — a tego sekretu czynność
     * `k8s.deploy-image` świadomie nie zakłada (D94 nr 3).
     */
    public const DEFAULT_REGISTRY = 'ghcr.io';

    /**
     * Wzorzec adresu rejestru: host z opcjonalnym portem.
     *
     * Wąski z założenia, tak jak wzorce `HostProfile` z kroku 48, i z tego samego
     * powodu: wartość wchodzi do nazwy obrazu, a stamtąd do wiersza polecenia
     * `kubectl`. Pierwszy znak **nie może** być myślnikiem.
     */
    public const REGISTRY_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]*(:[0-9]{1,5})?$/';

    /** Wzorzec użytkownika — to, co dopuszczają rejestry obrazów. */
    public const USER_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]*$/';

    /** @return list<ModuleSetting> */
    public static function declarations(): array
    {
        return [
            self::logLinesDeclaration(),
            self::registryDeclaration(),
            self::registryUserDeclaration(),
            self::registryTokenDeclaration(),
        ];
    }

    public static function registryFrom(Settings $settings): string
    {
        $value = self::registryDeclaration()->valueFrom($settings->moduleValue(self::ID, self::REGISTRY));

        return is_string($value) && $value !== '' ? $value : self::DEFAULT_REGISTRY;
    }

    public static function registryUserFrom(Settings $settings): string
    {
        $value = self::registryUserDeclaration()->valueFrom($settings->moduleValue(self::ID, self::REGISTRY_USER));

        return is_string($value) ? $value : '';
    }

    public static function registryTokenFrom(Settings $settings): string
    {
        $value = self::registryTokenDeclaration()->valueFrom($settings->moduleValue(self::ID, self::REGISTRY_TOKEN));

        return is_string($value) ? $value : '';
    }

    private static function registryDeclaration(): ModuleSetting
    {
        return ModuleSetting::text(
            self::REGISTRY,
            'module.' . self::ID . '.setting.' . self::REGISTRY,
            self::DEFAULT_REGISTRY,
            self::REGISTRY_PATTERN,
            maxLength: 255,
        );
    }

    private static function registryUserDeclaration(): ModuleSetting
    {
        return ModuleSetting::text(
            self::REGISTRY_USER,
            'module.' . self::ID . '.setting.' . self::REGISTRY_USER,
            pattern: self::USER_PATTERN,
            maxLength: 255,
        );
    }

    private static function registryTokenDeclaration(): ModuleSetting
    {
        return ModuleSetting::secret(
            self::REGISTRY_TOKEN,
            'module.' . self::ID . '.setting.' . self::REGISTRY_TOKEN,
            maxLength: 512,
        );
    }

    public static function logLinesFrom(Settings $settings): int
    {
        $value = self::logLinesDeclaration()->valueFrom($settings->moduleValue(self::ID, self::LOG_LINES));

        return is_int($value) ? $value : self::DEFAULT_LOG_LINES;
    }

    private static function logLinesDeclaration(): ModuleSetting
    {
        return ModuleSetting::number(
            self::LOG_LINES,
            'module.' . self::ID . '.setting.' . self::LOG_LINES,
            self::LOG_LINES_CHOICES,
            self::DEFAULT_LOG_LINES,
        );
    }
}
