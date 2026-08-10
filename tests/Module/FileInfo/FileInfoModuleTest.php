<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\FileInfo;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandTransition;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Domain\ValueObject\DirectoryPath;
use LightManager\Domain\ValueObject\Entry;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Module\FileInfo\Application\UseCase\InspectSelectedEntryUseCase;
use LightManager\Module\FileInfo\Presentation\Command\JumpCommand;
use LightManager\Module\FileInfo\Presentation\FileInfoScreen;
use LightManager\Presentation\Ui\Transition;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubFileInspector;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Pierwszy moduł projektu po przeprowadzce z rdzenia.
 *
 * Test pilnuje trzech rzeczy naraz: że opis pliku działa jak wcześniej, że
 * moduł deklaruje wszystkie pięć punktów zaczepienia i że jego komenda —
 * pierwsza w projekcie z podpowiedziami liczonymi na żądanie — naprawdę czyta
 * dysk, a nie listę policzoną przy starcie.
 */
final class FileInfoModuleTest extends TestCase
{
    private InMemoryDirectoryRepository $directories;

    private ScreenFixture $app;

    protected function setUp(): void
    {
        $this->directories = (new InMemoryDirectoryRepository())
            ->add('/', [Entry::directory('home')])
            ->add('/home', [Entry::directory('dokumenty'), Entry::directory('dane'), Entry::file('notatka.txt', 12)])
            ->add('/home/dokumenty', [Entry::file('umowa.pdf', 2048)])
            ->add('/home/dane', []);

        $this->app = new ScreenFixture(
            $this->directories->get(new DirectoryPath('/home'), false),
            $this->directories,
        );
    }

    public function testModuleDeclaresEveryHookOfTheContract(): void
    {
        $module = $this->app->module('file-info');

        self::assertNotNull($module);
        self::assertSame('file-info', $module->id());

        $shortcut = $module->shortcut();

        self::assertNotNull($shortcut);
        self::assertSame('d', $shortcut->character);
        self::assertTrue($shortcut->ctrl);
        self::assertDirectoryExists((string) $module->translations(), 'napisy leżą w katalogu modułu');
    }

    public function testSettingsTabDeclaresBothPositions(): void
    {
        $module = $this->app->module('file-info');

        self::assertInstanceOf(\LightManager\Application\Module\ProvidesSettingsTab::class, $module);

        $keys = array_map(
            static fn (\LightManager\Application\Module\ModuleSetting $setting): string => $setting->key,
            $module->settingsTab()->settings,
        );

        self::assertSame(['timeout', 'arguments'], $keys);
    }

    public function testCommandsStayInsideTheModuleNamespace(): void
    {
        $command = $this->app->commandRegistry->find('file-info.jump');

        self::assertNotNull($command);
        self::assertSame('module.file-info.command.jump', $command->descriptionKey());
    }

    /** Ekran modułu opisuje **plik**; katalog mówi tylko, że nie ma czego opisać. */
    public function testScreenDescribesOnlyFiles(): void
    {
        $inspector = new StubFileInspector('ASCII text');
        $screen = new FileInfoScreen(
            new InspectSelectedEntryUseCase($inspector, new InMemorySettings()),
            new StubTranslator(),
        );

        $screen->useContext(new ModuleContext('/home', 'dokumenty', ContextEntryKind::Directory));
        self::assertContains('module.file-info.nothing', self::textsOf($screen->draw(self::panel())));

        $screen->useContext(new ModuleContext('/home', 'notatka.txt', ContextEntryKind::File));
        $texts = self::textsOf($screen->draw(self::panel()));

        self::assertContains('notatka.txt', $texts);
        self::assertContains('ASCII text', $texts);
        self::assertSame(['/home/notatka.txt'], $inspector->inspectedPaths);
    }

    /**
     * Polecenie `file` uruchamia się przy zmianie zaznaczenia, a nie co klatkę:
     * trzydzieści procesów potomnych na sekundę kosztowałoby więcej niż cała
     * reszta klatki.
     */
    public function testDescriptionIsComputedOncePerSelection(): void
    {
        $inspector = new StubFileInspector();
        $screen = new FileInfoScreen(
            new InspectSelectedEntryUseCase($inspector, new InMemorySettings()),
            new StubTranslator(),
        );
        $context = new ModuleContext('/home', 'notatka.txt', ContextEntryKind::File);

        for ($frame = 0; $frame < 5; ++$frame) {
            $screen->useContext($context);
            $screen->draw(self::panel());
        }

        self::assertCount(1, $inspector->inspectedPaths);

        // Ponowne otwarcie ekranu liczy opis od nowa — po to, żeby zmiana
        // ustawień modułu była widoczna od razu.
        $screen->reset();
        $screen->useContext($context);

        self::assertCount(2, $inspector->inspectedPaths);
    }

    public function testModuleSettingsReachTheInspector(): void
    {
        $inspector = new StubFileInspector();
        $store = new InMemorySettings(
            (new Settings())
                ->withModuleValue(FileInfoSettings::ID, FileInfoSettings::TIMEOUT, 5)
                ->withModuleValue(FileInfoSettings::ID, FileInfoSettings::ARGUMENTS, '-k'),
        );

        (new InspectSelectedEntryUseCase($inspector, $store))
            ->execute(new ModuleContext('/home', 'notatka.txt', ContextEntryKind::File));

        self::assertSame([[5, '-k']], $inspector->options);
    }

    public function testEmptyContextDescribesNothing(): void
    {
        $description = (new InspectSelectedEntryUseCase(new StubFileInspector(), new InMemorySettings()))
            ->execute(new ModuleContext());

        self::assertNull($description);
    }

    public function testJumpEntersTheGivenDirectory(): void
    {
        $outcome = $this->jump()->execute(new CommandInput(['path' => '/home/dokumenty']));

        self::assertSame(CommandTransition::Close, $outcome->transition);
        self::assertSame('/home/dokumenty', $this->app->state->directory()->path()->value);
    }

    public function testJumpAcceptsAPathRelativeToTheCurrentPlace(): void
    {
        $this->jump()->execute(new CommandInput(['path' => 'dane']));

        self::assertSame('/home/dane', $this->app->state->directory()->path()->value);
    }

    /** Ścieżka nieczytelna nie zamyka okna — użytkownik ma gdzie poprawić literówkę. */
    public function testJumpToAnUnreadableDirectoryStaysOpenWithAMessage(): void
    {
        $outcome = $this->jump()->execute(new CommandInput(['path' => '/nie-ma-takiego']));

        self::assertSame(CommandTransition::Stay, $outcome->transition);
        self::assertNotNull($outcome->message);
        self::assertSame('/home', $this->app->state->directory()->path()->value);
    }

    public function testJumpSuggestsDirectoriesFromDiskOnDemand(): void
    {
        $suggestions = $this->jump()->suggestions('path', '/home/d');

        self::assertSame(['/home/dokumenty/', '/home/dane/'], $suggestions);
    }

    public function testSuggestionsOfAnUnreadableDirectoryAreEmptyRatherThanAnError(): void
    {
        $this->directories->makeUnreadable('/home');

        self::assertSame([], $this->jump()->suggestions('path', '/home/d'));
    }

    public function testSuggestionsSkipFiles(): void
    {
        self::assertSame(
            ['dokumenty/', 'dane/'],
            $this->jump()->suggestions('path', ''),
            'skok dotyczy katalogów, więc plik nie jest podpowiedzią',
        );
    }

    /** Ekran modułu wraca `Esc`em — tak samo, jak każdy ekran rdzenia. */
    public function testEscapeClosesTheModuleScreen(): void
    {
        $outcome = $this->app->fileInfo->handle(KeyPress::special(Key::Escape, ''));

        self::assertSame(Transition::Close, $outcome->transition);
    }

    private function jump(): JumpCommand
    {
        return new JumpCommand($this->app->state, $this->directories, new StubTranslator());
    }

    private static function panel(): Rect
    {
        return new Rect(0, 2, 10, 40);
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
