<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Domain\ValueObject;

use LightManager\Module\AddressBook\Domain\Exception\InvalidAddressEntryException;

/**
 * Wpis książki adresowej: **pojemnik z własną tożsamością** (krok 60, D105 nr 2).
 *
 * Trzy rzeczy niesie zawsze — **identyfikator**, **nazwę** i **adres** — a nazwa
 * i adres **mogą być puste**. Reszta zależy od modułu: pola dokładają rozdziały
 * (D105 nr 3), a wpis trzyma ich wartości pod identyfikatorem właściciela, nie
 * wiedząc, co znaczą. Nieprzezroczystość ładunku jest tu tą samą decyzją, co
 * w rdzeniowej `Book` z kroku 59, tyle że o piętro niżej.
 *
 * **Tożsamością jest identyfikator, a nie nazwa** — i to jest różnica wobec
 * wszystkich trzech książek, które ten moduł zastępuje (`HostBook`,
 * `EnvironmentBook`, `ClusterBook`). Powód jest wymierny: nazwa bywa pusta,
 * bywa powtórzona i **wolno ją zmienić**, a odniesienia do wpisu trzymają obcy
 * — wpis tunelowy modułu Dockera trzyma napis, za którym książka nie umie
 * pójść. Identyfikator jest losowy (osiem znaków szesnastkowych), więc
 * skasowanie wpisu nie zwalnia niczego, co kusiłoby, żeby użyć tego samego
 * ponownie.
 *
 * Samowalidacja pilnuje rzeczy, po których przewróci się cudzy kod, a nie
 * urody: znaków sterujących, długości i **wartości zaczynającej się od
 * myślnika**, którą `ssh` przeczytałby jako opcję niezależnie od cytowania
 * (lekcja kroku 48). Adres nie ma za to wzorca dziedzinowego — `10.0.0.5`,
 * `example.com:5432` i `unix:///var/run/docker.sock` są wszystkie adresami,
 * a książka nie jest od rozstrzygania, czyimi.
 */
final readonly class AddressEntry
{
    public const ID_LENGTH = 8;

    private const ID_PATTERN = '/^[0-9a-f]{8}$/';

    private const MAX_NAME_LENGTH = 64;

    private const MAX_ADDRESS_LENGTH = 255;

    /** Nazwa własna: cokolwiek czytelnego albo nic — byle bez znaków sterujących. */
    private const NAME_PATTERN = '/^[^\x00-\x1F\x7F]*$/u';

    /** Adres: bez znaków sterujących i bez odstępów — wchodzi do wiersza polecenia. */
    private const ADDRESS_PATTERN = '/^[^\x00-\x1F\x7F\s]*$/u';

    /** Identyfikator rozdziału to identyfikator modułu (`ModuleInterface::id()`). */
    private const CHAPTER_PATTERN = '/^[a-z][a-z0-9-]*$/';

    private const FIELD_PATTERN = '/^[a-zA-Z][a-zA-Z0-9_-]*$/';

    /**
     * @param array<string, array<string, string|int|bool>> $values rozdział → klucz pola → wartość
     */
    public function __construct(
        public string $id,
        public string $name = '',
        public string $address = '',
        public array $values = [],
    ) {
        $this->validate();
    }

    /**
     * Nowy identyfikator — losowy, ośmioznakowy, szesnastkowy (D105 nr 4).
     *
     * Losowość idzie z `random_bytes()`, bo `uniqid()` jest funkcją czasu, a dwa
     * wpisy dopisane w tej samej mikrosekundzie to nie jest sytuacja, której
     * chce się dowieść eksperymentalnie. Kolizję sprawdza książka — ona jedna
     * widzi wszystkie zajęte identyfikatory.
     */
    public static function newId(): string
    {
        return bin2hex(random_bytes(self::ID_LENGTH / 2));
    }

    /** To, co widać w spisie: nazwa, a gdy jej nie ma — identyfikator. */
    public function label(): string
    {
        return $this->name === '' ? $this->id : $this->name;
    }

    /** Tożsamość to identyfikator — nazwa i adres są zwykłymi polami. */
    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }

    public function withName(string $name): self
    {
        return new self($this->id, $name, $this->address, $this->values);
    }

    public function withAddress(string $address): self
    {
        return new self($this->id, $this->name, $address, $this->values);
    }

    /** Wartość pola rozdziału; `null`, gdy rozdział albo pole nie mają wartości. */
    public function value(string $chapter, string $field): string|int|bool|null
    {
        return $this->values[$chapter][$field] ?? null;
    }

    /** @return array<string, string|int|bool> wszystkie wartości jednego rozdziału */
    public function chapter(string $chapter): array
    {
        return $this->values[$chapter] ?? [];
    }

    public function withValue(string $chapter, string $field, string|int|bool $value): self
    {
        $values = $this->values;
        $values[$chapter][$field] = $value;

        return new self($this->id, $this->name, $this->address, $values);
    }

    /**
     * Wpis bez wartości rozdziału — używane, gdy moduł znika, a nie gdy
     * użytkownik czyści pole.
     */
    public function withoutChapter(string $chapter): self
    {
        $values = $this->values;
        unset($values[$chapter]);

        return new self($this->id, $this->name, $this->address, $values);
    }

    /** Czy wpis pasuje do zawężenia — po nazwie, adresie i identyfikatorze. */
    public function matches(string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        $needle = mb_strtolower($needle);

        return str_contains(mb_strtolower($this->name), $needle)
            || str_contains(mb_strtolower($this->address), $needle)
            || str_contains($this->id, $needle);
    }

    private function validate(): void
    {
        if (preg_match(self::ID_PATTERN, $this->id) !== 1) {
            throw InvalidAddressEntryException::invalidId($this->id);
        }

        if (
            mb_strlen($this->name) > self::MAX_NAME_LENGTH
            || preg_match(self::NAME_PATTERN, $this->name) !== 1
        ) {
            throw InvalidAddressEntryException::invalidName($this->name);
        }

        if (
            strlen($this->address) > self::MAX_ADDRESS_LENGTH
            || preg_match(self::ADDRESS_PATTERN, $this->address) !== 1
            || str_starts_with($this->address, '-')
        ) {
            throw InvalidAddressEntryException::invalidAddress($this->address);
        }

        $this->validateValues();
    }

    private function validateValues(): void
    {
        foreach ($this->values as $chapter => $fields) {
            if (preg_match(self::CHAPTER_PATTERN, $chapter) !== 1) {
                throw InvalidAddressEntryException::invalidChapter($chapter);
            }

            foreach (array_keys($fields) as $field) {
                if (preg_match(self::FIELD_PATTERN, $field) !== 1) {
                    throw InvalidAddressEntryException::invalidField($field);
                }
            }
        }
    }
}
