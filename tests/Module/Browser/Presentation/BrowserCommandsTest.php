<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Browser\Presentation;

use LightManager\Application\Command\AppliesToSelection;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * Trzy komendy dopisane w kroku 32 nazywają czynności, które przeglądarka miała
 * dotąd wyłącznie pod klawiszem — więc każdy test pyta o to samo: czy **obie
 * drogi prowadzą w to samo miejsce**.
 *
 * Gdyby prowadziły gdzie indziej, komenda byłaby drugą implementacją tej samej
 * czynności, czyli dokładnie tym, czego krok miał nie zrobić.
 */
final class BrowserCommandsTest extends TestCase
{
    private const NOW = 1000.0;

    /** Wejście do katalogu: komenda robi to, co `Enter` na liście. */
    public function testOpenCommandEntersTheSelectedDirectoryJustLikeEnter(): void
    {
        $throughKey = self::fixture();
        $throughKey->input->handle(KeyPress::special(Key::Enter, "\r"), $throughKey->state, self::NOW);

        $throughCommand = self::fixture();
        self::execute($throughCommand, 'browser.open');

        self::assertSame('/home/dokumenty', $throughKey->state->context()->path);
        self::assertSame($throughKey->state->context()->path, $throughCommand->state->context()->path);
    }

    /**
     * Na pliku komenda **nie wchodzi** — i mówi, dlaczego. W menu takiej pozycji
     * nie ma, ale nazwę wolno wpisać na czymkolwiek.
     */
    public function testOpenCommandOnAFileSaysWhyItDidNothing(): void
    {
        $app = self::fixture();
        $app->input->handle(KeyPress::special(Key::ArrowDown, ''), $app->state, self::NOW);

        $outcome = self::execute($app, 'browser.open');

        self::assertSame('/home', $app->state->context()->path);
        self::assertSame('module.browser.open.notDirectory', $outcome?->text);
    }

    /**
     * W widoku drzewa komenda wchodzi do **węzła pod kursorem**, a nie do
     * zaznaczenia listy — czyli patrzy tam, gdzie patrzy kontekst sesji.
     *
     * Różnica widać dopiero **poniżej pierwszego poziomu**: węzeł z pierwszego
     * przesuwa przy okazji zaznaczenie listy (krok 31), więc obie odpowiedzi są
     * tam z konstrukcji te same. Test schodzi więc do rozwiniętej gałęzi, gdzie
     * lista wskazuje `dokumenty`, a drzewo — `dokumenty/wnetrze`.
     */
    public function testOpenCommandFollowsTheTreeCursorWhenThePaneShowsATree(): void
    {
        $app = self::fixture();
        $app->input->handle(KeyPress::ctrl('t'), $app->state, self::NOW);

        // `↑` przy nieustawionym kursorze sprowadza go na pierwszy węzeł, `→`
        // rozwija gałąź, `↓` schodzi do jej pierwszego dziecka.
        $app->input->handle(KeyPress::special(Key::ArrowUp, ''), $app->state, self::NOW);
        $app->input->handle(KeyPress::special(Key::ArrowRight, ''), $app->state, self::NOW);
        $app->input->handle(KeyPress::special(Key::ArrowDown, ''), $app->state, self::NOW);

        self::assertSame('/home/dokumenty', $app->state->context()->path, 'kontekst zszedł do gałęzi');
        self::assertSame('wnetrze', $app->state->context()->selection);

        self::execute($app, 'browser.open');

        self::assertSame('/home/dokumenty/wnetrze', $app->state->context()->path);
    }

    /** Wpisy ukryte: komenda robi to, co kropka — razem z zapisem ustawienia. */
    public function testHiddenCommandTogglesTheSameThingAsTheDotKey(): void
    {
        $throughKey = self::fixture();
        $throughKey->input->handle(KeyPress::character('.'), $throughKey->state, self::NOW);

        $throughCommand = self::fixture();
        self::execute($throughCommand, 'browser.hidden');

        self::assertTrue(BrowserSettings::showHidden($throughKey->state->settings()));
        self::assertTrue(BrowserSettings::showHidden($throughCommand->state->settings()));
        self::assertSame(
            self::savedHidden($throughKey),
            self::savedHidden($throughCommand),
            'obie drogi zapisują to samo ustawienie',
        );
    }

    /** Widok panelu: komenda robi to, co `Ctrl`+`T` — poznać po klawiszach ekranu. */
    public function testTreeCommandSwitchesTheViewJustLikeTheShortcut(): void
    {
        $throughKey = self::fixture();
        $throughKey->input->handle(KeyPress::ctrl('t'), $throughKey->state, self::NOW);

        $throughCommand = self::fixture();
        self::execute($throughCommand, 'browser.tree');

        self::assertSame(self::keysOf($throughKey), self::keysOf($throughCommand));
        self::assertContains('module.browser.help.tree.expand', self::keysOf($throughCommand));
    }

    /**
     * Zdolność „czego dotyczę” ma **wyłącznie** komenda działająca na
     * zaznaczeniu. Przełączniki widoku jej nie deklarują i dlatego nie wchodzą
     * do menu.
     */
    public function testOnlyTheCommandActingOnTheSelectionDeclaresTheCapability(): void
    {
        $app = self::fixture();

        $open = $app->commandRegistry->find('browser.open');
        self::assertInstanceOf(AppliesToSelection::class, $open);

        self::assertTrue($open->appliesTo(new ModuleContext('/home', 'dokumenty', ContextEntryKind::Directory)));
        self::assertFalse($open->appliesTo(new ModuleContext('/home', 'notatka.txt', ContextEntryKind::File)));
        self::assertFalse($open->appliesTo(new ModuleContext('/home')));

        self::assertNotInstanceOf(AppliesToSelection::class, $app->commandRegistry->find('browser.hidden'));
        self::assertNotInstanceOf(AppliesToSelection::class, $app->commandRegistry->find('browser.tree'));
        self::assertNotInstanceOf(AppliesToSelection::class, $app->commandRegistry->find('browser.jump'));
    }

    private static function fixture(): ScreenFixture
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/home', [
                Entry::directory('dokumenty'),
                Entry::file('.ukryty', 3),
                Entry::file('notatka.txt', 12),
            ])
            ->add('/home/dokumenty', [Entry::directory('wnetrze')])
            ->add('/home/dokumenty/wnetrze', []);

        return new ScreenFixture($directories->get(new DirectoryPath('/home'), false), $directories);
    }

    private static function execute(ScreenFixture $app, string $name): ?Message
    {
        $command = $app->commandRegistry->find($name);

        self::assertInstanceOf(CommandInterface::class, $command);

        return $command->execute(new CommandInput())->message;
    }

    private static function savedHidden(ScreenFixture $app): bool
    {
        $saved = $app->settingsStore->saved;

        self::assertNotSame([], $saved, 'zmiana idzie na dysk od razu');

        return BrowserSettings::showHidden($saved[count($saved) - 1]);
    }

    /** @return list<string> opisy klawiszy ekranu przeglądarki */
    private static function keysOf(ScreenFixture $app): array
    {
        return array_map(
            static fn (KeyBinding $binding): string => $binding->descriptionKey,
            $app->browser->bindings(),
        );
    }
}
