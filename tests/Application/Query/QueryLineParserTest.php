<?php

declare(strict_types=1);

namespace LightManager\Tests\Application\Query;

use LightManager\Application\Command\CommandLineParser;
use LightManager\Application\Query\QueryLineParser;
use LightManager\Application\Query\QueryRegistry;
use LightManager\Tests\Support\CountingQuery;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Rozbiór wiersza kwerendy — **tym samym parserem, co komendy** (krok 53).
 *
 * Testy pilnują tu jednej rzeczy ponad poprawność: że składnia jest ta sama.
 * Cudzysłowy, mapowanie pozycyjne i sprawdzenie rodzaju wartości pochodzą
 * z `CommandLineParser`, więc drugiego zestawu reguł nie ma.
 */
final class QueryLineParserTest extends TestCase
{
    private QueryRegistry $registry;

    private QueryLineParser $parser;

    protected function setUp(): void
    {
        $translator = new StubTranslator();
        $this->registry = new QueryRegistry();
        $this->registry->add('browser', [new CountingQuery('browser.entries')]);
        $this->parser = new QueryLineParser(new CommandLineParser($translator), $translator);
    }

    public function testAnEmptyLineIsRefusedWithAReason(): void
    {
        $line = $this->parser->parse('   ', $this->registry);

        self::assertFalse($line->isValid());
        self::assertSame('query.problem.empty', $line->problem?->text);
    }

    public function testAnUnknownNameIsRefusedWithAReason(): void
    {
        $line = $this->parser->parse('docker.images', $this->registry);

        self::assertFalse($line->isValid());
        self::assertSame('query.problem.unknown(name=docker.images)', $line->problem?->text);
    }

    public function testAKnownNameBindsItsArgumentsPositionally(): void
    {
        $line = $this->parser->parse('browser.entries 1', $this->registry);

        self::assertTrue($line->isValid());
        self::assertSame('browser.entries', $line->query?->name());
        self::assertSame(1, $line->input?->number('pane'));
    }

    /** Argument nieobowiązkowy wolno pominąć — reszta wiersza działa tak samo. */
    public function testAnOptionalArgumentMayBeLeftOut(): void
    {
        $line = $this->parser->parse('browser.entries', $this->registry);

        self::assertTrue($line->isValid());
        self::assertFalse($line->input?->has('pane'));
    }
}
