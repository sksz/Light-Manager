<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Infrastructure;

use LightManager\Module\Ssh\Domain\ValueObject\HostFingerprint;

/**
 * Czyta wyjście `ssh-keygen -lf -` (krok 48).
 *
 * Klasa **czysta w całości** — dostaje napis, oddaje obiekty wartości, nie
 * dotyka ani dysku, ani sieci. To jest jedno z dwóch miejsc, w których krok
 * rozbiera cudze wyjście, i dlatego oba stoją osobno od usługi: rozbieranie
 * napisu jest jedyną rzeczą tutaj, którą da się sprawdzić testem bez serwera.
 *
 * Wiersz wygląda tak (sprawdzone na maszynie projektu):
 *
 * ```
 * 256 SHA256:EZQpKi4iUrJWT2nvMqRy5H6Xxy5R1PX65l6pJhzgxjo host.example.com (ED25519)
 * ```
 *
 * — czyli **liczba bitów, odcisk, komentarz, typ w nawiasie**. Komentarz przy
 * wejściu z `ssh-keyscan` jest nazwą hosta, ale polegać na tym nie wolno
 * i nie polegamy: jedyne, co z wiersza bierzemy, to odcisk, typ i bity.
 *
 * **Wiersz nie do rozczytania wypada, a reszta zostaje** — ta sama reguła, co
 * przy pozycji playlisty bez ścieżki (krok 45). Serwer oddaje zwykle trzy klucze
 * różnych typów i to, że jednego z nich nie umiemy rozczytać, nie jest powodem,
 * żeby nie pokazać pozostałych.
 */
final class FingerprintParser
{
    /** `<bity> <odcisk> <komentarz…> (<TYP>)` — komentarz bywa pusty i bywa ze spacjami. */
    private const LINE = '/^(\d+)\s+(\S+)\s+(?:.*\s+)?\(([A-Za-z0-9-]+)\)\s*$/';

    /** @return list<HostFingerprint> */
    public static function parse(string $output): array
    {
        $fingerprints = [];

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $fingerprint = self::line(trim($line));

            if ($fingerprint !== null) {
                $fingerprints[] = $fingerprint;
            }
        }

        return $fingerprints;
    }

    private static function line(string $line): ?HostFingerprint
    {
        if ($line === '' || preg_match(self::LINE, $line, $matches) !== 1) {
            return null;
        }

        $value = $matches[2];

        // Odcisk bez przedrostka funkcji skrótu nie jest odciskiem, tylko
        // przypadkowym słowem w miejscu, w którym go oczekiwaliśmy.
        if (!str_contains($value, ':')) {
            return null;
        }

        return new HostFingerprint(strtoupper($matches[3]), $value, (int) $matches[1]);
    }
}
