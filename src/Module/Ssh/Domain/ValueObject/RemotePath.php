<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Domain\ValueObject;

use LightManager\Module\Ssh\Domain\Exception\InvalidRemotePathException;

/**
 * Bezwzględna ścieżka na zdalnej maszynie (krok 49).
 *
 * **Powtarza `DirectoryPath` przeglądarki świadomie**, a nie przez przeoczenie.
 * Moduł nigdy nie sięga do innego modułu (reguła 15), a wyniesienie ścieżki do
 * rdzenia byłoby odwróceniem D42 („rdzeń nie wie, czym jest katalog ani wpis”) —
 * czyli ceną nieporównanie wyższą niż jedna klasa wartości. Granica tego
 * powtarzania jest zapisana w `SKILL.md`: **wolno powtórzyć pojęcia domeny,
 * nie wolno powtórzyć mechanizmu rdzenia.**
 *
 * Jedna rzecz różni ją od lokalnej odpowiedniczki i nie jest kosmetyczna:
 * `DirectoryPath` porządkuje ścieżkę przez `realpath()`, czyli **pyta system
 * plików**. Tutaj nie ma kogo zapytać — system plików leży na innej maszynie,
 * a pytanie kosztowałoby obieg do serwera. Porządkowanie jest więc **czysto
 * tekstowe**: `.` znika, `..` zjada poprzedni człon, powtórzone ukośniki
 * schodzą do jednego. Dowiązanie symboliczne w środku ścieżki zostaje przez to
 * nierozwinięte — i to jest różnica widoczna dla użytkownika, nie tylko dla
 * kodu: `cd ..` po wejściu w dowiązanie wróci **tam, skąd się przyszło**,
 * a nie tam, gdzie prowadzi cel. Rozwinięcie należy do serwera (`realpath`
 * w `sftp`) i pyta się o nie wtedy, gdy naprawdę trzeba — przy katalogu
 * startowym.
 *
 * Separator jest **zawsze** ukośnikiem, nigdy `DIRECTORY_SEPARATOR`: mówimy
 * o ścieżce na cudzej maszynie, a protokół SFTP zna jeden separator niezależnie
 * od tego, na czym akurat działa aplikacja.
 */
final readonly class RemotePath
{
    public const SEPARATOR = '/';

    public const ROOT = '/';

    private function __construct(
        public string $value,
    ) {
    }

    /**
     * @throws InvalidRemotePathException gdy ścieżka jest pusta albo względna
     */
    public static function of(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidRemotePathException::forEmptyPath();
        }

        if (!str_starts_with($trimmed, self::SEPARATOR)) {
            throw InvalidRemotePathException::forRelativePath($trimmed);
        }

        return new self(self::normalize($trimmed));
    }

    public static function root(): self
    {
        return new self(self::ROOT);
    }

    /**
     * Ścieżka wpisu leżącego w tym katalogu.
     *
     * Nazwy nie sprawdzamy tutaj — robi to `RemoteEntry` przy powstaniu, a wpis
     * bez nazwy nie ma jak tu dojść.
     */
    public function child(string $name): self
    {
        return new self(self::normalize($this->prefix() . $name));
    }

    /** Katalog wyżej; korzeń oddaje sam siebie, bo wyżej już nic nie ma. */
    public function parent(): self
    {
        if ($this->isRoot()) {
            return $this;
        }

        $position = strrpos($this->value, self::SEPARATOR);

        return new self($position === 0 || $position === false ? self::ROOT : substr($this->value, 0, $position));
    }

    public function isRoot(): bool
    {
        return $this->value === self::ROOT;
    }

    /** Sama nazwa ostatniego członu; korzeń oddaje ukośnik, bo nazwy nie ma. */
    public function name(): string
    {
        if ($this->isRoot()) {
            return self::ROOT;
        }

        $position = strrpos($this->value, self::SEPARATOR);

        return $position === false ? $this->value : substr($this->value, $position + 1);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /** Ścieżka zakończona ukośnikiem — postać, do której dokleja się nazwę. */
    public function prefix(): string
    {
        return $this->isRoot() ? self::ROOT : $this->value . self::SEPARATOR;
    }

    /**
     * Porządkowanie tekstowe: `.`, `..` i powtórzone ukośniki.
     *
     * `..` powyżej korzenia **znika bez śladu**, zamiast wyprowadzać ścieżkę
     * poza drzewo: tak samo zachowuje się serwer SFTP, a ścieżka `/..` byłaby
     * napisem, którego nie da się pokazać użytkownikowi jako miejsca.
     */
    private static function normalize(string $path): string
    {
        $parts = [];

        foreach (explode(self::SEPARATOR, $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);

                continue;
            }

            $parts[] = $part;
        }

        return $parts === [] ? self::ROOT : self::SEPARATOR . implode(self::SEPARATOR, $parts);
    }
}
