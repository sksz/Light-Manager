<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * **Reguła 15 sprawdzana maszynowo: moduł nigdy nie sięga do innego modułu.**
 *
 * Strażnik powstał w kroku 54, i to nie z ostrożności, tylko dlatego, że tamten
 * krok **po raz pierwszy postawił moduły w sytuacji, w której miałyby po co**:
 * czynność `k8s.deploy-image` buduje obraz Dockerem i wdraża go Kubernetesem.
 * Do tej pory reguła trzymała się sama, bo żaden moduł nie miał interesu
 * w cudzym; od tego kroku ma i dlatego potrzebuje strażnika.
 *
 * **Sprawdzana jest przestrzeń nazw, a nie treść** — inaczej niż
 * w `QueryIsTheOnlyReadPathTest`, i różnica jest zasadnicza. Tam zakazane było
 * *czytanie* obiektem, który wolno trzymać, więc widać to wyłącznie
 * w wywołaniu; tu zakazane jest **samo wymienienie typu**, a to widać w `use`
 * i w każdym miejscu, które pisze pełną nazwę klasy. Napisu z nazwą cudzej
 * komendy albo kwerendy (`'docker.images'`) zakaz nie dotyczy — to jest właśnie
 * ta droga, którą reguła 15g dopuszcza (krok 53, D92).
 *
 * Trzy rzeczy są **poza zasięgiem** i każda z innego powodu: rdzeń
 * (`LightManager\Application`, `Domain`, `Infrastructure`, `Presentation`) jest
 * tym, do czego sięgać wolno; testy nie są modułami; a `Module\<własny>` jest
 * własną przestrzenią modułu.
 */
final class NoModuleKnowsAnotherModuleTest extends TestCase
{
    /** Katalogi modułów — spis bierze się z dysku, żeby siódmy moduł nie wypadł z zasięgu. */
    private const MODULES_DIRECTORY = 'src/Module';

    public function testNoModuleMentionsATypeFromAnotherModule(): void
    {
        $offenders = [];

        foreach (self::modules() as $module) {
            foreach (self::filesOf($module) as $path => $contents) {
                foreach (self::foreignReferences($contents, $module) as $reference) {
                    $offenders[] = $path . ' → ' . $reference;
                }
            }
        }

        self::assertSame(
            [],
            $offenders,
            "moduł wymienia typ innego modułu (reguła 15 — pytaj nazwą komendy albo kwerendy):\n"
                . implode("\n", $offenders),
        );
    }

    /**
     * Kontrola samego strażnika: **czy w ogóle ma czego pilnować**.
     *
     * Test chodzący po pustej liście przechodzi zawsze i nie mówi nic. Ten
     * przypadek pilnuje, żeby zmiana układu katalogów nie zamieniła strażnika
     * w ozdobę — sześć modułów istnieje od kroku 52.
     */
    public function testTheGuardHasSomethingToWatch(): void
    {
        $modules = self::modules();

        self::assertGreaterThanOrEqual(6, count($modules), 'strażnik nie widzi modułów');
        self::assertContains('Docker', $modules);
        self::assertContains('Kubernetes', $modules);
    }

    /**
     * Nazwy typów z cudzych modułów wymienione w treści pliku.
     *
     * @return list<string>
     */
    private static function foreignReferences(string $contents, string $module): array
    {
        $found = [];

        if (preg_match_all('/LightManager\\\\Module\\\\([A-Za-z]+)\\\\([A-Za-z\\\\]+)/', $contents, $matches) === false) {
            return [];
        }

        foreach ($matches[1] as $index => $other) {
            if ($other === $module) {
                continue;
            }

            $found[] = 'LightManager\\Module\\' . $other . '\\' . $matches[2][$index];
        }

        return array_values(array_unique($found));
    }

    /** @return list<string> */
    private static function modules(): array
    {
        $root = dirname(__DIR__, 2) . '/' . self::MODULES_DIRECTORY;
        $names = [];

        foreach ((array) scandir($root) as $entry) {
            if (is_string($entry) && $entry[0] !== '.' && is_dir($root . '/' . $entry)) {
                $names[] = $entry;
            }
        }

        return $names;
    }

    /**
     * @return array<string, string> ścieżka względna → treść pliku
     */
    private static function filesOf(string $module): array
    {
        $root = dirname(__DIR__, 2);
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $root . '/' . self::MODULES_DIRECTORY . '/' . $module,
                RecursiveDirectoryIterator::SKIP_DOTS,
            ),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if ($contents !== false) {
                $files[substr($file->getPathname(), strlen($root) + 1)] = $contents;
            }
        }

        return $files;
    }
}
