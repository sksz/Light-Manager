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
     * **Pasa podglądu nie ma już w kontrakcie ekranu** (krok 47, D78).
     *
     * Ten test zmienia zdanie po raz drugi i to jest właściwy zapis obu decyzji.
     * Po D76 brzmiał „żaden ekran go nie zamawia”, bo podgląd przeszedł w całości
     * do modułu `FileInfo`, który rysuje go w prawym panelu. Mechanizm bez
     * odbiorcy był jednak złamaniem reguły 13, więc `preview()` wyszło
     * z `ScreenInterface` — i dziś sprawdza się to, czego **nie da się** już
     * zawołać.
     */
    public function testThePreviewStripIsGoneFromTheScreenContract(): void
    {
        $contract = self::methodsOf(\LightManager\Presentation\Ui\ScreenInterface::class);

        self::assertContains('header', $contract, 'strefa górna zostaje — ma odbiorców');
        self::assertNotContains('preview', $contract, 'strefa skrajna wyszła z kontraktu');

        // Układ stracił ją razem z kontraktem, a nie tylko przestał ją zamawiać.
        $layout = self::methodsOf(\LightManager\Presentation\Ui\HudLayout::class);

        self::assertNotContains('previewIsPanel', $layout);
        self::assertContains('listIsPanel', $layout);
    }

    /**
     * @param class-string $type
     *
     * @return list<string>
     */
    private static function methodsOf(string $type): array
    {
        $names = [];

        foreach ((new \ReflectionClass($type))->getMethods() as $method) {
            $names[] = $method->getName();
        }

        return $names;
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
