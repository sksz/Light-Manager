<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Module\Docker\Infrastructure\DockerIgnore;
use PHPUnit\Framework\TestCase;

/**
 * Wzorce `.dockerignore` (krok 51).
 *
 * Plik nie jest ozdobą, tylko **warunkiem używalności budowy**: bez niego
 * pierwszy lepszy projekt Node.js wysłałby demonowi `node_modules`, czyli setki
 * megabajtów, których budowa i tak nie użyje. Test pilnuje przy tym granicy
 * uproszczenia — czytamy podzbiór składni, a nie pełną semantykę Dockera,
 * i różnica ma się objawiać **rozmiarem kontekstu, a nie wynikiem budowy**.
 */
final class DockerIgnoreTest extends TestCase
{
    /**
     * Wzorzec katalogu wyklucza **wszystko, co w nim leży**.
     *
     * Bez dopasowania do przodków ścieżki najczęstszy wpis świata Node.js nie
     * robiłby nic: `fnmatch` sam z siebie nie przepuszcza `*` przez ukośnik.
     */
    public function testADirectoryPatternExcludesItsContents(): void
    {
        $ignore = DockerIgnore::of("node_modules\n");

        self::assertTrue($ignore->excludes('node_modules'));
        self::assertTrue($ignore->excludes('node_modules/express/index.js'));
        self::assertFalse($ignore->excludes('src/index.js'));
    }

    public function testCommentsAndBlankLinesAreSkipped(): void
    {
        $ignore = DockerIgnore::of("# to jest komentarz\n\n*.log\n");

        self::assertTrue($ignore->excludes('debug.log'));
        self::assertFalse($ignore->excludes('# to jest komentarz'));
    }

    /** Wyjątek `!` ma pierwszeństwo — różnica działa w stronę bezpieczną. */
    public function testAnExceptionKeepsTheFileInTheContext(): void
    {
        $ignore = DockerIgnore::of("*.log\n!wazny.log\n");

        self::assertTrue($ignore->excludes('debug.log'));
        self::assertFalse($ignore->excludes('wazny.log'));
    }

    /** `/build/` i `build` znaczą to samo — ukośniki na brzegach nic nie wnoszą. */
    public function testLeadingAndTrailingSlashesDoNotMatter(): void
    {
        $ignore = DockerIgnore::of("/build/\n");

        self::assertTrue($ignore->excludes('build/app.js'));
    }

    /** Brak pliku znaczy „nic nie wykluczaj” — a nie „wyklucz wszystko”. */
    public function testWithoutAFileNothingIsExcluded(): void
    {
        $ignore = DockerIgnore::readFrom(sys_get_temp_dir() . '/lm-nie-ma-takiego-katalogu-' . uniqid());

        self::assertFalse($ignore->excludes('cokolwiek'));
    }
}
