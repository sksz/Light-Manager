<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Config;

/**
 * Jedna droga zapisu pliku stanu (krok 59, D103).
 *
 * Do kroku 59 ten mechanizm stał w projekcie **pięć razy**: w konfiguracji,
 * w historii komend i w trzech usługach stanu modułów — skopiowany niemal co
 * do znaku. Przegląd z reguły 15e zakończył się rozstrzygnięciem użytkownika:
 * mechanizm jest jeden i mieszka tutaj. Trzy gwarancje, których pilnuje:
 *
 * - **plik tymczasowy i `rename()` w tym samym katalogu** — przerwany zapis
 *   zostawia poprzednią, poprawną wersję zamiast obciętej;
 * - **prawa `0600` na pliku i `0700` na katalogu** — wpisy mówią, z jakimi
 *   maszynami użytkownik rozmawia i gdzie leżą jego klucze;
 * - **wynik zamiast wyjątku** — o tym, czy niepowodzenie jest ciszą (historia,
 *   stan modułów) czy wyjątkiem (konfiguracja), rozstrzyga wołający, bo to
 *   jego kontrakt, nie mechanizmu.
 *
 * Klasa statyczna jak parsery infrastruktury (`ClusterInfoParser`) — nie jest
 * usługą z tożsamością, tylko rachunkiem z jednym skutkiem ubocznym.
 */
final class StateFile
{
    public const FILE_MODE = 0o600;

    public const DIRECTORY_MODE = 0o700;

    private const DIRECTORY = '.light-manager';

    /**
     * Katalog stanu aplikacji: `~/.light-manager`.
     *
     * Katalog domowy bierzemy z `HOME`. Gdy zmiennej nie ma — a to stan
     * patologiczny, nie zwykły — stan ląduje w katalogu roboczym procesu,
     * żeby aplikacja działała zamiast wywracać się na starcie.
     */
    public static function directory(): string
    {
        $home = getenv('HOME');

        if (!is_string($home) || $home === '') {
            $working = getcwd();
            $home = $working === false ? '.' : $working;
        }

        return rtrim($home, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::DIRECTORY;
    }

    /**
     * Zapisuje treść pod wskazaną nazwą — plikiem tymczasowym i `rename()`.
     *
     * Treść dostaje na końcu znak nowej linii, bo plik stanu ma się dać
     * przeczytać oczami, a `cat` bez końcowej nowej linii skleja go z wierszem
     * polecenia.
     */
    public static function write(
        string $directory,
        string $file,
        string $temporaryPrefix,
        string $content,
    ): StateWriteOutcome {
        if (!is_dir($directory) && !@mkdir($directory, self::DIRECTORY_MODE, true) && !is_dir($directory)) {
            return StateWriteOutcome::DirectoryFailed;
        }

        $temporary = $directory . DIRECTORY_SEPARATOR . $temporaryPrefix . getmypid() . '.tmp';

        if (@file_put_contents($temporary, $content . "\n") === false) {
            return StateWriteOutcome::FileFailed;
        }

        @chmod($temporary, self::FILE_MODE);

        if (!@rename($temporary, $directory . DIRECTORY_SEPARATOR . $file)) {
            @unlink($temporary);

            return StateWriteOutcome::FileFailed;
        }

        return StateWriteOutcome::Written;
    }
}
