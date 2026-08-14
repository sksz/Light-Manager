<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Diagnostics;

use LightManager\Application\Dto\Language;
use LightManager\Infrastructure\Diagnostics\GoldenFrames;
use LightManager\Infrastructure\Diagnostics\Scenario;
use LightManager\Tests\Support\PinsLanguage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Złote klatki (krok 38): treść każdego scenariusza porównana z plikiem
 * w `tests/Golden/`.
 *
 * Test łapie to, czego nie widać ani w czasach, ani w rozmiarze bloba:
 * przesunięty napis, zgubiony suwak, panel niższy o wiersz. `ScenarioFactory`
 * buduje klatki deterministycznie, a `Primitive::signature()` niesie wszystko,
 * co wpływa na piksele (D34) — więc **nie istnieje zmiana treści, którą ten
 * test przeoczy**.
 *
 * Gdy różnica jest zamierzona, złote pliki odnawia się **jawnie**:
 * `./bin/render-bench --golden-save`. Nigdy automatem — plik regenerowany bez
 * przeczytania różnicy przestaje być testem (D64).
 */
final class GoldenFrameTest extends TestCase
{
    use PinsLanguage;

    private GoldenFrames $golden;

    protected function setUp(): void
    {
        // Treść scenariuszy jest nietłumaczona (D33), ale przypięty język
        // chroni przed pomyłką na przyszłość — złoty plik zależny od locale
        // maszyny byłby testem tej maszyny.
        $this->pinLanguage(Language::English);

        $this->golden = GoldenFrames::default();
    }

    /** @return array<string, array{Scenario}> */
    public static function everyScenario(): array
    {
        $cases = [];

        foreach (Scenario::cases() as $scenario) {
            $cases[$scenario->value] = [$scenario];
        }

        return $cases;
    }

    #[DataProvider('everyScenario')]
    public function testScenarioMatchesItsGoldenFrame(Scenario $scenario): void
    {
        $expected = $this->golden->read($scenario);

        self::assertNotNull(
            $expected,
            sprintf(
                'Brak złotej klatki scenariusza "%s". Zapisz ją: ./bin/render-bench --golden-save',
                $scenario->value,
            ),
        );

        $actual = $this->golden->textOf($scenario);

        if ($actual === $expected) {
            self::assertSame($expected, $actual);

            return;
        }

        self::fail($this->firstDifference($scenario, $expected, $actual));
    }

    /**
     * Komunikat wskazuje **pierwszy różniący się prymityw**, a nie zrzuca obu
     * plików: różnica w klatce o pięćdziesięciu kształtach jest zwykle jedna,
     * a ściana tekstu w wyniku testu każe jej szukać oczami.
     */
    private function firstDifference(Scenario $scenario, string $expected, string $actual): string
    {
        $expectedLines = explode("\n", $expected);
        $actualLines = explode("\n", $actual);

        for ($line = 0; $line < max(count($expectedLines), count($actualLines)); ++$line) {
            $before = $expectedLines[$line] ?? '(brak wiersza)';
            $after = $actualLines[$line] ?? '(brak wiersza)';

            if ($before !== $after) {
                return sprintf(
                    "Klatka \"%s\" różni się od złotej w wierszu %d:\n  złota: %s\n  teraz: %s\n"
                    . 'Jeśli zmiana jest zamierzona: ./bin/render-bench --golden-save --scenarios=%s',
                    $scenario->value,
                    $line + 1,
                    trim($before),
                    trim($after),
                    $scenario->value,
                );
            }
        }

        return sprintf('Klatka "%s" różni się od złotej długością.', $scenario->value);
    }
}
