<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Rendering;

use LightManager\Application\Ui\Primitive\Primitive;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Główne kryterium kroku 35, sprawdzane maszynowo: **tabela tłumaczeń
 * renderera okienkowego jest kompletna wobec słownika prymitywów.**
 * „Prymityw niezaimplementowany” nie istnieje.
 *
 * Test chodzi po katalogu prymitywów i po treści metody rozsyłającej — nie po
 * zachowaniu, bo rysowanie wymagałoby okna i kontekstu GL. To ta sama metoda,
 * którą `CoreKnowsNothingAboutFilesTest` sprawdza granice warstw: sprawdzalne
 * jest to, co widać w źródle, i tylko to.
 *
 * Sixelowy `SixelFrameEncoder` idzie tą samą drogą — od kroku 35 nowy prymityw
 * obowiązuje **trzy** renderery naraz, a dwa z nich pilnuje ten test. Trzeci,
 * tekstowy, świadomie degraduje część kształtów (nawias narożny i suwak nie
 * mają odpowiednika w siatce znakowej), więc kompletności się od niego nie
 * wymaga — i to jest jedyny powód, dla którego go tu nie ma.
 */
final class PrimitiveTranslationTableTest extends TestCase
{
    private const TRANSLATORS = [
        'src/Infrastructure/Rendering/OpenGlFrameRenderer.php' => 'drawPrimitive',
        'src/Infrastructure/Imagick/SixelFrameEncoder.php' => 'drawPrimitive',
    ];

    /** @return array<string, array{string, string}> */
    public static function translators(): array
    {
        $cases = [];

        foreach (self::TRANSLATORS as $path => $method) {
            $cases[basename($path, '.php')] = [$path, $method];
        }

        return $cases;
    }

    /**
     * @param string $path   plik tłumacza, względem korzenia repozytorium
     * @param string $method metoda rozsyłająca prymitywy
     */
    #[DataProvider('translators')]
    public function testEveryPrimitiveHasATranslation(string $path, string $method): void
    {
        $source = $this->bodyOf($path, $method);
        $missing = [];

        foreach (self::primitiveNames() as $primitive) {
            if (!str_contains($source, $primitive)) {
                $missing[] = $primitive;
            }
        }

        self::assertSame([], $missing, $path . ': prymityw bez tłumaczenia');
    }

    /** Słownik nie może się skurczyć niepostrzeżenie — inaczej test wyżej przechodziłby pusty. */
    public function testDictionaryIsNotEmpty(): void
    {
        $names = self::primitiveNames();

        self::assertContains('TextRun', $names);
        self::assertContains('Bitmap', $names);
        self::assertGreaterThanOrEqual(6, count($names));
    }

    /** @return list<string> nazwy klas implementujących `Primitive`, bez przestrzeni nazw */
    private static function primitiveNames(): array
    {
        $directory = dirname(__DIR__, 3) . '/src/Application/Ui/Primitive';
        $names = [];

        foreach ((array) glob($directory . '/*.php') as $file) {
            $name = basename((string) $file, '.php');
            $class = 'LightManager\\Application\\Ui\\Primitive\\' . $name;

            if (class_exists($class) && (new ReflectionClass($class))->implementsInterface(Primitive::class)) {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }

    private function bodyOf(string $path, string $method): string
    {
        $absolute = dirname(__DIR__, 3) . '/' . $path;

        self::assertFileExists($absolute);

        $lines = (array) file($absolute);
        $reflection = new ReflectionMethod(
            $this->classOf($absolute),
            $method,
        );

        return implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1,
        ));
    }

    private function classOf(string $absolute): string
    {
        $source = (string) file_get_contents($absolute);
        $namespace = [];
        $class = [];

        if (preg_match('/^namespace\s+([^;]+);/m', $source, $namespace) !== 1
            || preg_match('/^final class (\w+)/m', $source, $class) !== 1) {
            self::fail('Nie rozpoznano klasy tłumacza w ' . $absolute);
        }

        return $namespace[1] . '\\' . $class[1];
    }
}
