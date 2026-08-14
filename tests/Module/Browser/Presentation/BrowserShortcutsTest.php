<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Browser\Presentation;

use LightManager\Application\Dto\KeyPress;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * Strażnik jednej kolizji, którą wprowadził krok 31: **`Ctrl`+`T` jest klawiszem
 * ekranu, a `Ctrl`+litera jest przestrzenią skrótów modułów.**
 *
 * `InputHandler` sprawdza skróty modułów **przed** ekranem (krok 19), a literę
 * bez zarejestrowanego modułu przepuszcza niżej — i tylko dlatego klawisz widoku
 * w ogóle działa. Moduł ze skrótem `t` przejąłby go po cichu: użytkownik
 * naciskałby klawisz drzewa i dostawał cudze okno. Rozstrzygnięcie użytkownika
 * ze startu kroku było świadome, więc cena też ma być widoczna — i ma wyjść na
 * testach, a nie na klawiaturze.
 */
final class BrowserShortcutsTest extends TestCase
{
    /** Ta sama litera, co `BrowserScreen::TREE_KEY` — stała ekranu jest prywatna. */
    private const TREE_KEY = 't';

    private ScreenFixture $app;

    protected function setUp(): void
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/home', [Entry::directory('projekty'), Entry::file('notatka.txt', 12)])
            ->add('/home/projekty', [Entry::file('plan.md', 2048)]);

        $this->app = new ScreenFixture($directories->get(new DirectoryPath('/home'), false), $directories);
    }

    public function testNoModuleClaimsTheLetterUsedByTheTreeView(): void
    {
        $taken = [];

        foreach ($this->app->modules->declared() as $module) {
            $shortcut = $module->shortcut();

            if ($shortcut !== null) {
                $taken[$shortcut->character] = $module->id();
            }
        }

        self::assertArrayNotHasKey(
            self::TREE_KEY,
            $taken,
            'skrót modułu przejąłby Ctrl+T sprzed ekranu przeglądarki — klawisz widoku trzeba wtedy zmienić',
        );
    }

    /**
     * Klawisz globalny wygrywa z ekranem i tak ma zostać: `Ctrl`+`D` otwiera opis
     * pliku także wtedy, gdy panel pokazuje drzewo.
     */
    public function testAModuleShortcutStillReachesItsModuleFromTheTreeView(): void
    {
        $this->app->browser->handle(KeyPress::ctrl(self::TREE_KEY));
        $this->app->input->handle(KeyPress::ctrl('d'), $this->app->state, 0.0);

        self::assertSame($this->app->fileInfo->id(), $this->app->screens->current()->id());
    }

    /** Litera z `Ctrl` bez znaczenia nie robi w przeglądarce nic — i nic nie psuje. */
    public function testAnUnknownControlLetterChangesNothing(): void
    {
        $before = $this->app->state->context()->selection;

        $this->app->browser->handle(KeyPress::ctrl('q'));

        self::assertSame($before, $this->app->state->context()->selection);
    }

    /** Goła litera `t` nie jest klawiszem widoku — modyfikator musi się zgadzać (reguła 11j). */
    public function testThePlainLetterDoesNotSwitchTheView(): void
    {
        $this->app->browser->handle(KeyPress::character(self::TREE_KEY));

        self::assertSame(
            [],
            array_values(array_filter(
                $this->app->browser->bindings(),
                static fn (object $binding): bool => (string) $binding->descriptionKey === 'module.browser.help.tree.expand',
            )),
            'panel nadal pokazuje listę, więc spis nie zna klawiszy drzewa',
        );
    }
}
