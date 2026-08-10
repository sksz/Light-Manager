<?php

declare(strict_types=1);

namespace LightManager\Tests\Application\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandArgumentKind;
use LightManager\Application\Command\CommandLineParser;
use LightManager\Application\Command\CommandRegistry;
use LightManager\Domain\ValueObject\MessageTone;
use LightManager\Tests\Support\FakeCommand;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommandLineParserTest extends TestCase
{
    private CommandLineParser $parser;

    private CommandRegistry $registry;

    protected function setUp(): void
    {
        $this->parser = new CommandLineParser(new StubTranslator());
        $this->registry = new CommandRegistry();
        $this->registry->add('core', [
            new FakeCommand('core.help'),
            new FakeCommand('core.hidden'),
            new FakeCommand('core.jump', [
                new CommandArgument('path', 'command.argument.path', CommandArgumentKind::Path),
            ]),
            new FakeCommand('core.palette', [
                new CommandArgument('colors', 'command.argument.colors', CommandArgumentKind::Number),
            ]),
        ]);
    }

    /** @return array<string, array{string, list<string>}> */
    public static function lines(): array
    {
        return [
            'puste' => ['', []],
            'sama nazwa' => ['core.help', ['core.help']],
            'nazwa i argument' => ['core.jump /tmp', ['core.jump', '/tmp']],
            'nadmiarowe odstępy' => ['  core.jump    /tmp  ', ['core.jump', '/tmp']],
            'cudzysłów podwójny' => ['core.jump "/home/moje pliki"', ['core.jump', '/home/moje pliki']],
            'cudzysłów pojedynczy' => ["core.jump '/home/moje pliki'", ['core.jump', '/home/moje pliki']],
            'cudzysłów w środku słowa' => ['core.jump /home/"moje pliki"', ['core.jump', '/home/moje pliki']],
            'niedomknięty cudzysłów bierze resztę' => ['core.jump "/home/moje', ['core.jump', '/home/moje']],
        ];
    }

    /** @param list<string> $words */
    #[DataProvider('lines')]
    public function testSplitsLineIntoWordsHonouringQuotes(string $line, array $words): void
    {
        self::assertSame($words, $this->parser->words($line));
    }

    public function testCompletionPointsAtTheCommandNameWhileItIsBeingTyped(): void
    {
        $completion = $this->parser->completion('core.he');

        self::assertTrue($completion->completesName());
        self::assertSame('core.he', $completion->prefix);
    }

    public function testCompletionPointsAtTheFirstArgumentAfterASpace(): void
    {
        $completion = $this->parser->completion('core.jump ');

        self::assertFalse($completion->completesName());
        self::assertSame('core.jump', $completion->name);
        self::assertSame(0, $completion->argumentIndex);
        self::assertSame('', $completion->prefix);
    }

    public function testCompletionCarriesWhatWasTypedOfTheArgument(): void
    {
        $completion = $this->parser->completion('core.jump /ho');

        self::assertSame(0, $completion->argumentIndex);
        self::assertSame('/ho', $completion->prefix);
    }

    public function testEmptyLineIsCompletingTheName(): void
    {
        $completion = $this->parser->completion('');

        self::assertTrue($completion->completesName());
        self::assertSame('', $completion->prefix);
    }

    public function testBindsValuesToNamesFromTheDeclaration(): void
    {
        $parsed = $this->parser->parse('core.jump "/home/moje pliki"', $this->registry);

        self::assertTrue($parsed->isValid());
        self::assertNotNull($parsed->input);
        self::assertSame('/home/moje pliki', $parsed->input->text('path'));
    }

    public function testUnknownNameIsRejectedWithItsOwnMessage(): void
    {
        $parsed = $this->parser->parse('core.nothing', $this->registry);

        self::assertFalse($parsed->isValid());
        self::assertNotNull($parsed->problem);
        self::assertSame(MessageTone::Error, $parsed->problem->tone);
        self::assertStringContainsString('command.problem.unknown', $parsed->problem->text);
    }

    public function testMissingRequiredArgumentIsRejected(): void
    {
        $parsed = $this->parser->parse('core.jump', $this->registry);

        self::assertFalse($parsed->isValid());
        self::assertNotNull($parsed->problem);
        self::assertStringContainsString('command.problem.missing', $parsed->problem->text);
    }

    public function testValueBeyondTheDeclarationIsRejected(): void
    {
        $parsed = $this->parser->parse('core.help teraz', $this->registry);

        self::assertFalse($parsed->isValid());
        self::assertNotNull($parsed->problem);
        self::assertStringContainsString('command.problem.extra', $parsed->problem->text);
    }

    public function testNumberArgumentRejectsSomethingThatIsNotANumber(): void
    {
        $parsed = $this->parser->parse('core.palette dużo', $this->registry);

        self::assertFalse($parsed->isValid());
        self::assertNotNull($parsed->problem);
        self::assertStringContainsString('command.problem.number', $parsed->problem->text);
    }

    public function testNumberArgumentAcceptsDigits(): void
    {
        $parsed = $this->parser->parse('core.palette 64', $this->registry);

        self::assertTrue($parsed->isValid());
        self::assertNotNull($parsed->input);
        self::assertSame(64, $parsed->input->number('colors'));
    }

    /** Istnienia ścieżki parser nie sprawdza — o tym wie już sama komenda. */
    public function testPathArgumentIsNotCheckedAgainstTheFilesystem(): void
    {
        $parsed = $this->parser->parse('core.jump /nie/ma/takiego/katalogu', $this->registry);

        self::assertTrue($parsed->isValid());
    }

    public function testEmptyLineIsRejected(): void
    {
        $parsed = $this->parser->parse('   ', $this->registry);

        self::assertFalse($parsed->isValid());
        self::assertNotNull($parsed->problem);
        self::assertStringContainsString('command.problem.empty', $parsed->problem->text);
    }
}
