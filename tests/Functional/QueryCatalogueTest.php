<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * **Każda kwerenda ma opis w obu katalogach napisów** — i mieści się w oknie.
 *
 * Ten sam strażnik, który w kroku 46 pilnował nazw zdarzeń, i z tego samego
 * powodu: kwerenda widoczna dla użytkownika bez wpisu w katalogu pokazuje surowy
 * klucz, a takiego wiersza nie odróżni się w klatce od wiersza opisanego —
 * wygląda po prostu na dziwny napis. Test czyta **oba** języki, bo brak wpisu
 * w angielskim jest tak samo widoczny, tylko dla kogoś innego.
 */
final class QueryCatalogueTest extends TestCase
{
    /**
     * Najdłuższy opis, jaki mieści się obok najdłuższej nazwy kwerendy w oknie
     * o szerokości progowej.
     *
     * Rachunek jest ten sam, co w kroku 46: 80 kolumn okna minus obwódka (2),
     * minus najdłuższa nazwa (`core.module-settings`, 20 znaków), minus odstęp.
     */
    private const DESCRIPTION_BUDGET = 56;

    private ScreenFixture $app;

    protected function setUp(): void
    {
        $directories = (new InMemoryDirectoryRepository())->add('/home', [Entry::file('notatka.txt', 12)]);

        $this->app = new ScreenFixture($directories->get(new DirectoryPath('/home'), false), $directories);
    }

    public function testEveryQueryHasADescriptionInBothCatalogues(): void
    {
        $missing = [];

        foreach ($this->catalogues() as $language => $entries) {
            foreach ($this->app->state->queries()->all() as $query) {
                if (!isset($entries[$query->descriptionKey()])) {
                    $missing[] = $language . ': ' . $query->descriptionKey();
                }
            }
        }

        self::assertSame([], $missing, 'kwerendy bez opisu: ' . implode(', ', $missing));
    }

    /** Argument kwerendy też ma etykietę — pyta o nią parser przy braku wartości. */
    public function testEveryQueryArgumentHasALabelInBothCatalogues(): void
    {
        $missing = [];

        foreach ($this->catalogues() as $language => $entries) {
            foreach ($this->app->state->queries()->all() as $query) {
                foreach ($query->arguments() as $argument) {
                    if (!isset($entries[$argument->labelKey])) {
                        $missing[] = $language . ': ' . $argument->labelKey;
                    }
                }
            }
        }

        self::assertSame([], $missing, 'argumenty bez etykiety: ' . implode(', ', $missing));
    }

    /**
     * Opis dłuższy od budżetu zostałby ucięty w oknie — i widać to dopiero
     * w klatce, więc pilnuje tego test, a nie oko.
     */
    public function testEveryDescriptionFitsTheWindow(): void
    {
        foreach ($this->catalogues() as $language => $entries) {
            foreach ($this->app->state->queries()->all() as $query) {
                $description = $entries[$query->descriptionKey()] ?? '';

                self::assertIsString($description, $language . ': opis nie jest napisem');
                self::assertLessThanOrEqual(
                    self::DESCRIPTION_BUDGET,
                    mb_strlen($description),
                    $language . ': opis ' . $query->descriptionKey() . ' nie zmieści się w oknie',
                );
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>> język → wpisy wszystkich katalogów
     */
    private function catalogues(): array
    {
        $root = dirname(__DIR__, 2);
        $catalogues = [];

        foreach (['pl', 'en'] as $language) {
            $entries = [];
            $paths = glob($root . '/src/Module/*/lang/' . $language . '.php');

            foreach ([$root . '/lang/' . $language . '.php', ...($paths === false ? [] : $paths)] as $path) {
                /** @var array<string, mixed> $catalogue */
                $catalogue = require $path;
                $entries = [...$entries, ...$catalogue];
            }

            $catalogues[$language] = $entries;
        }

        return $catalogues;
    }
}
