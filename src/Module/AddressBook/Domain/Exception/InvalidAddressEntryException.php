<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Domain\Exception;

use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Domain\Exception\DomainException;

/**
 * Wpis książki albo wartość, których nie da się zapisać (krok 60).
 *
 * Wyjątek **przedstawia się sam** (`DescribesProblem`), bo mówi o dziedzinie
 * modułu: rdzeń nie wie, czym jest wpis adresowy, i nie ma prawa dobierać dla
 * niego zdania po klasie (reguła 8, D42).
 *
 * **Czego ten wyjątek pilnuje, a czego nie.** Pilnuje tożsamości (identyfikator
 * musi wyglądać jak identyfikator), kształtu kluczy rozdziału i pola oraz
 * **higieny wartości** — znaków sterujących i długości, po której klatka
 * przestaje być rysowalna. **Nie pilnuje reguł dziedzinowych**: czy `10.0.0.5`
 * jest adresem, a `/home/x/.ssh/id_ed25519` ścieżką klucza, rozstrzyga ten, kto
 * te wartości czyta (D105 nr 3). Rodzaju pilnuje rozdział, nie wpis — bo wpis
 * deklaracji pól nie zna.
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
            sprintf('Entry id "%s" is not twelve hexadecimal characters.', $id),
            'module.address-book.entry.id.invalid',
            ['id' => $id],
        );
    }

    public static function invalidName(string $name): self
    {
        return new self(
            sprintf('Entry name "%s" contains control characters or is too long.', $name),
            'module.address-book.entry.name.invalid',
            ['name' => $name],
        );
    }

    public static function invalidChapter(string $chapter): self
    {
        return new self(
            sprintf('Chapter "%s" is not a usable chapter name.', $chapter),
            'module.address-book.chapter.invalid',
            ['chapter' => $chapter],
        );
    }

    public static function invalidField(string $field): self
    {
        return new self(
            sprintf('Field "%s" is not a usable field key.', $field),
            'module.address-book.field.invalid',
            ['field' => $field],
        );
    }

    public static function invalidValue(string $field): self
    {
        return new self(
            sprintf('Value of field "%s" contains control characters or is too long.', $field),
            'module.address-book.value.invalid',
            ['field' => $field],
        );
    }

    public static function notANumber(string $field, string $value): self
    {
        return new self(
            sprintf('Field "%s" expects a number, got "%s".', $field, $value),
            'module.address-book.value.number',
            ['field' => $field, 'value' => $value],
        );
    }

    /** @param list<string> $choices */
    public static function notInChoices(string $field, string $value, array $choices): self
    {
        return new self(
            sprintf('Field "%s" expects one of [%s], got "%s".', $field, implode(', ', $choices), $value),
            'module.address-book.value.choice',
            ['field' => $field, 'value' => $value, 'choices' => implode(', ', $choices)],
        );
    }

    /**
     * Odniesienie wskazujące wpis, którego nie ma — jedyna reguła spoza higieny,
     * której książka pilnuje sama (D105 nr 4). Wolno jej, bo to jedyna reguła,
     * którą **ona** zna: kto istnieje w książce, wie wyłącznie książka.
     */
    public static function unknownEntry(string $field, string $value): self
    {
        return new self(
            sprintf('Field "%s" points at entry "%s", which does not exist.', $field, $value),
            'module.address-book.value.entry',
            ['field' => $field, 'entry' => $value],
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
