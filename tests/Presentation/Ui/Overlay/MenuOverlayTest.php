<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui\Overlay;

use LightManager\Application\Command\AppliesToSelection;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Command\CommandRegistry;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Presentation\Ui\Overlay\MenuOverlay;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Menu ma być **widokiem na rejestr komend**, więc testy pilnują przede
 * wszystkim dwóch rzeczy: że pozycje biorą się z rejestru (a nie z własnej
 * listy) i że pokazują wyłącznie to, co da się zrobić z zaznaczeniem.
 */
final class MenuOverlayTest extends TestCase
{
    private CommandRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new CommandRegistry();
        $this->registry->add('core', [
            new PlainCommand('core.quit'),
            new FakeCommand('core.onFiles', ContextEntryKind::File),
            new FakeCommand('core.onDirectories', ContextEntryKind::Directory),
        ]);
    }

    /**
     * Zawężenie do rodzaju zaznaczenia — jedyna rzecz, którą menu wnosi ponad
     * okno komend poza wyborem bez pisania.
     */
    public function testShowsOnlyTheCommandsThatMakeSenseForTheSelection(): void
    {
        $menu = $this->menu();

        $menu->useContext(new ModuleContext('/home', 'plik.txt', ContextEntryKind::File));
        self::assertSame(['core.onFiles'], $this->namesIn($menu));

        $menu->useContext(new ModuleContext('/home', 'katalog', ContextEntryKind::Directory));
        self::assertSame(['core.onDirectories'], $this->namesIn($menu));
    }

    /**
     * Komenda bez zdolności `AppliesToSelection` **nie ma prawa** trafić do
     * menu, choćby zaznaczenie było najbogatsze: `core.quit` nie jest czynnością
     * na pliku.
     */
    public function testCommandsWithoutTheCapabilityNeverShowUp(): void
    {
        $menu = $this->menu();

        $menu->useContext(new ModuleContext('/home', 'plik.txt', ContextEntryKind::File));

        self::assertNotContains('core.quit', $this->namesIn($menu));
    }

    /** Pusty katalog: nie ma zaznaczenia, więc nie ma o czym otwierać okna. */
    public function testWithoutASelectionThereIsNothingToShow(): void
    {
        $menu = $this->menu();

        $menu->useContext(new ModuleContext('/home'));

        self::assertTrue($menu->isEmpty());
        self::assertSame('menu.empty', $menu->emptyMessage()->text);
    }

    /**
     * **Drugiego rejestru nie ma** i to jest kryterium ukończenia kroku:
     * komenda dopisana do rejestru pojawia się w menu bez zmiany choćby jednej
     * linii w tej klasie.
     */
    public function testItemsComeFromTheRegistrySoANewCommandCostsNothingHere(): void
    {
        $menu = $this->menu();
        $context = new ModuleContext('/home', 'plik.txt', ContextEntryKind::File);

        $menu->useContext($context);
        self::assertSame(['core.onFiles'], $this->namesIn($menu));

        $this->registry->add('module', [new FakeCommand('module.late', ContextEntryKind::File)]);
        $menu->useContext($context);

        self::assertSame(['core.onFiles', 'module.late'], $this->namesIn($menu));
    }

    /** `Enter` wykonuje komendę spod kursora — i nic poza nią. */
    public function testEnterRunsTheCommandUnderTheCursor(): void
    {
        $menu = $this->menu();
        $menu->useContext(new ModuleContext('/home', 'plik.txt', ContextEntryKind::File));

        $outcome = $menu->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(['core.onFiles'], FakeCommand::$executed);
        self::assertTrue($outcome->closes);
    }

    /** Strzałki przestawiają kursor, a `Enter` bierze to, co pod nim stoi. */
    public function testArrowsMoveTheCursorBeforeRunning(): void
    {
        $this->registry->add('module', [new FakeCommand('module.second', ContextEntryKind::File)]);

        $menu = $this->menu();
        $menu->useContext(new ModuleContext('/home', 'plik.txt', ContextEntryKind::File));

        $menu->handle(KeyPress::special(Key::ArrowDown, ''));
        $menu->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(['module.second'], FakeCommand::$executed);
    }

    /** Skutek komendy przechodzi przez menu bez zmiany: ekran do otwarcia i komunikat. */
    public function testTheOutcomeOfTheCommandTravelsThroughUntouched(): void
    {
        $this->registry->add('module', [new FakeCommand('module.opens', ContextEntryKind::File, 'file-info')]);

        $menu = $this->menu();
        $menu->useContext(new ModuleContext('/home', 'plik.txt', ContextEntryKind::File));

        $menu->handle(KeyPress::special(Key::ArrowDown, ''));
        $outcome = $menu->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame('file-info', $outcome->screenId);
        self::assertTrue($outcome->closes);
    }

    /** `Esc` i `F9` zamykają — drugi tak samo, jak `F12` zamyka okno komend. */
    public function testEscapeAndTheOwnKeyClose(): void
    {
        foreach ([Key::Escape, Key::F9] as $key) {
            $menu = $this->menu();
            $menu->useContext(new ModuleContext('/home', 'plik.txt', ContextEntryKind::File));

            $outcome = $menu->handle(KeyPress::special($key, ''));

            self::assertTrue($outcome->handled, $key->name . ' należy do menu');
            self::assertTrue($outcome->closes, $key->name . ' zamyka menu');
            self::assertSame([], FakeCommand::$executed, 'zamknięcie nie wykonuje niczego');
        }
    }

    /**
     * Klawisz spoza menu **przechodzi wyżej**, a nie ginie: `F10` ma kończyć
     * pracę także wtedy, gdy menu stoi na wierzchu (reguła kroku 19).
     */
    public function testUnknownKeysPassToTheGlobalOnes(): void
    {
        $menu = $this->menu();
        $menu->useContext(new ModuleContext('/home', 'plik.txt', ContextEntryKind::File));

        self::assertFalse($menu->handle(KeyPress::special(Key::F10, ''))->handled);
        self::assertFalse($menu->handle(KeyPress::character('a'))->handled);
    }

    /** Wiersz: nazwa po lewej, opis po prawej — układ listy podpowiedzi okna komend. */
    public function testRowCarriesTheNameOnTheLeftAndTheDescriptionOnTheRight(): void
    {
        $menu = $this->menu();
        $menu->useContext(new ModuleContext('/home', 'plik.txt', ContextEntryKind::File));

        $texts = self::textsOf($menu->draw($menu->bounds(24, 80)));

        self::assertContains('menu.title', $texts, 'tytuł okna');
        self::assertContains('core.onFiles', $texts, 'nazwa komendy');
        self::assertContains('command.core.onFiles', $texts, 'opis komendy z katalogu napisów');
    }

    /**
     * Okno staje pośrodku, jak okno potwierdzenia: rdzeń nie wie, gdzie moduł
     * narysował zaznaczenie, więc nie ma jak stanąć przy nim.
     */
    public function testWindowStandsInTheMiddle(): void
    {
        $menu = $this->menu();
        $menu->useContext(new ModuleContext('/home', 'plik.txt', ContextEntryKind::File));

        $bounds = $menu->bounds(24, 80);

        self::assertSame(intdiv(24 - $bounds->rows, 2), $bounds->row);
        self::assertSame(intdiv(80 - $bounds->columns, 2), $bounds->column);
    }

    /** Okno mniejsze od żądanego prostokąta nie wychodzi poza klatkę. */
    public function testWindowNeverGrowsBeyondTheFrame(): void
    {
        $menu = $this->menu();
        $menu->useContext(new ModuleContext('/home', 'plik.txt', ContextEntryKind::File));

        $bounds = $menu->bounds(4, 20);

        self::assertLessThanOrEqual(4, $bounds->rows);
        self::assertLessThanOrEqual(20, $bounds->columns);
    }

    private function menu(): MenuOverlay
    {
        FakeCommand::$executed = [];

        return new MenuOverlay($this->registry, new StubTranslator());
    }

    /**
     * Nazwy pozycji odczytane z narysowanej klatki — bo publicznego spisu menu
     * nie wystawia i wystawiać nie powinno.
     *
     * @return list<string>
     */
    private function namesIn(MenuOverlay $menu): array
    {
        $names = [];

        foreach (self::textsOf($menu->draw($menu->bounds(24, 80))) as $text) {
            if (str_contains($text, '.') && !str_starts_with($text, 'command.') && $text !== 'menu.title') {
                $names[] = $text;
            }
        }

        return $names;
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
                $texts[] = trim($primitive->text);
            }
        }

        return $texts;
    }
}

/**
 * Komenda bez zdolności „czego dotyczę” — taka, jaką jest dziś każda komenda
 * rdzenia. Do menu nie ma prawa wejść.
 */
final class PlainCommand implements CommandInterface
{
    public function __construct(private readonly string $name)
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function descriptionKey(): string
    {
        return 'command.' . $this->name;
    }

    public function arguments(): array
    {
        return [];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        return CommandOutcome::done();
    }
}

/**
 * Komenda-atrapa ze zdolnością „czego dotyczę”; zapisuje, że ją wywołano.
 */
final class FakeCommand implements CommandInterface, AppliesToSelection
{
    /** @var list<string> nazwy wywołanych komend — wspólny ślad dla całego testu */
    public static array $executed = [];

    public function __construct(
        private readonly string $name,
        private readonly ?ContextEntryKind $kind,
        private readonly ?string $screenId = null,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function descriptionKey(): string
    {
        return 'command.' . $this->name;
    }

    public function arguments(): array
    {
        return [];
    }

    public function appliesTo(ModuleContext $context): bool
    {
        return $this->kind !== null && $context->kind === $this->kind;
    }

    public function inputFor(ModuleContext $context): CommandInput
    {
        return new CommandInput();
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        self::$executed[] = $this->name;

        return $this->screenId === null ? CommandOutcome::done() : CommandOutcome::opens($this->screenId);
    }
}
