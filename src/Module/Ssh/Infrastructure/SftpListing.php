<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Infrastructure;

use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntry;

/**
 * Rozczytany wypis `sftp` — trzy rzeczy, które da się z niego wyjąć (krok 49).
 *
 * Klasa mieszka w `Infrastructure`, bo jest **postacią wyjścia programu**,
 * a nie pojęciem dziedziny: wpisy pojadą dalej jako `RemoteDirectory`, katalog
 * roboczy jako `RemotePath`, a komunikaty zamienią się w klucz katalogu napisów
 * i dalej nie pójdą.
 *
 * Komunikaty niesie się **zawsze**, a używa **tylko przy niepowodzeniu**. Powód
 * jest zapisany w parserze: strumień błędów jest z wypisem sklejony, więc każdy
 * wiersz nie do rozczytania trafia tutaj — także pierwsza linia nazwy zawierającej
 * znak nowej linii. Przy powodzeniu nikt tu nie zagląda i nikomu to nie szkodzi.
 */
final readonly class SftpListing
{
    /**
     * @param list<RemoteEntry> $entries
     * @param list<string>      $messages wiersze, których nie dało się rozczytać jako wpisy
     * @param string|null       $workingDirectory odpowiedź na `pwd`, gdy o nią pytano
     */
    public function __construct(
        public array $entries,
        public array $messages = [],
        public ?string $workingDirectory = null,
    ) {
    }

    /** Wszystkie komunikaty w jednym napisie — postać, której szuka czytnik powodów. */
    public function messageText(): string
    {
        return implode("\n", $this->messages);
    }
}
