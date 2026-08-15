<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Infrastructure;

use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;

/**
 * Gdzie leży gniazdo mistrza połączenia (krok 48, wydzielone w kroku 49).
 *
 * Klasa powstała, gdy **drugi** użytkownik gniazda wszedł do modułu: sesję
 * zestawia `OpenSshSessionService`, a katalog czyta `SftpDirectoryService` —
 * i obie strony muszą wskazać **dokładnie ten sam plik**, bo inaczej odczyt
 * otworzyłby własne połączenie z własnym uściskiem dłoni zamiast wejść przez
 * gniazdo stojącego mistrza. Rachunek powtórzony w dwóch miejscach rozjechałby
 * się przy pierwszej poprawce, a rozjazd byłby niewidoczny: wszystko dalej
 * działa, tylko wolniej i z drugim pytaniem o hasło.
 *
 * Nazwa jest **skrótem z celu**, a nie celem, i to nie jest ostrożność
 * przesadzona: gniazdo uniksowe ma twardy limit długości ścieżki (108 bajtów na
 * Linuksie), a `użytkownik@host:port` w katalogu domowym potrafi go przekroczyć.
 * Skrót jest przy tym stały między uruchomieniami, więc mistrz zostawiony przez
 * poprzedni proces daje się odnaleźć.
 */
final class ControlSocket
{
    private const DIRECTORY = '.light-manager';

    /** Znak wodny w nazwie — żeby dało się je poznać w katalogu. */
    private const PREFIX = 'ssh-';

    private const SUFFIX = '.sock';

    private const DIRECTORY_MODE = 0o700;

    private const DIGEST_LENGTH = 16;

    public static function pathFor(HostProfile $profile): string
    {
        $digest = substr(hash('sha256', $profile->target() . ':' . $profile->port), 0, self::DIGEST_LENGTH);

        return self::directory() . DIRECTORY_SEPARATOR . self::PREFIX . $digest . self::SUFFIX;
    }

    /**
     * Katalog domowy z `HOME`, a w jego braku — katalog roboczy (jak
     * w konfiguracji i w pliku stanu modułu).
     */
    public static function directory(): string
    {
        $home = getenv('HOME');

        if (!is_string($home) || $home === '') {
            $working = getcwd();
            $home = $working === false ? '.' : $working;
        }

        $directory = rtrim($home, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::DIRECTORY;

        if (!is_dir($directory)) {
            @mkdir($directory, self::DIRECTORY_MODE, true);
        }

        return $directory;
    }
}
