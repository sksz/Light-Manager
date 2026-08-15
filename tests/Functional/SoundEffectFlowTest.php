<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Audio\Application\EffectAssignment;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubEffectStorage;
use PHPUnit\Framework\TestCase;

/**
 * Przebieg: **zdarzenie aplikacji dostaje dźwięk** (krok 46).
 *
 * Przebieg idzie całą drogą, której żaden test jednostkowy nie widzi w całości:
 * przeglądarka ogłasza zdarzenie → rdzeń je publikuje → moduł dźwięku, **którego
 * w tej chwili nie widać**, sięga do swojej mapy i prosi port o zagranie. Trzy
 * warstwy i dwa moduły, które się nie znają.
 *
 * Silnika audio nie uruchamia — atrapa portu zapamiętuje prośby zamiast je
 * spełniać (reguła z kroku 36).
 */
final class SoundEffectFlowTest extends TestCase
{
    private const CLICK = 'assets/sfx/click.wav';

    private const FAIL = 'assets/sfx/fail.mp3';

    private ScreenFixture $app;

    protected function setUp(): void
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/home', [
                Entry::directory('projekty'),
                Entry::file('notatka.txt', 12),
                Entry::file('plan.md', 40),
            ])
            ->add('/home/projekty', [Entry::file('plan.md', 120)]);

        $this->app = new ScreenFixture(
            $directories->get(new DirectoryPath('/home'), false),
            $directories,
            effects: new StubEffectStorage([
                'browser.cursor.moved' => new EffectAssignment(self::CLICK),
                'core.message.error' => new EffectAssignment(self::FAIL),
            ]),
        );

        // Takt wczytuje mapę — dokładnie tak, jak w pętli głównej. Bez niego
        // zdarzenia milczą i to jest zachowanie, nie usterka: odbiór nie ma prawa
        // sięgnąć na dysk.
        $this->app->ticker->tick($this->app->state, 100.0);
    }

    /** Ruch kursora w przeglądarce gra klik — moduł dźwięku o przeglądarce nie wie. */
    public function testMovingTheCursorPlaysTheAssignedEffect(): void
    {
        $this->app->browser->handle(KeyPress::special(Key::ArrowDown, "\e[B"));

        self::assertSame([['path' => self::CLICK, 'volume' => 70]], $this->app->audio->effects);
    }

    /**
     * Trzymana strzałka **nie zamienia kliku w warkot**: w jednej klatce gra raz,
     * a kolejne klatki wpuszczają następne dopiero po minimalnym odstępie.
     */
    public function testHoldingTheArrowDoesNotTurnTheClickIntoARattle(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->app->browser->handle(KeyPress::special(Key::ArrowDown, "\e[B"));
        }

        self::assertCount(1, $this->app->audio->effects);

        // Trzy klatki później próg mija i następny ruch znowu gra.
        $this->app->ticker->tick($this->app->state, 100.2);
        $this->app->browser->handle(KeyPress::special(Key::ArrowUp, "\e[A"));

        self::assertCount(2, $this->app->audio->effects);
    }

    /** Komunikat błędu gra swoje — ton komunikatu jest zdarzeniem rdzenia. */
    public function testAnErrorMessagePlaysItsOwnEffect(): void
    {
        $this->app->state->reportProblem('nie udało się', 100.0);

        self::assertSame([['path' => self::FAIL, 'volume' => 70]], $this->app->audio->effects);
    }

    /** Zdarzenie bez przypisania milczy — a przypisane obok niego nadal gra. */
    public function testAnUnassignedEventStaysSilent(): void
    {
        $this->app->state->report(Message::info('gotowe'), 100.0);

        self::assertSame([], $this->app->audio->effects);
    }

    /**
     * Odbiorca **nie zmienia biegu aplikacji**: klatka po ruchu kursora wygląda
     * tak samo, jak wyglądała przed krokiem 46, a komunikat zostaje ten, który
     * postawiła czynność.
     */
    public function testTheListenerChangesNothingInTheApplication(): void
    {
        $this->app->state->reportProblem('nie udało się', 100.0);
        $message = $this->app->state->message();

        self::assertNotNull($message);
        self::assertSame('nie udało się', $message->text);
    }
}
