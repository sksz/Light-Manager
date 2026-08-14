<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Presentation\Ui\Component\Dialog;
use LightManager\Presentation\Ui\Overlay\MessageOverlay;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\TestCase;

/**
 * Droga klawisza przy otwartym oknie komend: okno → klawisze globalne → **nigdy**
 * ekran.
 *
 * Test sprawdza to, czego nie widać w żadnej klasie z osobna: że okno jest
 * naprawdę modalne. Do kroku 19 okno nakładane zamykało się pierwszym dowolnym
 * klawiszem, więc pytanie „co dostaje ekran pod spodem” w ogóle nie istniało.
 */
final class CommandWindowFlowTest extends TestCase
{
    private const NOW = 1000.0;

    private ScreenFixture $app;

    protected function setUp(): void
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/home', [Entry::directory('dokumenty'), Entry::file('notatka.txt', 12)])
            ->add('/home/dokumenty', []);

        $this->app = new ScreenFixture(
            $directories->get(new DirectoryPath('/home'), false),
            $directories,
        );
    }

    public function testF12OpensTheWindowAndClosesItAgain(): void
    {
        $this->special(Key::F12);

        self::assertTrue($this->app->state->overlays()->isOpen());

        $this->special(Key::F12);

        self::assertFalse($this->app->state->overlays()->isOpen());
    }

    /**
     * Litera wpisana w oknie komend nie ma prawa ruszyć zaznaczenia pod spodem —
     * użytkownik go w tej chwili nie widzi.
     */
    public function testTypingDoesNotReachTheScreenUnderneath(): void
    {
        $before = $this->app->state->context()->selection;

        $this->special(Key::F12);
        $this->character('c');
        $this->special(Key::ArrowDown);

        self::assertSame($before, $this->app->state->context()->selection);
    }

    /** Kropka przełącza wpisy ukryte na liście — ale nie wtedy, gdy się ją wpisuje. */
    public function testDotTypedInTheWindowDoesNotToggleHiddenEntries(): void
    {
        $this->special(Key::F12);
        $this->character('.');

        self::assertFalse(BrowserSettings::showHidden($this->app->state->settings()));
    }

    public function testGlobalKeyStillWorksAndClosesTheWindow(): void
    {
        $this->special(Key::F12);

        self::assertFalse($this->special(Key::F1), 'pomoc to nie wyjście');
        self::assertFalse($this->app->state->overlays()->isOpen(), 'okno ustępuje ekranowi pomocy');
        self::assertSame('help', $this->app->screens->current()->id());
    }

    public function testF10QuitsEvenWithTheWindowOpen(): void
    {
        $this->special(Key::F12);

        self::assertTrue($this->special(Key::F10));
    }

    public function testCommandOpensTheScreenItNames(): void
    {
        $this->special(Key::F12);
        $this->write('core.settings');
        $this->special(Key::Enter);

        self::assertSame('settings', $this->app->screens->current()->id());
        self::assertFalse($this->app->state->overlays()->isOpen());
    }

    public function testCommandQuitsTheApplication(): void
    {
        $this->special(Key::F12);
        $this->write('core.quit');

        self::assertTrue($this->special(Key::Enter));
    }

    public function testCommandChangesASettingAndSaysNothingWhenItWorks(): void
    {
        $this->special(Key::F12);
        $this->write('core.theme nordyk');
        $this->special(Key::Enter);

        $saved = $this->app->settingsStore->saved;

        self::assertSame('nordyk', $this->app->state->settings()->theme);
        self::assertNotSame([], $saved, 'zmiana idzie na dysk od razu');
        self::assertSame('nordyk', $saved[count($saved) - 1]->theme);
    }

    public function testUnknownValueKeepsTheWindowOpenAndSaysWhy(): void
    {
        $this->special(Key::F12);
        $this->write('core.theme nieznany');
        $this->special(Key::Enter);

        self::assertTrue($this->app->state->overlays()->isOpen());
        self::assertNotNull($this->app->state->message());
        self::assertSame('grafit', $this->app->state->settings()->theme, 'ustawienie zostaje przy swoim');
    }

    public function testRunCommandLandsInHistoryOnFlush(): void
    {
        $this->special(Key::F12);
        $this->write('core.help');
        $this->special(Key::Enter);

        self::assertSame(0, $this->app->history->saves, 'bufor nie jest pełny — dysk czeka');
    }

    /**
     * Okno z treścią do przeczytania zamyka się dowolnym klawiszem — tak jak
     * przed krokiem 19.
     *
     * Od kroku 20 aplikacja sama go nie otwiera (opis pliku wyprowadził się do
     * modułu i ma własny **ekran**), więc test stawia je wprost. Okno zostaje
     * w rdzeniu jako droga, którą `ScreenOutcome::opens()` oddaje ekranom —
     * także modułowym.
     */
    public function testMessageWindowStillClosesOnAnyKey(): void
    {
        $this->app->state->overlays()->open(new MessageOverlay(new Dialog('alfa.txt', ['ASCII text'])));

        self::assertTrue($this->app->state->overlays()->isOpen());

        $this->character('x');

        self::assertFalse($this->app->state->overlays()->isOpen());
    }

    private function write(string $line): void
    {
        foreach (mb_str_split($line) as $character) {
            $this->character($character);
        }
    }

    private function character(string $character): bool
    {
        return $this->app->input->handle(KeyPress::character($character), $this->app->state, self::NOW);
    }

    private function special(Key $key): bool
    {
        return $this->app->input->handle(KeyPress::special($key, ''), $this->app->state, self::NOW);
    }
}
