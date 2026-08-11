<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Główne kryterium kroku 21, sprawdzane maszynowo: **w rdzeniu nie zostaje ani
 * jedna klasa wiedząca, czym jest katalog albo wpis w systemie plików.**
 *
 * Test chodzi po przestrzeniach nazw, a nie po treści metod, bo tylko to daje się
 * sprawdzić bez zgadywania, i pilnuje dwóch reguł:
 *
 * 1. **Twardej** — kod w `src/Domain`, `src/Application`, `src/Infrastructure`
 *    i `src/Presentation` nie ma prawa wymienić ani jednego typu z modułu
 *    przeglądarki. To tam mieszka dziś cała wiedza o katalogach i wpisach, więc
 *    ta reguła jest kryterium kroku wyrażonym wprost. Jedyny wyjątek: `Bootstrap`
 *    wymienia klasę modułu, bo jest z definicji jedynym miejscem, które konkretne
 *    moduły zna.
 * 2. **Ogólnej** — poza `Bootstrapem` rdzeń nie wymienia żadnego typu z `src/Module`.
 *    To sprawdzian obietnicy z kroku 20: dopisanie modułu ma kosztować jedną
 *    zmianę w rdzeniu.
 */
final class CoreKnowsNothingAboutFilesTest extends TestCase
{
    /** Warstwy rdzenia — wszystko w `src/` poza katalogiem modułów. */
    private const CORE = ['Domain', 'Application', 'Infrastructure', 'Presentation'];

    /**
     * Jedyne miejsce, któremu wolno zobaczyć moduł — a modułu przeglądarki
     * wyłącznie przez jego klasę główną, nigdy przez jego domenę, przypadki
     * użycia ani infrastrukturę.
     */
    private const WIRING = 'src/Presentation/Cli/Bootstrap.php';

    private const BROWSER = 'LightManager\\Module\\Browser\\';

    public function testNoCoreClassSeesTheFileSystemDomainOfTheBrowserModule(): void
    {
        $offenders = [];

        foreach (self::coreFiles() as $path => $contents) {
            foreach (self::moduleReferences($contents) as $reference) {
                if (!str_starts_with($reference, self::BROWSER)) {
                    continue;
                }

                if ($path === self::WIRING && self::isModuleClass($reference)) {
                    continue;
                }

                $offenders[] = $path . ' → ' . $reference;
            }
        }

        self::assertSame([], $offenders, 'rdzeń wie, czym jest katalog');
    }

    public function testOnlyTheWiringMentionsModulesAtAll(): void
    {
        $offenders = [];

        foreach (self::coreFiles() as $path => $contents) {
            if ($path === self::WIRING) {
                continue;
            }

            foreach (self::moduleReferences($contents) as $reference) {
                $offenders[] = $path . ' → ' . $reference;
            }
        }

        self::assertSame([], $offenders, 'moduł widoczny poza jedynym miejscem, które ma go znać');
    }

    /**
     * `Bootstrap` widzi z **każdego** modułu wyłącznie jego klasę główną.
     *
     * Do kroku 25 reguła obowiązywała naprawdę tylko przeglądarkę: opis pliku był
     * składany w `Bootstrapie` z czterech klas — ekranu, przypadku użycia, usługi
     * i tłumacza — i dług ten zapisano wprost w dzienniku kroku 21. Ten test jest
     * jego spłatą wyrażoną maszynowo: gdyby ktoś zechciał znów złożyć wnętrze
     * modułu w rdzeniu, zapali się tutaj, a nie na przeglądzie.
     */
    public function testTheWiringSeesNothingButTheModuleClassOfEveryModule(): void
    {
        $offenders = [];

        foreach (self::moduleReferences((string) file_get_contents(self::WIRING)) as $reference) {
            if (!self::isModuleClass($reference)) {
                $offenders[] = $reference;
            }
        }

        self::assertSame([], $offenders, 'rdzeń zna wnętrze modułu');
    }

    /** Nazwa modułu ostatniej szansy jest daną w `Bootstrap`, nie typem. */
    public function testTheOnlyModuleNameInTheCoreIsTheLastResortIdentifier(): void
    {
        $bootstrap = (string) file_get_contents(self::WIRING);

        self::assertStringContainsString("LAST_RESORT_MODULE = 'browser'", $bootstrap);
        self::assertStringNotContainsString('DirectoryPath', $bootstrap);
        self::assertStringNotContainsString('DirectoryRepositoryInterface', $bootstrap);
    }

    /**
     * Odwołania do przestrzeni `LightManager\Module\…` znalezione w pliku.
     *
     * @return list<string>
     */
    private static function moduleReferences(string $contents): array
    {
        preg_match_all('/LightManager\\\\Module\\\\[A-Za-z0-9_\\\\]+/', $contents, $matches);

        return array_values(array_unique($matches[0]));
    }

    /** Klasa modułu: `LightManager\Module\<Nazwa>\Presentation\<Nazwa>Module`. */
    private static function isModuleClass(string $reference): bool
    {
        return preg_match('/^LightManager\\\\Module\\\\[A-Za-z0-9]+\\\\Presentation\\\\[A-Za-z0-9]+Module$/', $reference) === 1;
    }

    /** @return array<string, string> ścieżka pliku → jego treść */
    private static function coreFiles(): array
    {
        $root = dirname(__DIR__, 2);
        $files = [];

        foreach (self::CORE as $layer) {
            $directory = $root . '/src/' . $layer;

            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $path = str_replace($root . '/', '', $file->getPathname());
                $files[$path] = (string) file_get_contents($file->getPathname());
            }
        }

        return $files;
    }
}
