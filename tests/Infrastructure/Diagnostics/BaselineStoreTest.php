<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Diagnostics;

use LightManager\Infrastructure\Diagnostics\BaselineSnapshot;
use LightManager\Infrastructure\Diagnostics\BaselineStore;
use LightManager\Infrastructure\Diagnostics\BenchmarkOptions;
use LightManager\Infrastructure\Diagnostics\DiagnosticsException;
use LightManager\Infrastructure\Diagnostics\DiagnosticsProblem;
use LightManager\Infrastructure\Diagnostics\EnvironmentMetadata;
use LightManager\Infrastructure\Diagnostics\ScenarioMedians;
use PHPUnit\Framework\TestCase;

/**
 * Wzorzec ma przeżyć w repozytorium dłużej niż sesja, w której powstał, więc
 * najważniejsze jest tu jedno: to, co zapisano, daje się odczytać z powrotem bez
 * zgubienia liczb.
 */
final class BaselineStoreTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'light-manager-baseline-' . uniqid();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->directory);
    }

    public function testSavedBaselineIsReadBackUnchanged(): void
    {
        $store = new BaselineStore($this->directory);
        $path = $store->save($this->snapshot(), 'proba');

        $loaded = $store->load($path);

        self::assertSame($this->snapshot()->options->signature(), $loaded->options->signature());
        self::assertSame('8.3.11', $loaded->environment->phpVersion);
        self::assertArrayHasKey('text', $loaded->scenarios);
        self::assertEqualsWithDelta(268.3, $loaded->scenarios['text']->totalMilliseconds, 0.001);
        self::assertSame(20051, $loaded->scenarios['text']->blobBytes);
        self::assertFalse($loaded->scenarios['text']->unstable);
    }

    /** Data w nazwie układa katalog chronologicznie i pozwala śledzić postęp między krokami planu. */
    public function testFileNameCarriesTheDateAndTheGivenName(): void
    {
        $path = (new BaselineStore($this->directory))->save($this->snapshot(), 'Po Optymalizacji');

        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}-po-optymalizacji\.json$/',
            basename($path),
        );
    }

    public function testNewestPicksTheLastFileByName(): void
    {
        mkdir($this->directory, 0o755, true);

        foreach (['2026-01-01-a.json', '2026-08-09-b.json', '2026-03-05-c.json'] as $name) {
            file_put_contents($this->directory . DIRECTORY_SEPARATOR . $name, '{"scenarios":{}}');
        }

        self::assertSame('2026-08-09-b.json', basename((new BaselineStore($this->directory))->newest()));
    }

    /**
     * Od kroku 35 katalog mieszczą dwa nieporównywalne tory naraz, więc wybór
     * bez wskazanego pliku musi trafić we wzorzec **własnego** toru — inaczej
     * `--compare` terminalowy odbijałby się od wzorca okienkowego, który
     * przypadkiem jest nowszy.
     */
    public function testNewestPrefersABaselineComparableWithTheCurrentRun(): void
    {
        $store = new BaselineStore($this->directory);
        $terminal = new BenchmarkOptions();
        $windowed = new BenchmarkOptions(windowed: true);

        $store->save($this->snapshot($terminal), '2000-01-01-terminal');
        $store->save($this->snapshot($windowed), 'zzz-window');

        // Alfabetycznie ostatni jest wzorzec okienkowy, ale tor terminalowy
        // ma dostać swój.
        self::assertSame(
            $terminal->signature(),
            $store->load($store->newest($terminal))->options->signature(),
        );
        self::assertSame(
            $windowed->signature(),
            $store->load($store->newest($windowed))->options->signature(),
        );
    }

    /** Gdy nic nie pasuje, wraca najnowszy w ogóle — odmowa z dwiema konfiguracjami mówi więcej niż „brak wzorca”. */
    public function testNewestFallsBackToTheLatestWhenNothingIsComparable(): void
    {
        $store = new BaselineStore($this->directory);
        $store->save($this->snapshot(new BenchmarkOptions()), 'jedyny');

        $path = $store->newest(new BenchmarkOptions(themeName: 'papier'));

        self::assertStringContainsString('jedyny', $path);
    }

    public function testMissingFileIsReportedAsMissingBaseline(): void
    {
        try {
            (new BaselineStore($this->directory))->load($this->directory . '/nie-ma.json');
            self::fail('Brakujący plik powinien skończyć się wyjątkiem.');
        } catch (DiagnosticsException $exception) {
            self::assertSame(DiagnosticsProblem::BaselineMissing, $exception->problem);
        }
    }

    public function testEmptyDirectoryHasNoNewestBaseline(): void
    {
        try {
            (new BaselineStore($this->directory))->newest();
            self::fail('Pusty katalog nie ma najnowszego wzorca.');
        } catch (DiagnosticsException $exception) {
            self::assertSame(DiagnosticsProblem::BaselineMissing, $exception->problem);
        }
    }

    /** Plik, który jest poprawnym JSON-em, ale nie wzorcem, też musi zostać odrzucony. */
    public function testFileWithoutScenariosIsNotABaseline(): void
    {
        mkdir($this->directory, 0o755, true);
        $path = $this->directory . DIRECTORY_SEPARATOR . 'obcy.json';
        file_put_contents($path, '{"cokolwiek": 1}');

        try {
            (new BaselineStore($this->directory))->load($path);
            self::fail('Plik bez scenariuszy nie jest wzorcem.');
        } catch (DiagnosticsException $exception) {
            self::assertSame(DiagnosticsProblem::BaselineUnreadable, $exception->problem);
        }
    }

    public function testBrokenJsonIsRejected(): void
    {
        mkdir($this->directory, 0o755, true);
        $path = $this->directory . DIRECTORY_SEPARATOR . 'popsuty.json';
        file_put_contents($path, '{"scenarios": ');

        try {
            (new BaselineStore($this->directory))->load($path);
            self::fail('Niepoprawny JSON nie jest wzorcem.');
        } catch (DiagnosticsException $exception) {
            self::assertSame(DiagnosticsProblem::BaselineUnreadable, $exception->problem);
        }
    }

    private function snapshot(?BenchmarkOptions $options = null): BaselineSnapshot
    {
        return new BaselineSnapshot(
            $options ?? new BenchmarkOptions(),
            new EnvironmentMetadata('8.3.11', 'ImageMagick 6.9', 'DejaVu-Sans-Mono', '2026-08-09T10:00:00+00:00'),
            ['text' => new ScenarioMedians(210.7, 55.9, 1.6, 268.3, 20051, false)],
        );
    }
}
