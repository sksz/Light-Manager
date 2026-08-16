<?php

declare(strict_types=1);

namespace LightManager\Tests\Application\Module;

use LightManager\Application\Module\ModuleRegistry;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Tests\Support\FakeEnvironmentModule;
use LightManager\Tests\Support\FakeModule;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Rejestr modułów: kto wchodzi, kto odpada i dlaczego.
 *
 * Test jest jedynym miejscem, w którym da się to sprawdzić **zanim zobaczy to
 * użytkownik**: moduły są wbudowane w repozytorium, a ich lista stoi w kodzie,
 * więc kolizja skrótu jest błędem, który ma wyjść przy `composer test`, a nie
 * przy uruchomieniu.
 */
final class ModuleRegistryTest extends TestCase
{
    /**
     * **Piąty powód odrzucenia i pierwszy niezależny od autora modułu** (krok 48,
     * D87 nr 11).
     *
     * Cztery poprzednie dotyczą samej deklaracji i w wydanej aplikacji nie
     * zdarzają się nigdy. Ten zależy od maszyny użytkownika — i to on sprawił,
     * że powody odrzucenia zaczęły trafiać przed oczy kogokolwiek.
     */
    public function testModuleWithoutItsEnvironmentIsRejectedWithItsOwnReason(): void
    {
        $registry = new ModuleRegistry([new FakeEnvironmentModule('narzedziowy', 'module.narzedziowy.brak')]);

        self::assertSame([], $registry->accepted());
        self::assertNull($registry->find('narzedziowy'));

        $rejection = $registry->rejectionOf('narzedziowy');
        self::assertNotNull($rejection);
        self::assertSame('module.narzedziowy.brak', $rejection->reasonKey, 'powód podaje moduł, nie rdzeń');
    }

    public function testModuleWithItsEnvironmentEntersNormally(): void
    {
        $registry = new ModuleRegistry([new FakeEnvironmentModule('narzedziowy', null, new ModuleShortcut('n'))]);

        self::assertCount(1, $registry->accepted());
        self::assertSame([], $registry->rejections());
        self::assertSame(['n'], array_keys($registry->shortcuts()));
    }

    /**
     * **Moduł odrzucony przez środowisko nie zajmuje litery.**
     *
     * Sprawdzenie środowiska stoi przed sprawdzeniem skrótu właśnie po to:
     * inaczej moduł, który i tak nie wejdzie, zabrałby literę komuś, kto by
     * działał — i użytkownik bez zainstalowanego narzędzia straciłby przy okazji
     * drugi moduł.
     */
    public function testARejectedModuleLeavesItsLetterFree(): void
    {
        $registry = new ModuleRegistry([
            new FakeEnvironmentModule('narzedziowy', 'module.narzedziowy.brak', new ModuleShortcut('n')),
            new FakeModule('inny', new ModuleShortcut('n')),
        ]);

        self::assertCount(1, $registry->accepted());
        self::assertNotNull($registry->find('inny'));
        self::assertSame(['n'], array_keys($registry->shortcuts()));
        self::assertSame($registry->find('inny'), $registry->shortcuts()['n']);
    }

    /** Moduł wyłączony **nie jest pytany o środowisko** — wyłączenie wyprzedza wszystko. */
    public function testADisabledModuleIsNotAskedAboutItsEnvironment(): void
    {
        $module = new FakeEnvironmentModule('narzedziowy', 'module.narzedziowy.brak');
        $registry = new ModuleRegistry([$module], ['narzedziowy' => ['enabled' => false]]);

        self::assertSame([], $registry->rejections(), 'wyłączony nie jest odrzucony');
        self::assertSame(0, $module->asked);
    }

    public function testModuleWithoutAnyCapabilityIsLegal(): void
    {
        $registry = new ModuleRegistry([new FakeModule('samotny')]);

        self::assertCount(1, $registry->accepted());
        self::assertSame([], $registry->rejections());
        self::assertSame([], $registry->shortcuts(), 'moduł bez skrótu niczego nie zajmuje');
    }

    public function testShortcutMapsToTheModuleThatDeclaredIt(): void
    {
        $module = new FakeModule('alfa', new ModuleShortcut('a'));
        $registry = new ModuleRegistry([$module]);

        self::assertSame(['a' => $module], $registry->shortcuts());
    }

    /** @return array<string, array{string}> */
    public static function forbiddenLetters(): array
    {
        $cases = [];

        foreach (ModuleRegistry::FORBIDDEN_CHARACTERS as $letter) {
            $cases['Ctrl+' . strtoupper($letter)] = [$letter];
        }

        return $cases;
    }

    /**
     * Sześć liter jest zajętych przez terminal: dwie przez sygnały (`Ctrl+C`,
     * `Ctrl+Z`), cztery przez klawisze nazwane, które przychodzą tym samym
     * bajtem (Backspace, Tab, dwa razy Enter).
     */
    #[DataProvider('forbiddenLetters')]
    public function testForbiddenLetterRejectsTheWholeModule(string $letter): void
    {
        $registry = new ModuleRegistry([new FakeModule('alfa', new ModuleShortcut($letter))]);

        self::assertSame([], $registry->accepted());
        self::assertSame('module.rejected.character', $registry->rejectionOf('alfa')?->reasonKey);
    }

    public function testTwentyLettersAreLeftForModules(): void
    {
        self::assertCount(20, ModuleRegistry::allowedCharacters());
        self::assertContains('s', ModuleRegistry::allowedCharacters(), 'tryb surowy wyłącza sterowanie przepływem');
        self::assertContains('q', ModuleRegistry::allowedCharacters());
        self::assertNotContains('c', ModuleRegistry::allowedCharacters());
    }

    public function testShortcutWithoutCtrlIsRejected(): void
    {
        $registry = new ModuleRegistry([new FakeModule('alfa', new ModuleShortcut('a', ctrl: false))]);

        self::assertSame('module.rejected.character', $registry->rejectionOf('alfa')?->reasonKey);
    }

    /** Kolizja odrzuca **cały** moduł, nie tylko jego skrót. */
    public function testCollidingShortcutRejectsTheSecondModuleEntirely(): void
    {
        $registry = new ModuleRegistry([
            new FakeModule('pierwszy', new ModuleShortcut('d')),
            new FakeModule('drugi', new ModuleShortcut('d')),
        ]);

        self::assertCount(1, $registry->accepted());
        self::assertSame('pierwszy', $registry->accepted()[0]->id());
        self::assertSame('module.rejected.taken', $registry->rejectionOf('drugi')?->reasonKey);
        self::assertNull($registry->find('drugi'), 'odrzucony moduł nie wnosi ani zakładki, ani komend');
    }

    /** @return array<string, array{string}> */
    public static function invalidIdentifiers(): array
    {
        return [
            'wielka litera' => ['FileInfo'],
            'cyfra na początku' => ['1modul'],
            'podkreślenie' => ['file_info'],
            'pusty' => [''],
            'kropka' => ['file.info'],
        ];
    }

    #[DataProvider('invalidIdentifiers')]
    public function testIdentifierMustMatchThePattern(string $id): void
    {
        $registry = new ModuleRegistry([new FakeModule($id)]);

        self::assertSame([], $registry->accepted());
        self::assertSame('module.rejected.id', $registry->rejectionOf($id)?->reasonKey);
    }

    public function testDigitsAndDashesAreAllowedAfterTheFirstLetter(): void
    {
        $registry = new ModuleRegistry([new FakeModule('file-info2')]);

        self::assertCount(1, $registry->accepted());
    }

    public function testRepeatedIdentifierRejectsTheSecondModule(): void
    {
        $registry = new ModuleRegistry([new FakeModule('alfa'), new FakeModule('alfa')]);

        self::assertCount(1, $registry->accepted());
        self::assertSame('module.rejected.duplicate', $registry->rejectionOf('alfa')?->reasonKey);
    }

    public function testDisabledModuleIsSiftedOutButStaysOnTheList(): void
    {
        $registry = new ModuleRegistry(
            [new FakeModule('alfa', new ModuleShortcut('a'))],
            ['alfa' => ['enabled' => false]],
        );

        self::assertSame([], $registry->accepted());
        self::assertCount(1, $registry->declared(), 'wyłączony moduł widać w zakładce „Moduły”');
        self::assertFalse($registry->isEnabled('alfa'));
        self::assertNull($registry->rejectionOf('alfa'), 'wyłączenie nie jest odrzuceniem');
    }

    /**
     * Wyłączenie modułu może kolizję tylko **usunąć**, nigdy stworzyć — dlatego
     * moduł wyłączony nie jest w ogóle sprawdzany.
     */
    public function testDisablingOneModuleFreesItsShortcutForAnother(): void
    {
        $registry = new ModuleRegistry(
            [
                new FakeModule('pierwszy', new ModuleShortcut('d')),
                new FakeModule('drugi', new ModuleShortcut('d')),
            ],
            ['pierwszy' => ['enabled' => false]],
        );

        self::assertCount(1, $registry->accepted());
        self::assertSame('drugi', $registry->accepted()[0]->id());
        self::assertSame([], $registry->rejections());
    }

    public function testMissingSwitchMeansEnabled(): void
    {
        $registry = new ModuleRegistry([new FakeModule('alfa')], ['alfa' => ['inne' => 7]]);

        self::assertTrue($registry->isEnabled('alfa'));
    }

    /** Plik konfiguracji ruszony ręcznie nie ma prawa wywrócić startu. */
    public function testNonBooleanSwitchIsTreatedAsEnabled(): void
    {
        $registry = new ModuleRegistry([new FakeModule('alfa')], ['alfa' => ['enabled' => 'tak']]);

        self::assertTrue($registry->isEnabled('alfa'));
        self::assertCount(1, $registry->accepted());
    }

    /**
     * Komplet modułów wbudowanych **musi** przejść bez odrzucenia — to jest ten
     * test, który łapie kolizję, zanim zobaczy ją użytkownik.
     *
     * Zestaw budowany jest tą samą drogą, co w `Bootstrap` (przez `ScreenFixture`),
     * więc moduł podłączony ręcznym obejściem nie przemknąłby tu niezauważony.
     */
    public function testBuiltInModulesFormAValidSet(): void
    {
        $directories = (new InMemoryDirectoryRepository())->add('/', [Entry::file('plik.txt', 1)]);
        $app = new ScreenFixture($directories->get(new DirectoryPath('/'), false), $directories);

        self::assertSame([], $app->modules->rejections());
        self::assertSame(
            ['b', 'd', 'a', 's', 'o'],
            array_keys($app->modules->shortcuts()),
            'przeglądarka trzyma Ctrl+B, FileInfo — Ctrl+D, dźwięk — Ctrl+A (krok 45), '
            . 'sesja zdalna — Ctrl+S (krok 48), Docker — Ctrl+O (krok 51)',
        );
        self::assertNotNull($app->module('browser'));
        self::assertNotNull($app->module('file-info'));
        self::assertNotNull($app->module('audio'));
        self::assertNotNull($app->module('ssh'));
        self::assertNotNull($app->module('docker'));
    }
}
