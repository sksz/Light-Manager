<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Presentation\Ui\KeyBinding;
use PHPUnit\Framework\TestCase;

final class KeyBindingTest extends TestCase
{
    public function testMatchesNamedKey(): void
    {
        $binding = KeyBinding::of([Key::Enter, Key::ArrowRight], 'help.key.open');

        self::assertTrue($binding->matches(KeyPress::special(Key::Enter, "\r")));
        self::assertTrue($binding->matches(KeyPress::special(Key::ArrowRight, "\e[C")));
        self::assertFalse($binding->matches(KeyPress::special(Key::Escape, "\e")));
    }

    public function testMatchesPlainCharacter(): void
    {
        $binding = KeyBinding::character('.', 'help.key.hidden');

        self::assertTrue($binding->matches(KeyPress::character('.')));
        self::assertFalse($binding->matches(KeyPress::character(',')));
    }

    public function testCtrlBindingMatchesOnlyWithCtrl(): void
    {
        $binding = KeyBinding::ctrl('d', 'module.file-info.key.open');

        self::assertTrue($binding->matches(KeyPress::ctrl('d')));
        self::assertFalse($binding->matches(KeyPress::character('d')));
    }

    /**
     * Bez porównania flagi wiązanie na gołą literę łapałoby skrót z `Ctrl` —
     * a od kroku 20 to właśnie na `Ctrl`+literze wiszą skróty modułów.
     */
    public function testPlainCharacterBindingDoesNotMatchCtrl(): void
    {
        $binding = KeyBinding::character('d', 'help.key.hidden');

        self::assertFalse($binding->matches(KeyPress::ctrl('d')));
    }

    public function testDisplaysCtrlWithUppercaseLetter(): void
    {
        self::assertSame('Ctrl+D', KeyBinding::ctrl('d', 'whatever')->display());
        self::assertSame('.', KeyBinding::character('.', 'whatever')->display());
        self::assertSame('Enter / →', KeyBinding::of([Key::Enter, Key::ArrowRight], 'whatever')->display());
    }
}
