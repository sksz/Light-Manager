<?php

declare(strict_types=1);

namespace LightManager\Tests\Domain\ValueObject;

use LightManager\Domain\Exception\InvalidMessageException;
use LightManager\Domain\ValueObject\Message;
use LightManager\Domain\ValueObject\MessageTone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    public function testCarriesTextAndTone(): void
    {
        $message = Message::error('Brak uprawnień.');

        self::assertSame('Brak uprawnień.', $message->text);
        self::assertSame(MessageTone::Error, $message->tone);
    }

    /** @return array<string, array{Message, MessageTone, string}> */
    public static function tones(): array
    {
        return [
            'informacja' => [Message::info('gotowe'), MessageTone::Info, '·'],
            'ostrzeżenie' => [Message::warning('tylko do odczytu'), MessageTone::Warning, '!'],
            'błąd' => [Message::error('brak uprawnień'), MessageTone::Error, '×'],
        ];
    }

    #[DataProvider('tones')]
    public function testEachToneHasItsOwnMarker(Message $message, MessageTone $tone, string $marker): void
    {
        self::assertSame($tone, $message->tone);
        self::assertSame($marker, $message->tone->marker());
        self::assertStringStartsWith($marker . ' ', $message->marked());
    }

    /**
     * Znak wiodący jest po to, żeby ton dało się odczytać także tam, gdzie
     * kolory zawodzą: po kwantyzacji Sixela i w terminalu bez palety 256.
     */
    public function testMarkedTextKeepsTheOriginalContent(): void
    {
        self::assertSame('× Brak uprawnień.', Message::error('Brak uprawnień.')->marked());
    }

    /** @return array<string, array{string}> */
    public static function emptyTexts(): array
    {
        return [
            'pusty string' => [''],
            'same spacje' => ['   '],
            'sam znak nowej linii' => ["\n"],
        ];
    }

    #[DataProvider('emptyTexts')]
    public function testRejectsEmptyText(string $text): void
    {
        $this->expectException(InvalidMessageException::class);
        $this->expectExceptionMessage('A message needs non-empty text.');

        Message::info($text);
    }

    public function testComparesByValue(): void
    {
        $message = Message::error('uwaga');

        self::assertTrue($message->equals(Message::error('uwaga')));
        self::assertFalse($message->equals(Message::warning('uwaga')));
        self::assertFalse($message->equals(Message::error('inna uwaga')));
    }
}
