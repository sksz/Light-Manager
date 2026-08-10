<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Terminal;

use LightManager\Infrastructure\Terminal\DeviceAttributesParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeviceAttributesParserTest extends TestCase
{
    private DeviceAttributesParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DeviceAttributesParser();
    }

    /** @return array<string, array{string}> */
    public static function responsesWithSixel(): array
    {
        return [
            'VT340 (Sixel na drugiej pozycji)' => ["\e[?62;4c"],
            'XTerm z pełną listą' => ["\e[?64;1;2;4;6;9;15;18;21;22c"],
            'sam parametr Sixel' => ["\e[?4c"],
            'Sixel na końcu listy' => ["\e[?62;6;4c"],
            'odpowiedź poprzedzona wpisanym znakiem' => ["q\e[?62;4c"],
            'odpowiedź z ogonem po sekwencji' => ["\e[?62;4cq"],
        ];
    }

    #[DataProvider('responsesWithSixel')]
    public function testDetectsSixelSupport(string $response): void
    {
        self::assertTrue($this->parser->isComplete($response));
        self::assertTrue($this->parser->supportsSixel($response));
    }

    /** @return array<string, array{string}> */
    public static function responsesWithoutSixel(): array
    {
        return [
            'VT100' => ["\e[?1;2c"],
            'VT220 bez Sixela' => ["\e[?62;1;6;9;15c"],
            'pusta lista parametrów' => ["\e[?c"],
            'parametr 40 nie jest parametrem 4' => ["\e[?40c"],
            'parametr 14 nie jest parametrem 4' => ["\e[?14c"],
        ];
    }

    #[DataProvider('responsesWithoutSixel')]
    public function testDetectsLackOfSixelSupport(string $response): void
    {
        self::assertTrue($this->parser->isComplete($response));
        self::assertFalse($this->parser->supportsSixel($response));
    }

    /** @return array<string, array{string}> */
    public static function incompleteResponses(): array
    {
        return [
            'pusty bufor' => [''],
            'sam prefiks' => ["\e[?"],
            'parametry bez bajtu końcowego' => ["\e[?62;4"],
            'brak znaku zapytania' => ["\e[62;4c"],
            'przypadkowy tekst' => ['jakiś tekst'],
            'naciśnięty klawisz, nie odpowiedź' => ["\e[A"],
        ];
    }

    #[DataProvider('incompleteResponses')]
    public function testTreatsIncompleteBufferAsNoResponse(string $buffer): void
    {
        self::assertFalse($this->parser->isComplete($buffer));
        self::assertFalse($this->parser->supportsSixel($buffer));
        self::assertSame([], $this->parser->parameters($buffer));
    }

    public function testStripLeavesBytesThatBelongToSomeoneElse(): void
    {
        // Przy starcie leci więcej niż jedno zapytanie — odpowiedź na inne
        // z nich nie może zniknąć razem z naszą.
        self::assertSame(
            "\e[4;540;960t",
            $this->parser->strip("\e[?62;4c\e[4;540;960t"),
        );
    }

    public function testStripKeepsTypedCharacters(): void
    {
        self::assertSame('qw', $this->parser->strip("q\e[?62;4cw"));
    }

    public function testStripReturnsBufferUntouchedWhenThereIsNoResponse(): void
    {
        self::assertSame("\e[A", $this->parser->strip("\e[A"));
    }

    public function testReturnsAllParameters(): void
    {
        self::assertSame([62, 4, 6], $this->parser->parameters("\e[?62;4;6c"));
    }

    public function testReadsResponseArrivingInPieces(): void
    {
        $chunks = ["\e", '[?62', ';4', 'c'];
        $buffer = '';

        foreach ($chunks as $index => $chunk) {
            $buffer .= $chunk;

            if ($index < count($chunks) - 1) {
                self::assertFalse($this->parser->isComplete($buffer));
            }
        }

        self::assertTrue($this->parser->isComplete($buffer));
        self::assertTrue($this->parser->supportsSixel($buffer));
    }
}
