<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Infrastructure;

/**
 * Fragment zmiany sekretu podawany `kubectl patch --type=merge` (krok 52).
 *
 * Leży w `Infrastructure`, bo **składa cudzy format** — dokładnie z tego powodu,
 * dla którego leżą tu parsery JSON-a klastra (reguła 11t). Warstwa aplikacji
 * mówi „ten klucz ma mieć taką wartość”, a czym jest `{"data":{…}}`, wie to
 * miejsce.
 *
 * Dwie własności scalającej zmiany, obie wykorzystane i obie warte zapamiętania:
 * **klucz z wartością `null` zostaje skasowany**, a klucz nieobecny we fragmencie
 * **zostaje nietknięty**. Stąd bierze się cała edycja sekretu z D91 nr 10 —
 * dodanie, zmiana i skasowanie klucza to jedno polecenie w trzech odmianach,
 * a nie trzy różne czynności.
 *
 * Wartość przychodzi **już zakodowana w base64**, bo o tym, czy użytkownik wpisał
 * zapis base64, czy tekst do zakodowania, rozstrzyga okno — a to nie jest wiedza
 * o formacie Kubernetesa.
 */
final class SecretPatch
{
    /**
     * Fragment zmieniający jeden klucz.
     *
     * `JSON_UNESCAPED_SLASHES` i `JSON_UNESCAPED_UNICODE` są tu **potrzebne, a nie
     * ozdobne**: klucze sekretów bywają ścieżkami (`tls.crt`, `ca/bundle.pem`),
     * a nadmiarowe ucieczki przeszłyby przez `kubectl` jako inny klucz. Kodowania
     * pilnuje `json_encode()`, bo cytowanie powłoki załatwia dopiero
     * `escapeshellarg()` przy składaniu polecenia — to dwie różne warstwy
     * ucieczek i żadna nie zastępuje drugiej.
     */
    public static function forKey(string $key, ?string $base64Value): string
    {
        $patch = json_encode(
            ['data' => [$key => $base64Value]],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        // `json_encode()` na tablicy o napisowych kluczach nie ma jak zawieść,
        // ale sygnatura dopuszcza `false` — pusty obiekt jest wtedy zmianą,
        // która niczego nie zmienia, i to jest najbezpieczniejsze, co można tu
        // podstawić.
        return $patch === false ? '{}' : $patch;
    }

    /**
     * Czy napis jest poprawnym zapisem base64 — pytanie okna edycji.
     *
     * Sprawdzamy **ściśle** (`strict: true`), bo `base64_decode()` w trybie
     * pobłażliwym przyjmuje wszystko, ignorując znaki spoza alfabetu: wpisane
     * hasło `moje hasło` przeszłoby jako „poprawny base64” i wylądowało
     * w klastrze jako `moehaso`.
     */
    public static function isBase64(string $value): bool
    {
        return $value !== '' && base64_decode($value, true) !== false;
    }

    /** Zapis base64 wartości podanej tekstem surowym. */
    public static function encode(string $raw): string
    {
        return base64_encode($raw);
    }
}
