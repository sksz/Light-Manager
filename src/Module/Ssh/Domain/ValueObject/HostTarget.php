<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Domain\ValueObject;

use LightManager\Module\Ssh\Domain\Exception\InvalidHostProfileException;

/**
 * Rozbiera `[użytkownik@]host[:port]` na profil (krok 48).
 *
 * **Postać jest ta, którą użytkownik zna z `ssh`**, i to jest cały powód, dla
 * którego okno dodawania wpisu ma jedno pole zamiast formularza: rdzeń nie ma
 * komponentu formularza, a dokładanie go dla trzech pól znaczyłoby nowy
 * komponent bez drugiego użytkownika (reguła 13).
 *
 * Klasa leży w `Domain` i jest **czysta w całości** — bierze napis, oddaje
 * obiekt wartości albo rzuca wyjątkiem, który sam się przedstawia. To ostatnie
 * jest tu wyjątkiem od zasady „port nie rzuca", i słusznie: nie jesteśmy
 * w porcie. Wyjątek łapie ekran i zamienia na zdanie w pasku stanu, zamiast
 * pokazywać ślad stosu.
 *
 * **IPv6 w nawiasach kwadratowych rozstrzyga się przed portem** i to jest cała
 * trudność tego rozbioru: `::1` ma dwukropki w środku, więc „ostatni dwukropek
 * oddziela port" byłoby regułą fałszywą. Adres bez nawiasów, ale z więcej niż
 * jednym dwukropkiem, czytamy w całości jako host — bo `fe80::1:22` to adres,
 * a nie adres z portem, i tak samo czyta go `ssh`.
 */
final class HostTarget
{
    /**
     * Cel złożony z wpisu książki adresowej i własnych poświadczeń modułu
     * (krok 60).
     *
     * Adres rozbiera się na `[użytkownik@]host[:port]`, bo tak go zwykle pisze
     * człowiek — ale **pola rozdziału `ssh` wygrywają**, gdy są. Powód jest
     * praktyczny: wpis dopisany komendą `address-book.add biuro admin@10.0.0.5`
     * ma login w adresie i nikt go potem nie przepisuje do osobnego pola,
     * a wpis wypełniony w oknach książki ma pola i pusty login w adresie.
     *
     * @param ?int    $port port z rozdziału albo `null`, gdy rozdział go nie ma
     * @param ?string $user login z rozdziału albo `null`, jw.
     *
     * @throws InvalidHostProfileException gdy adresu nie da się przeczytać albo
     *                                     gdy profil odmówi samowalidacji
     */
    public static function of(
        string $id,
        string $name,
        string $address,
        HostCredentials $credentials,
        ?int $port = null,
        ?string $user = null,
    ): HostProfile {
        $address = trim($address);

        if ($address === '') {
            throw InvalidHostProfileException::invalidHost($address);
        }

        [$parsedUser, $rest] = self::splitUser($address);
        [$host, $parsedPort] = self::splitPort($rest);

        if ($host === '') {
            throw InvalidHostProfileException::invalidHost($rest);
        }

        return new HostProfile(
            $id,
            $name,
            $host,
            $port ?? $parsedPort,
            $user === null || $user === '' ? $parsedUser : $user,
            $credentials->auth,
            $credentials->keyPath,
        );
    }

    /** @return array{string, string} login (pusty, gdy go nie podano) i reszta */
    private static function splitUser(string $value): array
    {
        $at = strrpos($value, '@');

        if ($at === false) {
            return ['', $value];
        }

        return [substr($value, 0, $at), substr($value, $at + 1)];
    }

    /** @return array{string, int} host i port */
    private static function splitPort(string $value): array
    {
        // `[adres]:port` — jedyna postać, w której port przy IPv6 jest jednoznaczny.
        if (str_starts_with($value, '[')) {
            $close = strpos($value, ']');

            if ($close === false) {
                throw InvalidHostProfileException::invalidHost($value);
            }

            $host = substr($value, 1, $close - 1);
            $tail = substr($value, $close + 1);

            return [$host, self::portFrom($tail, $value)];
        }

        // Więcej niż jeden dwukropek znaczy goły adres IPv6, a nie host z portem.
        if (substr_count($value, ':') !== 1) {
            return [$value, HostProfile::DEFAULT_PORT];
        }

        [$host, $port] = explode(':', $value, 2);

        return [$host, self::portFrom(':' . $port, $value)];
    }

    /** @param string $tail to, co zostało po haście — pustka albo `:port` */
    private static function portFrom(string $tail, string $whole): int
    {
        if ($tail === '') {
            return HostProfile::DEFAULT_PORT;
        }

        if (!str_starts_with($tail, ':')) {
            throw InvalidHostProfileException::invalidHost($whole);
        }

        $port = substr($tail, 1);

        if ($port === '' || preg_match('/^\d+$/', $port) !== 1) {
            throw InvalidHostProfileException::invalidPort(0);
        }

        return (int) $port;
    }
}
