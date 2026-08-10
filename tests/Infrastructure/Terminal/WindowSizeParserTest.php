<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Terminal;

use LightManager\Infrastructure\Terminal\WindowSizeParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WindowSizeParserTest extends TestCase
{
    private WindowSizeParser $parser;

    protected function setUp(): void
    {
        $this->parser = new WindowSizeParser();
    }

    public function testReadsWidthAndHeightInTerminalOrder(): void
    {
        // Terminal podaje najpierw wysokość, potem szerokość.
        $size = $this->parser->parse("\e[4;540;960t");

        self::assertSame(['width' => 960, 'height' => 540], $size);
    }

    public function testFindsResponseSurroundedByOtherBytes(): void
    {
        self::assertTrue($this->parser->isComplete("q\e[4;1080;1920tx"));
        self::assertSame(['width' => 1920, 'height' => 1080], $this->parser->parse("q\e[4;1080;1920tx"));
    }

    /** @return array<string, array{string}> */
    public static function unusableBuffers(): array
    {
        return [
            'pusty bufor' => [''],
            'sam prefiks' => ["\e[4;"],
            'bez bajtu końcowego' => ["\e[4;540;960"],
            'inna odpowiedź (DA1)' => ["\e[?62;4c"],
            'odpowiedź o rozmiarze w komórkach' => ["\e[8;24;80t"],
            'zerowe wymiary' => ["\e[4;0;0t"],
            'przypadkowy tekst' => ['960x540'],
        ];
    }

    #[DataProvider('unusableBuffers')]
    public function testReturnsNothingForUnusableBuffer(string $buffer): void
    {
        self::assertNull($this->parser->parse($buffer));
    }

    public function testStripLeavesBytesThatBelongToSomeoneElse(): void
    {
        self::assertSame(
            "\e[?62;4c",
            $this->parser->strip("\e[4;540;960t\e[?62;4c"),
        );
    }

    public function testStripReturnsBufferUntouchedWhenThereIsNoResponse(): void
    {
        self::assertSame("\e[?62;4c", $this->parser->strip("\e[?62;4c"));
    }

    public function testTreatsIncompleteResponseAsNotReady(): void
    {
        self::assertFalse($this->parser->isComplete("\e[4;540;96"));
        self::assertTrue($this->parser->isComplete("\e[4;540;960t"));
    }
}
