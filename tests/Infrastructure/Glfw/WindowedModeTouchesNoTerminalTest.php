<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Glfw;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Kryterium kroku 34, sprawdzane maszynowo: **tor okienkowy nie dotyka
 * terminala** — bez trybu surowego, bez zapytania DA1, bez alternatywnego
 * bufora, bez jednej sekwencji sterującej na STDOUT.
 *
 * Test chodzi po źródłach toru okienkowego (katalog `Infrastructure/Glfw`
 * i renderer okienkowy) i pilnuje, żeby nie wymieniały ani usług terminalowych,
 * ani samych uchwytów strumieni. Wzorem `CoreKnowsNothingAboutFilesTest`:
 * sprawdzamy wystąpienia nazw, bo tylko to daje się sprawdzić bez zgadywania.
 */
final class WindowedModeTouchesNoTerminalTest extends TestCase
{
    private const WINDOWED_SOURCES = [
        'src/Infrastructure/Glfw',
        'src/Infrastructure/Rendering/OpenGlFrameRenderer.php',
    ];

    /**
     * Nazwy, których tor okienkowy nie ma prawa wymienić: usługi terminalowe
     * (każda ciągnie za sobą efekt uboczny na terminalu), strumienie procesu
     * i bajt otwierający sekwencję sterującą.
     */
    private const FORBIDDEN = [
        'TerminalService',
        'SixelCapabilityService',
        'TerminalSizeService',
        'KeySequenceParser',
        'STDOUT',
        'STDIN',
        'stty',
        "\e[",
    ];

    public function testWindowedSourcesNeverMentionTheTerminal(): void
    {
        $offenders = [];

        foreach (self::windowedFiles() as $path => $contents) {
            $code = self::withoutComments($contents);

            foreach (self::FORBIDDEN as $needle) {
                if (str_contains($code, $needle)) {
                    $offenders[] = $path . ' → ' . addcslashes($needle, "\e");
                }
            }
        }

        self::assertSame([], $offenders, 'tor okienkowy dotyka terminala');
    }

    /**
     * Komentarze wolno pominąć: dokumentacja toru okienkowego z natury
     * przywołuje terminalowe wzorce („wzorem `TerminalService`…”), a dotykać
     * terminala może wyłącznie kod.
     */
    private static function withoutComments(string $contents): string
    {
        $code = '';

        foreach (token_get_all($contents) as $token) {
            if (is_string($token)) {
                $code .= $token;

                continue;
            }

            if ($token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT) {
                $code .= $token[1];
            }
        }

        return $code;
    }

    /** @return iterable<string, string> ścieżka względna → treść pliku */
    private static function windowedFiles(): iterable
    {
        $root = dirname(__DIR__, 3);

        foreach (self::WINDOWED_SOURCES as $source) {
            $absolute = $root . DIRECTORY_SEPARATOR . $source;

            if (is_file($absolute)) {
                yield $source => (string) file_get_contents($absolute);

                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absolute));

            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    yield $source . '/' . $file->getFilename() => (string) file_get_contents($file->getPathname());
                }
            }
        }
    }
}
