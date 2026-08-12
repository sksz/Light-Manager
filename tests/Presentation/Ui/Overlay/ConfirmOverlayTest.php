<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui\Overlay;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\RoundRect;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\Message;
use LightManager\Presentation\Ui\Overlay\ConfirmOverlay;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Okno pyta o rzecz nieodwracalną, więc najważniejsze jest to, czego **nie**
 * robi: nie wykonuje czynności, dopóki nie padnie wyraźna zgoda.
 */
final class ConfirmOverlayTest extends TestCase
{
    /** @var list<string> ślad wywołań domknięcia */
    private array $performed = [];

    protected function setUp(): void
    {
        $this->performed = [];
    }

    public function testFocusStartsOnRefusalSoEnterAloneChangesNothing(): void
    {
        $overlay = $this->overlay();

        $outcome = $overlay->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame([], $this->performed);
        self::assertTrue($outcome->closes);
        self::assertNull($outcome->message);
    }

    public function testMovingFocusAndConfirmingPerformsTheActionAndCarriesItsMessage(): void
    {
        $overlay = $this->overlay();

        $overlay->handle(KeyPress::special(Key::ArrowRight, ''));
        $outcome = $overlay->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(['wykonano'], $this->performed);
        self::assertTrue($outcome->closes);
        self::assertSame('gotowe', $outcome->message?->text);
    }

    /** Ognisko wędruje każdą z trzech dróg i wraca — dwa przyciski to przełącznik. */
    public function testFocusTravelsWithArrowsAndTab(): void
    {
        foreach ([Key::ArrowLeft, Key::ArrowRight, Key::Tab] as $key) {
            $overlay = $this->overlay();

            $outcome = $overlay->handle(KeyPress::special($key, ''));
            self::assertFalse($outcome->closes, 'ruch ogniska nie zamyka okna');

            $overlay->handle(KeyPress::special(Key::Enter, "\r"));
            self::assertSame(['wykonano'], $this->performed, $key->name . ' powinien przestawić ognisko na „tak”');

            $this->performed = [];
        }
    }

    public function testFocusReturnsToRefusalAfterTwoMoves(): void
    {
        $overlay = $this->overlay();

        $overlay->handle(KeyPress::special(Key::Tab, "\t"));
        $overlay->handle(KeyPress::special(Key::Tab, "\t"));
        $overlay->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame([], $this->performed);
    }

    /** `Esc` znaczy dokładnie tyle, co „nie” (D56) — zamyka i nie wykonuje. */
    public function testEscapeRefuses(): void
    {
        $overlay = $this->overlay();

        $overlay->handle(KeyPress::special(Key::ArrowRight, ''));
        $outcome = $overlay->handle(KeyPress::special(Key::Escape, "\e"));

        self::assertSame([], $this->performed);
        self::assertTrue($outcome->closes);
    }

    /**
     * Klawisze globalne okno przepuszcza — `F10` w trakcie pytania kończy
     * aplikację **bez** wykonania czynności.
     */
    public function testGlobalKeysArePassedThrough(): void
    {
        $overlay = $this->overlay();

        foreach ([Key::F10, Key::F1, Key::F12] as $key) {
            $outcome = $overlay->handle(KeyPress::special($key, ''));

            self::assertFalse($outcome->handled, $key->name . ' należy do klawiszy globalnych');
            self::assertFalse($outcome->closes);
        }

        self::assertSame([], $this->performed);
    }

    /** Wariant groźny maluje się rolą `Danger` — obwódką i tytułem naraz. */
    public function testDangerousVariantPaintsWithTheDangerRole(): void
    {
        $bounds = new Rect(0, 0, 6, 40);

        $calm = $this->rolesOf($this->overlay(false)->draw($bounds));
        $grave = $this->rolesOf($this->overlay(true)->draw($bounds));

        self::assertContains(Role::Border, $calm);
        self::assertNotContains(Role::Danger, $calm);
        self::assertContains(Role::Danger, $grave);
        self::assertNotContains(Role::Border, $grave);
    }

    /** Okno mieści pytanie i oba przyciski, a przy ciasnym oknie nie wychodzi poza nie. */
    public function testBoundsFitTheQuestionAndStayInsideTheWindow(): void
    {
        $bounds = $this->overlay()->bounds(30, 100);

        self::assertSame(6, $bounds->rows);
        self::assertGreaterThan(0, $bounds->columns);
        self::assertLessThanOrEqual(100, $bounds->columns);

        $tight = $this->overlay()->bounds(4, 10);

        self::assertLessThanOrEqual(4, $tight->rows);
        self::assertLessThanOrEqual(10, $tight->columns);
    }

    /** Oba przyciski są widoczne od pierwszej klatki — inaczej pytanie nie mówi, co wolno odpowiedzieć. */
    public function testBothAnswersAreDrawn(): void
    {
        $texts = [];

        foreach ($this->overlay()->draw(new Rect(0, 0, 6, 40)) as $primitive) {
            if ($primitive instanceof TextRun) {
                $texts[] = $primitive->text;
            }
        }

        self::assertContains('confirm.yes', $texts);
        self::assertContains('confirm.no', $texts);
        self::assertContains('pytanie.klucz', $texts);
    }

    private function overlay(bool $dangerous = false): ConfirmOverlay
    {
        return new ConfirmOverlay(
            'pytanie.klucz',
            [],
            function (): Message {
                $this->performed[] = 'wykonano';

                return Message::info('gotowe');
            },
            new StubTranslator(),
            $dangerous,
        );
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<Role>
     */
    private function rolesOf(array $primitives): array
    {
        $roles = [];

        foreach ($primitives as $primitive) {
            if ($primitive instanceof RoundRect && $primitive->stroke !== null) {
                $roles[] = $primitive->stroke;
            }

            if ($primitive instanceof TextRun) {
                $roles[] = $primitive->role;
            }
        }

        return $roles;
    }
}
