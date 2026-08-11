<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Cli;

use LightManager\Application\Module\ModuleRegistry;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Presentation\Cli\StartupScreen;
use LightManager\Tests\Support\FakeModule;
use LightManager\Tests\Support\FakeScreenModule;
use LightManager\Tests\Support\ResetsSingletons;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Dno stosu przestało być wpisane w kod: wskazuje je klucz `startupModule`.
 *
 * Cztery drogi awaryjne to cztery testy, bo każda prowadzi do innej poprawki po
 * stronie użytkownika — a jedno zdanie „nie udało się” kazałoby zgadywać, którą.
 * Nazwa modułu ostatniej szansy przychodzi z zewnątrz, więc test podstawia
 * własną i sprawdza przy okazji, że rdzeń nie ma jej wpisanej.
 */
final class StartupScreenTest extends TestCase
{
    use ResetsSingletons;

    private const LAST_RESORT = 'ostatnia-szansa';

    public function testDefaultModuleWithAScreenStandsOnTheFloor(): void
    {
        $startup = $this->choose('inny', [
            new FakeScreenModule(self::LAST_RESORT, new ModuleShortcut('b')),
            new FakeScreenModule('inny', new ModuleShortcut('n')),
        ]);

        self::assertSame('inny', $startup->screen->id());
        self::assertNull($startup->problemKey, 'dostaliśmy to, o co prosiliśmy');
    }

    public function testUnknownModuleFallsBackAndSaysSo(): void
    {
        $startup = $this->choose('nie-ma-takiego', [new FakeScreenModule(self::LAST_RESORT)]);

        self::assertSame(self::LAST_RESORT, $startup->screen->id());
        self::assertSame('module.startup.unknown', $startup->problemKey);
        self::assertSame('nie-ma-takiego', $startup->requested);
    }

    public function testDisabledModuleFallsBackAndSaysSo(): void
    {
        $startup = $this->choose(
            'inny',
            [new FakeScreenModule(self::LAST_RESORT), new FakeScreenModule('inny')],
            ['inny' => ['enabled' => false]],
        );

        self::assertSame(self::LAST_RESORT, $startup->screen->id());
        self::assertSame('module.startup.disabled', $startup->problemKey);
    }

    /** Moduł kolizyjny odpada w całości — także jako moduł domyślny. */
    public function testRejectedModuleFallsBackAndSaysSo(): void
    {
        $startup = $this->choose('inny', [
            new FakeScreenModule(self::LAST_RESORT, new ModuleShortcut('b')),
            new FakeScreenModule('inny', new ModuleShortcut('b')),
        ]);

        self::assertSame(self::LAST_RESORT, $startup->screen->id());
        self::assertSame('module.startup.rejected', $startup->problemKey);
    }

    public function testModuleWithoutAScreenFallsBackAndSaysSo(): void
    {
        $startup = $this->choose('bez-ekranu', [
            new FakeScreenModule(self::LAST_RESORT),
            new FakeModule('bez-ekranu'),
        ]);

        self::assertSame(self::LAST_RESORT, $startup->screen->id());
        self::assertSame('module.startup.screenless', $startup->problemKey);
    }

    /**
     * Moduł ostatniej szansy jest sprawdzany **pierwszy**, więc przy kolizji
     * skrótu odrzucony zostaje ten drugi — nawet gdy stoi na liście przed nim.
     */
    public function testTheLastResortModuleWinsEveryShortcutCollision(): void
    {
        $modules = new ModuleRegistry(
            [
                new FakeScreenModule('inny', new ModuleShortcut('b')),
                new FakeScreenModule(self::LAST_RESORT, new ModuleShortcut('b')),
            ],
            [],
            self::LAST_RESORT,
        );

        self::assertNotNull($modules->find(self::LAST_RESORT));
        self::assertNull($modules->find('inny'));
        self::assertNotNull($modules->rejectionOf('inny'));
    }

    /** Wyłączenie modułu ostatniej szansy w pliku nie ma prawa zadziałać. */
    public function testTheLastResortModuleCannotBeDisabledFromTheConfiguration(): void
    {
        $modules = new ModuleRegistry(
            [new FakeScreenModule(self::LAST_RESORT)],
            [self::LAST_RESORT => ['enabled' => false]],
            self::LAST_RESORT,
        );

        self::assertTrue($modules->isEnabled(self::LAST_RESORT));
        self::assertNotNull($modules->find(self::LAST_RESORT));
    }

    /**
     * Brak modułu ostatniej szansy na liście to **błąd programistyczny**, nie
     * sytuacja użytkownika: aplikacja bez ekranu nie ma czego narysować.
     */
    public function testMissingLastResortModuleIsAProgrammingError(): void
    {
        $this->expectException(LogicException::class);

        $this->choose('cokolwiek', [new FakeScreenModule('inny')]);
    }

    /**
     * @param list<\LightManager\Application\Module\ModuleInterface> $modules
     * @param array<string, array<string, bool|int|string>>          $configuration
     */
    private function choose(string $requested, array $modules, array $configuration = []): StartupScreen
    {
        return StartupScreen::choose(
            new ModuleRegistry($modules, $configuration, self::LAST_RESORT),
            $requested,
            self::LAST_RESORT,
        );
    }
}
