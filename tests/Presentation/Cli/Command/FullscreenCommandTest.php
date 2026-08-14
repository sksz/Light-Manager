<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Cli\Command;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Domain\ValueObject\MessageTone;
use LightManager\Presentation\Cli\Command\FullscreenCommand;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * `core.fullscreen` (krok 37) — komenda, która o GLFW nie wie nic.
 *
 * Przełączenie przychodzi domknięciem, więc cała komenda daje się sprawdzić bez
 * otwartego okna: test podstawia domknięcie mówiące, w którym stanie okno się
 * znalazło, i patrzy, czy komunikat mówi to samo.
 */
final class FullscreenCommandTest extends TestCase
{
    public function testTurningFullscreenOnSaysSo(): void
    {
        $message = $this->execute(true)->message;

        self::assertNotNull($message);
        self::assertSame('command.fullscreen.on', $message->text);
        self::assertSame(MessageTone::Info, $message->tone);
    }

    public function testTurningFullscreenOffSaysSo(): void
    {
        $message = $this->execute(false)->message;

        self::assertNotNull($message);
        self::assertSame('command.fullscreen.off', $message->text);
    }

    public function testCommandTakesNoArguments(): void
    {
        $command = new FullscreenCommand(new StubTranslator(), static fn (): bool => true);

        self::assertSame('core.fullscreen', $command->name());
        self::assertSame('command.core.fullscreen', $command->descriptionKey());
        self::assertSame([], $command->arguments());
    }

    private function execute(bool $fullscreen): CommandOutcome
    {
        $command = new FullscreenCommand(new StubTranslator(), static fn (): bool => $fullscreen);

        return $command->execute(new CommandInput([]));
    }
}
