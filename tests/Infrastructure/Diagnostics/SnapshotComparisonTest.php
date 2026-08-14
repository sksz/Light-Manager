<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Diagnostics;

use Imagick;
use ImagickDraw;
use ImagickPixel;
use LightManager\Infrastructure\Diagnostics\BenchmarkTrack;
use LightManager\Infrastructure\Diagnostics\Scenario;
use LightManager\Infrastructure\Diagnostics\SnapshotComparison;
use LightManager\Infrastructure\Diagnostics\SnapshotDifference;
use LightManager\Infrastructure\Diagnostics\SnapshotStore;
use LightManager\Infrastructure\Diagnostics\SnapshotVerdict;
use PHPUnit\Framework\TestCase;

/**
 * Regresja wizualna ma być **miarą**, a nie ilustracją — więc reguła „co jest
 * niezgodne” sama potrzebuje testu.
 */
final class SnapshotComparisonTest extends TestCase
{
    private const SIGNATURE = '1000x600px 166x46 theme=grafit';

    private string $directory;

    private SnapshotStore $store;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/lm-zrzuty-' . bin2hex(random_bytes(4));
        $this->store = new SnapshotStore($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testIdenticalFrameMatchesAtTheStrictestThreshold(): void
    {
        $this->saveBaseline();
        $current = $this->image();

        $difference = $this->compare($current);

        self::assertSame(SnapshotVerdict::Match, $difference->verdict);
        self::assertSame(0, $difference->differingPixels);
        self::assertNull($difference->differencePath);
    }

    /**
     * Cztery piksele na stu to dokładnie ten rząd wielkości, o który chodzi:
     * zjedzony obrys jest ułamkiem obrazu i ma zostać złapany.
     */
    public function testAFewChangedPixelsAreCaughtAndTheDifferenceImageIsWritten(): void
    {
        $this->saveBaseline();
        $current = $this->image(spot: true);

        $difference = $this->compare($current);

        self::assertSame(SnapshotVerdict::Differs, $difference->verdict);
        self::assertSame(4, $difference->differingPixels);
        self::assertNotNull($difference->differencePath);
        self::assertFileExists($difference->differencePath);
        self::assertTrue($difference->verdict->isFailure());
    }

    /** Próg podnosi się świadomie — tor okienkowy ma go luźniejszego z natury. */
    public function testALooseThresholdAcceptsTheSameDifference(): void
    {
        $this->saveBaseline();

        $difference = $this->compare($this->image(spot: true), thresholdPerMille: 100.0);

        self::assertSame(SnapshotVerdict::Match, $difference->verdict);
        self::assertSame(4, $difference->differingPixels);
        self::assertNull($difference->differencePath, 'obraz różnicy powstaje tylko przy niezgodności');
    }

    /** Brak wzorca to prośba o `--png-save`, a nie regresja. */
    public function testMissingBaselineIsNotAFailure(): void
    {
        $difference = $this->compare($this->image());

        self::assertSame(SnapshotVerdict::Missing, $difference->verdict);
        self::assertFalse($difference->verdict->isFailure());
    }

    public function testDifferentCanvasSizeIsNotComparedAtAll(): void
    {
        $this->saveBaseline();

        $difference = $this->compare($this->image(width: 20));

        self::assertSame(SnapshotVerdict::Resized, $difference->verdict);
    }

    /**
     * Wzorzec z innego motywu porównany „na obraz” pokazywałby regresję wyglądu
     * tam, gdzie zmieniła się wyłącznie prośba użytkownika. Wzorce liczbowe mają
     * tę regułę od kroku 16 — obrazy dostają ją tutaj.
     */
    public function testBaselineFromAnotherConfigurationIsRefused(): void
    {
        $this->saveBaseline();

        $difference = (new SnapshotComparison($this->store))->compare(
            $this->image(),
            BenchmarkTrack::Sixel,
            Scenario::ChromeWithText,
            0.0,
            'inna konfiguracja',
        );

        self::assertSame(SnapshotVerdict::Incomparable, $difference->verdict);
        self::assertFalse($difference->verdict->isFailure());
    }

    private function compare(Imagick $current, float $thresholdPerMille = 0.0): SnapshotDifference
    {
        try {
            return (new SnapshotComparison($this->store))->compare(
                $current,
                BenchmarkTrack::Sixel,
                Scenario::ChromeWithText,
                $thresholdPerMille,
                self::SIGNATURE,
            );
        } finally {
            $current->clear();
        }
    }

    private function saveBaseline(): void
    {
        $baseline = $this->image();

        try {
            $this->store->save($baseline, BenchmarkTrack::Sixel, Scenario::ChromeWithText, self::SIGNATURE);
        } finally {
            $baseline->clear();
        }
    }

    /** Płótno 10×10; `spot` maluje w nim kwadrat 2×2 w innym kolorze. */
    private function image(bool $spot = false, int $width = 10): Imagick
    {
        $image = new Imagick();
        $image->newImage($width, 10, new ImagickPixel('#1c1f26'), 'png');

        if ($spot) {
            $draw = new ImagickDraw();
            $draw->setFillColor(new ImagickPixel('#e0645c'));
            $draw->rectangle(2, 2, 3, 3);
            $image->drawImage($draw);
        }

        return $image;
    }
}
