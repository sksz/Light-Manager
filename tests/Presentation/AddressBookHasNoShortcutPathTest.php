<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * **Główne kryterium kroku 60, sprawdzane maszynowo: książka nie zna wyjątku
 * od własnej reguły.**
 *
 * Dwa zdania, oba wzięte wprost z wad książki usuniętej w kroku poprzednim.
 *
 * **Pierwsze: model widzą wyłącznie komendy i kwerendy.** Ekran, łańcuch okien
 * i fasada odczytu nie mają prawa wymienić `Addresses` — bo wtedy istniałaby
 * druga droga do tych samych operacji, dostępna tylko właścicielowi. Test
 * chodzi po **treści** plików, jak `QueryIsTheOnlyReadPathTest`, bo zakazane
 * jest nie posiadanie referencji, tylko sama możliwość jej trzymania.
 *
 * **Drugie: deklaracja jest jednostronna.** W całym module nie ma ani jednego
 * pytania kwerendą **spoza własnej przestrzeni** — książka nie oddzwania do
 * deklarującego i nie zna nazw jego kwerend. To jest ta wada, z której brały
 * się wszystkie kłopoty z kolejnością w poprzedniej próbie.
 */
final class AddressBookHasNoShortcutPathTest extends TestCase
{
    private const MODULE = __DIR__ . '/../../src/Module/AddressBook';

    /** Pliki, którym wolno widzieć model: same komendy, kwerendy i klasa modułu. */
    private const MODEL_IS_ALLOWED_IN = [
        'Presentation/Command',
        'Presentation/Query',
        'Presentation/AddressBookModule.php',
        'Application',
    ];

    public function testOnlyCommandsAndQueriesSeeTheModel(): void
    {
        $offenders = [];

        foreach (self::files() as $file) {
            $relative = self::relative($file);

            if (self::allowed($relative) || !str_contains((string) file_get_contents($file->getPathname()), 'Addresses ')) {
                continue;
            }

            $offenders[] = $relative;
        }

        self::assertSame(
            [],
            $offenders,
            "Model książki widzą wyłącznie jej komendy i kwerendy; te pliki sięgają po niego obok nich:\n"
                . implode("\n", $offenders),
        );
    }

    public function testTheBookNeverAsksAForeignQuery(): void
    {
        $offenders = [];

        foreach (self::files() as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            preg_match_all("/->ask\(\s*'([^']+)'/", $contents, $matches);

            foreach ($matches[1] as $name) {
                // Nazwa bez kropki nie jest kwerendą, tylko przyrostkiem, do
                // którego fasada dokłada własną przestrzeń — rejestr nazwy bez
                // przedrostka i tak nie zna.
                if (str_contains($name, '.') && !str_starts_with($name, 'address-book.')) {
                    $offenders[] = self::relative($file) . ': ' . $name;
                }
            }
        }

        self::assertSame(
            [],
            $offenders,
            "Deklaracja jest jednostronna — książka nie pyta cudzych kwerend:\n" . implode("\n", $offenders),
        );
    }

    /** Kontrola samego strażnika: czy w ogóle ma czego pilnować. */
    public function testTheGuardHasSomethingToWatch(): void
    {
        self::assertGreaterThanOrEqual(20, count(self::files()));
    }

    /** @return list<SplFileInfo> */
    private static function files(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::MODULE));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        return $files;
    }

    private static function relative(SplFileInfo $file): string
    {
        return str_replace(realpath(self::MODULE) . '/', '', (string) realpath($file->getPathname()));
    }

    private static function allowed(string $relative): bool
    {
        foreach (self::MODEL_IS_ALLOWED_IN as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
