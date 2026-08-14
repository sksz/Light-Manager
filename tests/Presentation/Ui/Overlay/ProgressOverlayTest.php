<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui\Overlay;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\WorkProgress;
use LightManager\Application\Ui\Primitive\Bar;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Domain\ValueObject\Message;
use LightManager\Presentation\Ui\Overlay\ConfirmOverlay;
use LightManager\Presentation\Ui\Overlay\ProgressOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Okno pracy dłuższej od klatki (krok 41) — pierwsze, które **działa samo**.
 *
 * Trzy rzeczy są tu do sprawdzenia i wszystkie trzy są nowe w projekcie: praca
 * posuwa się raz na takt bez ani jednego klawisza, okno zamyka się samo, kiedy
 * praca się skończy, i umie **ustąpić miejsca** kolejnemu oknu.
 */
final class ProgressOverlayTest extends TestCase
{
    /** @var list<WorkProgress> stany oddawane kolejnymi kawałkami pracy */
    private array $steps = [];

    private int $cancelled = 0;

    protected function setUp(): void
    {
        $this->steps = [];
        $this->cancelled = 0;
    }

    public function testWorkAdvancesOnceATickWithoutAnyKey(): void
    {
        $this->steps = [
            new WorkProgress(true, 'a', 1, 3),
            new WorkProgress(true, 'b', 2, 3),
        ];
        $overlay = $this->overlay(new WorkProgress(true, '', 0, 3));

        self::assertFalse($overlay->advance()->closes);
        self::assertFalse($overlay->advance()->closes);
        self::assertContains('b', self::textsOf($overlay->draw(new Rect(0, 0, 5, 50))), 'okno pokazuje ostatni kawałek');
    }

    public function testFinishedWorkClosesTheWindowOnItsOwn(): void
    {
        $this->steps = [new WorkProgress(false, '', 3, 3)];
        $overlay = $this->overlay(new WorkProgress(true, '', 0, 3));

        $outcome = $overlay->advance();

        self::assertTrue($outcome->closes);
        self::assertSame('skończone', $outcome->message?->text);
    }

    /** Praca może skończyć się **pytaniem**: policzona liczba wpisów jest do czegoś potrzebna. */
    public function testFinishedWorkMayHandOverToTheNextWindow(): void
    {
        $next = new ConfirmOverlay('pytanie', [], static fn (): OverlayOutcome => OverlayOutcome::close(), new StubTranslator());
        $this->steps = [new WorkProgress(false, '', 3, 3)];
        $overlay = new ProgressOverlay(
            'tytul',
            [],
            new WorkProgress(true, '', 0, 3),
            fn (): WorkProgress => $this->nextStep(),
            static fn (): OverlayOutcome => OverlayOutcome::replace($next),
            static fn (): ?Message => null,
            new StubTranslator(),
        );

        $outcome = $overlay->advance();

        self::assertTrue($outcome->closes);
        self::assertSame($next, $outcome->next);
    }

    public function testEscapeStopsTheWork(): void
    {
        $overlay = $this->overlay(new WorkProgress(true, 'a', 1, 3));

        $outcome = $overlay->handle(KeyPress::special(Key::Escape, "\e"));

        self::assertSame(1, $this->cancelled);
        self::assertTrue($outcome->closes);
        self::assertSame('przerwane', $outcome->message?->text);
    }

    /** Wszystko poza `Esc` idzie do klawiszy globalnych: w tym oknie nie ma czego pisać. */
    public function testEveryOtherKeyIsPassedThrough(): void
    {
        $overlay = $this->overlay(new WorkProgress(true, 'a', 1, 3));

        foreach ([Key::Enter, Key::F10, Key::ArrowDown] as $key) {
            self::assertFalse($overlay->handle(KeyPress::special($key, ''))->handled, $key->name);
        }

        self::assertSame(0, $this->cancelled);
    }

    /**
     * Pasek pokazuje się **dopiero wtedy, gdy jest co pokazać**: praca nieznająca
     * swojej całości (liczenie zawartości katalogu) dostaje samą nazwę.
     */
    public function testTheBarAppearsOnlyWhenTheTotalIsKnown(): void
    {
        $counting = $this->overlay(new WorkProgress(true, 'plik', 7, null));
        $deleting = $this->overlay(new WorkProgress(true, 'plik', 7, 30));

        self::assertSame(4, $counting->bounds(30, 60)->rows, 'bez paska okno jest o wiersz niższe');
        self::assertSame(5, $deleting->bounds(30, 60)->rows);

        self::assertSame([], self::barsOf($counting->draw(new Rect(0, 0, 4, 60))));
        self::assertNotSame([], self::barsOf($deleting->draw(new Rect(0, 0, 5, 60))));
    }

    public function testTheCounterStandsInsideTheBar(): void
    {
        $texts = self::textsOf($this->overlay(new WorkProgress(true, 'plik', 7, 30))->draw(new Rect(0, 0, 5, 60)));

        self::assertContains('plik', $texts, 'wiersz treści to nazwa wpisu');

        // Napis paska rozpada się na dwa `TextRun`y tam, gdzie kończy się
        // wypełnienie — bo litery na akcencie zmieniają rolę (krok 23). Sprawdzamy
        // więc całość, a nie pojedynczy kawałek.
        $caption = implode('', $texts);

        self::assertStringContainsString('progress.counter', $caption);
        self::assertStringContainsString('total=30', $caption, 'licznik zna całość');
        self::assertStringContainsString('percent', $caption, 'procent dokłada sam pasek');
    }

    private function overlay(WorkProgress $progress): ProgressOverlay
    {
        return new ProgressOverlay(
            'tytul',
            [],
            $progress,
            fn (): WorkProgress => $this->nextStep(),
            static fn (): OverlayOutcome => OverlayOutcome::close(Message::info('skończone')),
            function (): Message {
                ++$this->cancelled;

                return Message::info('przerwane');
            },
            new StubTranslator(),
        );
    }

    /** Kolejny kawałek pracy; wyczerpana lista znaczy „skończone”. */
    private function nextStep(): WorkProgress
    {
        $step = array_shift($this->steps);

        return $step ?? WorkProgress::idle();
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

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<Bar>
     */
    private static function barsOf(array $primitives): array
    {
        $bars = [];

        foreach ($primitives as $primitive) {
            if ($primitive instanceof Bar) {
                $bars[] = $primitive;
            }
        }

        return $bars;
    }
}
