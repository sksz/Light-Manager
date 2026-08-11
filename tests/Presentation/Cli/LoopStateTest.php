<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Cli;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Component\Dialog;
use LightManager\Presentation\Ui\Overlay\MessageOverlay;
use PHPUnit\Framework\TestCase;

/**
 * Stan powłoki — czyli to, co po kroku 21 zostało z dawnego stanu pętli.
 *
 * Katalogu tu nie ma i to jest treść tego testu tak samo, jak treścią jest
 * wszystko, co zostało: ustawienia, komunikat, okna nakładane i kontekst sesji.
 */
final class LoopStateTest extends TestCase
{
    private LoopState $state;

    protected function setUp(): void
    {
        $this->state = new LoopState();
    }

    public function testStartsEmpty(): void
    {
        self::assertNull($this->state->message());
        self::assertNull($this->state->overlays()->current());
        self::assertSame('', $this->state->context()->path);
        self::assertNull($this->state->context()->selection);
    }

    /** Brak wydawcy daje kontekst **pusty**, a nie `null`: odbiorca ma czytać. */
    public function testContextIsEmptyUntilSomebodyPublishesIt(): void
    {
        self::assertSame(ContextEntryKind::None, $this->state->context()->kind);

        $this->state->publishContext(new ModuleContext('/home', 'notatka.txt', ContextEntryKind::File));

        self::assertSame('/home', $this->state->context()->path);
        self::assertSame('notatka.txt', $this->state->context()->selection);
        self::assertSame(ContextEntryKind::File, $this->state->context()->kind);
    }

    public function testPublishingContextKeepsPreviousProblemVisible(): void
    {
        $this->state->reportProblem('coś poszło nie tak', 0.0);

        $this->state->publishContext(new ModuleContext('/inny'));

        self::assertSame('/inny', $this->state->context()->path);
        // Sama zmiana miejsca komunikatu nie gasi — robi to dopiero klawisz
        // naciśnięty po upływie czasu na przeczytanie.
        self::assertNotNull($this->state->message());
    }

    public function testSettingsAreReplacedWholesale(): void
    {
        self::assertSame('browser', $this->state->settings()->startupModule);

        $this->state->applySettings((new Settings())->withStartupModule('file-info'));

        self::assertSame('file-info', $this->state->settings()->startupModule);
    }

    public function testOpensAndClosesTheModalWindow(): void
    {
        $this->state->overlays()->open(new MessageOverlay(new Dialog('alfa.txt', ['ASCII text'])));

        self::assertNotNull($this->state->overlays()->current());

        $this->state->overlays()->close();

        self::assertNull($this->state->overlays()->current());
    }
}
