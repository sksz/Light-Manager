<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Filesystem;

use Collator;
use LightManager\Domain\ValueObject\Entry;
use Throwable;

/**
 * Porządkuje wpisy do postaci pokazywanej użytkownikowi: najpierw katalogi,
 * potem pliki, w obu grupach alfabetycznie bez rozróżniania wielkości liter.
 *
 * Porównaniem nazw zajmuje się `Collator` z rozszerzenia `intl`, które zna
 * reguły języka i ustawia `ą` obok `a`. Gdy rozszerzenia nie ma, wchodzi
 * ścieżka awaryjna: nazwy sprowadzone do małych liter z polskimi znakami
 * odwzorowanymi na podstawowe. Jest uboższa i celowo ograniczona do alfabetu,
 * którym posługuje się ten projekt.
 */
final class EntryComparator
{
    private const FALLBACK_TRANSLITERATION = [
        'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
        'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
    ];

    public function __construct(
        private readonly ?Collator $collator,
    ) {
    }

    public static function create(): self
    {
        return new self(self::createCollator());
    }

    /**
     * @param list<Entry> $entries
     *
     * @return list<Entry>
     */
    public function sort(array $entries): array
    {
        usort($entries, function (Entry $left, Entry $right): int {
            if ($left->isDirectory() !== $right->isDirectory()) {
                return $left->isDirectory() ? -1 : 1;
            }

            return $this->compareNames($left->name, $right->name);
        });

        return $entries;
    }

    private function compareNames(string $left, string $right): int
    {
        if ($this->collator !== null) {
            $result = $this->collator->compare($left, $right);

            if ($result !== false) {
                return $result;
            }
        }

        return strcmp($this->fold($left), $this->fold($right));
    }

    private function fold(string $name): string
    {
        return strtr(mb_strtolower($name, 'UTF-8'), self::FALLBACK_TRANSLITERATION);
    }

    private static function createCollator(): ?Collator
    {
        if (!extension_loaded('intl')) {
            return null;
        }

        try {
            $collator = new Collator(locale_get_default());
        } catch (Throwable) {
            return null;
        }

        // Wielkość liter ma nie wpływać na kolejność, ale nadal rozróżniamy
        // znaki diakrytyczne, żeby „lan” i „łan” nie zlały się w jedno.
        $collator->setStrength(Collator::SECONDARY);

        return $collator;
    }
}
