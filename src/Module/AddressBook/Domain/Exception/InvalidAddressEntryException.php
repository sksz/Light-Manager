<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Domain\Exception;

use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Domain\Exception\DomainException;

/**
 * Powody, dla których wpis książki adresowej nie powstaje (krok 60).
 *
 * Wyjątek modułu **przedstawia się sam** (reguła 8, krok 21): niesie klucz
 * katalogu wraz z parametrami, bo `ProblemPresenter` rdzenia nie ma prawa znać
 * nazw modułu.
 *
 * Powodów jest mało i to jest celowe. Wpis książki jest **pojemnikiem**
 * (D105 nr 2): nazwa i adres wolno mieć puste, więc jedyne, czego wpis pilnuje,
 * to rzeczy, po których cudzy kod się przewróci — znaki sterujące, długość
 * i wartość zaczynająca się od myślnika, którą `ssh` przeczytałby jako opcję
 * (ta sama lekcja, co w kroku 48).
 */
final class InvalidAddressEntryException extends DomainException implements DescribesProblem
{
    /** @param array<string, string> $problemParameters */
    private function __construct(
        string $message,
        private readonly string $problemKey,
        private readonly array $problemParameters,
    ) {
        parent::__construct($message);
    }

    public static function invalidId(string $id): self
    {
        return new self(
            sprintf('Address entry id "%s" is not eight hexadecimal characters.', $id),
            'module.address-book.entry.id.invalid',
            ['id' => $id],
        );
    }

    public static function invalidName(string $name): self
    {
        return new self(
            sprintf('Address entry name "%s" contains control characters or is too long.', $name),
            'module.address-book.entry.name.invalid',
            ['name' => $name],
        );
    }

    public static function invalidAddress(string $address): self
    {
        return new self(
            sprintf('Address "%s" is not a usable address.', $address),
            'module.address-book.entry.address.invalid',
            ['address' => $address],
        );
    }

    public static function invalidChapter(string $chapter): self
    {
        return new self(
            sprintf('Chapter owner "%s" is not a module identifier.', $chapter),
            'module.address-book.entry.chapter.invalid',
            ['chapter' => $chapter],
        );
    }

    public static function invalidField(string $field): self
    {
        return new self(
            sprintf('Chapter field "%s" is not a usable field key.', $field),
            'module.address-book.entry.field.invalid',
            ['field' => $field],
        );
    }

    public function problemKey(): string
    {
        return $this->problemKey;
    }

    public function problemParameters(): array
    {
        return $this->problemParameters;
    }
}
