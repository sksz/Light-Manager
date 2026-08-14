<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Cli;

use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Presentation\Cli\Bootstrap;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * Kontrakt trzech stref, widziany od strony **wszystkich** ekranów naraz.
 *
 * Do kroku 20 górny pas rysował rdzeń, bezwarunkowo i zawsze ścieżką: ekran mógł
 * co najwyżej dopisać do niej końcówkę. Od kroku 21 pas jest polem ekranu i każdy
 * stawia w nim to, co ma własnego — przeglądarka ścieżkę, pomoc nazwę i wersję,
 * ustawienia położenie pliku konfiguracyjnego. Ten test pilnuje, że żaden z nich
 * nie zostawia go pustym, bo pusty pas byłby zmianą wyglądu klatki.
 */
final class ScreenZonesTest extends TestCase
{
    private ScreenFixture $app;

    protected function setUp(): void
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/home', [Entry::directory('dokumenty'), Entry::file('notatka.txt', 12)])
            ->add('/home/dokumenty', []);

        $this->app = new ScreenFixture($directories->get(new DirectoryPath('/home'), false), $directories);
    }

    public function testHelpScreenNamesTheApplicationInItsHeader(): void
    {
        // Typ zwracany zawęża tu `?ScreenZone` do `ScreenZone` i to jest treść
        // deklaracji: pomoc **zawsze** ma co postawić w górnym pasie.
        $header = $this->app->help->header();

        self::assertSame('layout.zone.about', $header->labelKey);
        self::assertStringContainsString(Bootstrap::VERSION, self::textOf($header));
    }

    public function testSettingsScreenPointsAtTheConfigurationFileInItsHeader(): void
    {
        $header = $this->app->settings->header();

        self::assertSame('layout.zone.settings.file', $header->labelKey);
        self::assertNotSame('', self::textOf($header));
    }

    /**
     * **Pasa podglądu nie zamawia dziś żaden ekran** (D76).
     *
     * Do tej zmiany zamawiała go przeglądarka i była jedyna; podgląd przeszedł
     * w całości do modułu `FileInfo`, który rysuje go **w prawym panelu**, a nie
     * w strefie skrajnej. Strefa zostaje w kontrakcie ekranu, bo `null` jest jej
     * poprawną odpowiedzią od kroku 21 — ale nie ma odtąd ani jednego użytkownika,
     * i ten test jest miejscem, w którym to widać.
     */
    public function testNoScreenOrdersThePreviewStripAnyMore(): void
    {
        self::assertNull($this->app->browser->preview());
        self::assertNull($this->app->help->preview());
        self::assertNull($this->app->settings->preview());
        self::assertNull($this->app->fileInfo->preview());
    }

    private static function textOf(\LightManager\Presentation\Ui\ScreenZone $zone): string
    {
        return implode('', self::textsOf($zone->content->draw(new Rect(0, 2, 1, 80))));
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<string>
     */
    private static function textsOf(array $primitives): array
    {
        $texts = [];

        foreach ($primitives as $primitive) {
            if ($primitive instanceof TextRun) {
                $texts[] = $primitive->text;
            }
        }

        return $texts;
    }
}
