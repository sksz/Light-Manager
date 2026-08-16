<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Infrastructure;

/**
 * Wzorce z pliku `.dockerignore` (krok 51).
 *
 * Czytamy **podzbiór składni**, i mówimy to wprost zamiast udawać kompletność:
 * komentarze (`#`), puste wiersze, wzorce dopasowywane `fnmatch`iem i wyjątki
 * zaczynające się `!`. Poza podzbiorem zostaje pełna semantyka Dockera —
 * wzorce `**` w środku ścieżki i kolejność reguł rozstrzygana wpis po wpisie.
 *
 * Uproszczenie jest bezpieczne w jedną stronę i to jest cała jego obrona:
 * różnica objawia się **rozmiarem kontekstu**, a nie wynikiem budowy. Plik
 * niepotrzebnie wysłany demonowi kosztuje bajty; plik niepotrzebnie pominięty
 * kosztowałby nieudaną budowę — a tego ten podzbiór nie robi, bo wyjątki `!`
 * czyta.
 *
 * **Katalog jest wykluczony razem z zawartością**: wzorzec `node_modules`
 * wyklucza `node_modules/express/index.js`, bo dopasowanie sprawdza się także
 * dla każdego przodka ścieżki. Bez tego najczęstszy wpis świata Node.js nie
 * robiłby nic.
 */
final class DockerIgnore
{
    /**
     * @param list<string> $excluded wzorce wykluczające
     * @param list<string> $included wzorce z `!` — wyjątki od wykluczeń
     */
    private function __construct(
        private readonly array $excluded,
        private readonly array $included,
    ) {
    }

    public static function readFrom(string $directory): self
    {
        $path = $directory . '/.dockerignore';

        if (!is_file($path) || !is_readable($path)) {
            return new self([], []);
        }

        $contents = @file_get_contents($path);

        return $contents === false ? new self([], []) : self::of($contents);
    }

    public static function of(string $contents): self
    {
        $excluded = [];
        $included = [];

        foreach (explode("\n", str_replace("\r", "\n", $contents)) as $line) {
            $pattern = trim($line);

            if ($pattern === '' || str_starts_with($pattern, '#')) {
                continue;
            }

            if (str_starts_with($pattern, '!')) {
                $included[] = self::normalize(substr($pattern, 1));

                continue;
            }

            $excluded[] = self::normalize($pattern);
        }

        return new self($excluded, $included);
    }

    /**
     * Czy ścieżka względna wypada z kontekstu.
     *
     * Wyjątek (`!`) ma **pierwszeństwo przed wykluczeniem**, niezależnie od
     * kolejności wierszy w pliku. Docker rozstrzyga to ostatnią pasującą regułą;
     * tutaj rozstrzyga wyjątek, bo przy podzbiorze składni jest to różnica
     * działająca w stronę bezpieczną: plik zostaje w kontekście.
     */
    public function excludes(string $relativePath): bool
    {
        $path = self::normalize($relativePath);

        if (self::matchesAny($this->included, $path)) {
            return false;
        }

        return self::matchesAny($this->excluded, $path);
    }

    /** @param list<string> $patterns */
    private static function matchesAny(array $patterns, string $path): bool
    {
        foreach ($patterns as $pattern) {
            if (self::matches($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dopasowanie wzorca do ścieżki **i do każdego jej przodka**.
     *
     * Przodkowie są tu całą sztuczką: wzorzec `node_modules` ma wykluczyć
     * `node_modules/express/index.js`, a `fnmatch` sam z siebie tego nie zrobi,
     * bo `*` nie przechodzi przez ukośnik.
     */
    private static function matches(string $pattern, string $path): bool
    {
        if (fnmatch($pattern, $path)) {
            return true;
        }

        $prefix = '';

        foreach (explode('/', $path) as $segment) {
            $prefix = $prefix === '' ? $segment : $prefix . '/' . $segment;

            if (fnmatch($pattern, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** Bez wiodącego i końcowego ukośnika — `/build/` i `build` znaczą to samo. */
    private static function normalize(string $pattern): string
    {
        return trim(trim($pattern), '/');
    }
}
