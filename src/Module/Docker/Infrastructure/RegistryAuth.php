<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Infrastructure;

/**
 * Nagłówek `X-Registry-Auth` — poświadczenia w postaci, której żąda demon
 * (krok 54, D94 nr 2).
 *
 * Klasa istnieje, bo kodowanie jest **nieoczywiste w dwóch miejscach naraz**,
 * a pomyłka w każdym z nich kończy się tym samym: demon odsyła `401` i wygląda
 * to jak zły token. Po pierwsze to jest base64 **wedle URL** (`-` i `_` zamiast
 * `+` i `/`) i **bez dopełnienia** — zwykły `base64_encode()` daje napis, który
 * demon odrzuca. Po drugie pole nazywa się `serveraddress`, małymi literami
 * i jednym słowem.
 *
 * Rozczytywanie i składanie cudzych formatów leży w `Infrastructure` **za
 * portem** (reguła 11t), więc ta klasa stoi tu, a nie obok pracy, która jej
 * używa.
 */
final class RegistryAuth
{
    /**
     * Wartość nagłówka albo **pusty napis**, gdy poświadczeń nie ma.
     *
     * Pusty napis, a nie wyjątek: rejestr publiczny przyjmuje odczyt bez
     * logowania, a o tym, czy wypchnięcie się uda, rozstrzyga demon — nie my.
     */
    public static function header(string $registry, string $user, string $token): string
    {
        if ($user === '' || $token === '') {
            return '';
        }

        $json = json_encode([
            'username' => $user,
            'password' => $token,
            'serveraddress' => $registry,
        ]);

        if ($json === false) {
            return '';
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }
}
