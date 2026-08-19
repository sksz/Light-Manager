<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Domain\ValueObject;

use LightManager\Module\AddressBook\Domain\Exception\InvalidAddressEntryException;

/**
 * Wpis książki adresowej: **tożsamość i nazwa, a reszta jest cudza**
 * (krok 60, D104 nr 5).
 *
 * Wpis niesie sam z siebie dokładnie dwie rzeczy — **identyfikator** i **nazwę**
 * — a wszystko poza tym jest **wartością rozdziału**: mapą `rozdział → pole →
 * wartość`, dla wpisu nieprzezroczystą. Adresu wśród pól własnych **nie ma**
 * i to jest decyzja, nie przeoczenie: adres jest polem rozdziału jak każde inne,
 * deklarowanym przez tego, kto go używa, a książka nie wie, co to adres.
 * Nieprzezroczystość ładunku jest tu tą samą decyzją, co w rdzeniowej `Book`
 * z kroku 59, tyle że o piętro niżej.
 *
 * **Tożsamością jest identyfikator, a nie nazwa** — i to jest odwrócenie wzorca
 * trzech książek, które ten moduł zastępuje (`HostBook`, `EnvironmentBook`,
 * `ClusterBook`). Powód jest wymierny: nazwa bywa pusta, bywa powtórzona
 * i **wolno ją zmienić**, a odniesienia do wpisu trzymają obcy — wpis tunelowy
 * modułu Dockera trzymał do tego kroku napis, za którym książka nie umiała
 * pójść. Identyfikator jest losowy (dwanaście znaków szesnastkowych, D105 nr 1),
 * więc skasowanie wpisu nie zwalnia niczego, co kusiłoby, żeby użyć tego samego
 * ponownie.
 *
 * Samowalidacja pilnuje **tożsamości, kształtu kluczy i higieny wartości**,
 * nigdy reguł dziedzinowych (D105 nr 3) — te należą do czytającego.
 */
final readonly class AddressEntry
{
    /** Długość identyfikatora w znakach szesnastkowych (D105 nr 1). */
    public const ID_LENGTH = 12;

    /** Tyle bajtów losowych daje dwanaście znaków szesnastkowych. */
    private const ID_BYTES = 6;

    private const ID_PATTERN = '/^[0-9a-f]{12}$/D';

    private const MAX_NAME_LENGTH = 64;

    /** Po tej długości wartość przestaje być czymkolwiek, co da się pokazać w wierszu. */
    private const MAX_VALUE_LENGTH = 1024;

    /**
     * Nazwa własna: cokolwiek czytelnego albo nic — byle bez znaków sterujących.
     *
     * Modyfikator `D` jest tu **warunkiem poprawności, nie ozdobą**: bez niego
     * `$` dopuszcza jeden znak nowej linii **na końcu**, więc nazwa `"biuro\n"`
     * przechodziłaby przez wzorzec zakazujący znaków sterujących. Ta sama
     * pułapka dotyczy pozostałych wzorców w tej klasie i wszystkie mają `D`.
     */
    private const NAME_PATTERN = '/^[^\x00-\x1F\x7F]*$/uD';

    /** Wartość: bez znaków sterujących; odstępy wolno, bo nazwa własna miejsca bywa zdaniem. */
    private const VALUE_PATTERN = '/^[^\x00-\x1F\x7F]*$/uD';

    /** Rozdział nazywa się jak moduł, bo zwykle jest nim (`ModuleInterface::id()`). */
    private const CHAPTER_PATTERN = '/^[a-z][a-z0-9-]*$/D';

    private const FIELD_PATTERN = '/^[a-zA-Z][a-zA-Z0-9_-]*$/D';

    /**
     * @param array<string, array<string, string|int|bool>> $values rozdział → klucz pola → wartość
     */
    public function __construct(
        public string $id,
        public string $name = '',
        public array $values = [],
    ) {
        $this->validate();
    }

    /**
     * Nowy identyfikator — losowy, dwunastoznakowy, szesnastkowy (D105 nr 1).
     *
     * Losowość idzie z `random_bytes()`, bo `uniqid()` jest funkcją czasu, a dwa
     * wpisy dopisane w tej samej mikrosekundzie to nie jest sytuacja, której
     * chce się dowieść eksperymentalnie. Kolizję sprawdza książka — ona jedna
     * widzi wszystkie zajęte identyfikatory.
     */
    public static function newId(): string
    {
        return bin2hex(random_bytes(self::ID_BYTES));
    }

    /** To, co widać w spisie: nazwa, a gdy jej nie ma — sam identyfikator. */
    public function label(): string
    {
        return $this->name === '' ? $this->id : $this->name;
    }

    public function withName(string $name): self
    {
        return new self($this->id, $name, $this->values);
    }

    /** @return array<string, string|int|bool> wartości jednego rozdziału */
    public function valuesOf(string $chapter): array
    {
        return $this->values[$chapter] ?? [];
    }

    /** Wartość pola; `null`, gdy wpis jej nie ma — a `null` nie jest wartością (patrz `withValue()`). */
    public function value(string $chapter, string $field): string|int|bool|null
    {
        return $this->values[$chapter][$field] ?? null;
    }

    public function hasChapter(string $chapter): bool
    {
        return ($this->values[$chapter] ?? []) !== [];
    }

    /** @return list<string> rozdziały, w których wpis ma cokolwiek */
    public function chapters(): array
    {
        $chapters = [];

        foreach ($this->values as $chapter => $fields) {
            if ($fields !== []) {
                $chapters[] = $chapter;
            }
        }

        return $chapters;
    }

    public function withValue(string $chapter, string $field, string|int|bool $value): self
    {
        $values = $this->values;
        $values[$chapter][$field] = $value;

        return new self($this->id, $this->name, $values);
    }

    /** Bez jednego pola; rozdział pusty znika w całości, żeby nie udawał, że coś w nim jest. */
    public function withoutValue(string $chapter, string $field): self
    {
        $values = $this->values;
        unset($values[$chapter][$field]);

        if (($values[$chapter] ?? []) === []) {
            unset($values[$chapter]);
        }

        return new self($this->id, $this->name, $values);
    }

    /** Bez całego rozdziału — droga komendy `address-book.clear <wpis> <rozdział>`. */
    public function withoutChapter(string $chapter): self
    {
        $values = $this->values;
        unset($values[$chapter]);

        return new self($this->id, $this->name, $values);
    }

    /** Tożsamością jest identyfikator, więc porównuje się jego — nie nazwę i nie wartości. */
    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }

    private function validate(): void
    {
        if (preg_match(self::ID_PATTERN, $this->id) !== 1) {
            throw InvalidAddressEntryException::invalidId($this->id);
        }

        if (mb_strlen($this->name) > self::MAX_NAME_LENGTH || preg_match(self::NAME_PATTERN, $this->name) !== 1) {
            throw InvalidAddressEntryException::invalidName($this->name);
        }

        foreach ($this->values as $chapter => $fields) {
            $this->validateChapter($chapter, $fields);
        }
    }

    /** @param array<string, string|int|bool> $fields */
    private function validateChapter(string $chapter, array $fields): void
    {
        if (preg_match(self::CHAPTER_PATTERN, $chapter) !== 1) {
            throw InvalidAddressEntryException::invalidChapter($chapter);
        }

        foreach ($fields as $field => $value) {
            if (preg_match(self::FIELD_PATTERN, $field) !== 1) {
                throw InvalidAddressEntryException::invalidField($field);
            }

            if (!is_string($value)) {
                continue;
            }

            if (mb_strlen($value) > self::MAX_VALUE_LENGTH || preg_match(self::VALUE_PATTERN, $value) !== 1) {
                throw InvalidAddressEntryException::invalidValue($field);
            }
        }
    }
}
