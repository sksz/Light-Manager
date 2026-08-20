<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Domain\ValueObject;

use LightManager\Module\Docker\Domain\Exception\InvalidRegistryAddressException;

/**
 * Rejestr obrazów opisany wpisem książki adresowej (krok 61, etap 1).
 *
 * Rodzeństwo `DockerEnvironment` i celowo napisane tak samo: powstaje **wyłącznie
 * z wiersza kwerendy książki**, przez napisy i liczby, bez ani jednego typu
 * z cudzego modułu (reguła 15g).
 *
 * **Dlaczego rejestr jest osobnym rozdziałem, a nie polami rozdziału `docker`**
 * (D107): pola są rozłączne — demon opisuje się gniazdem, celem tunelu i trzema
 * ścieżkami materiału TLS, rejestr adresem, użytkownikiem i tokenem — a wpis
 * książki zostaje **jeden**, więc jedna maszyna ma prawo być naraz demonem
 * i rejestrem albo tylko jednym z nich. Wzorzec książki nie staje przy tym po raz
 * czwarty (D104): to nadal ta sama książka, tylko drugi rozdział.
 *
 * **Tokenu tu nie ma i być nie może.** Pole jest rodzaju `secret`, więc wiersz
 * spisu niesie w jego miejsce `set`/`unset`; treść dokłada `DockerQueries`
 * osobnym pytaniem, w chwili, gdy trzeba złożyć nagłówek — ta sama droga, którą
 * idą ścieżki TLS środowiska i ścieżka klucza w module sesji zdalnej.
 */
final readonly class ImageRegistry
{
    /**
     * Adres: host z opcjonalnym portem, pierwszy znak nie myślnik.
     *
     * Wzorzec przepisany z `DockerSettings::REGISTRY_PATTERN` (krok 54) i to jest
     * powtórzenie **zamierzone**: pozycja ustawień znika w tym kroku, a wzorzec
     * należy odtąd do pojęcia, a nie do zakładki.
     */
    public const ADDRESS_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]*(:[0-9]{1,5})?$/';

    public function __construct(
        public string $id,
        public string $name,
        public string $address,
        public string $user = '',
        public bool $isDefault = false,
        /** Rejestr w sieci lokalnej, z którym rozmawia się po `http`. */
        public bool $insecure = false,
        /** Czy token stoi we wpisie — **czy**, nigdy jaki. */
        public bool $hasToken = false,
    ) {
        if (preg_match(self::ADDRESS_PATTERN, $address) !== 1) {
            throw InvalidRegistryAddressException::forAddress($address);
        }
    }

    /**
     * Rejestr z wiersza kwerendy `address-book.entries registry` albo `null`, gdy
     * wiersz rejestru nie opisuje.
     *
     * Wiersz bez adresu **nie jest błędem**: wpis może istnieć po to, żeby nieść
     * pola zupełnie innego rozdziału — dokładnie tak, jak wiersz bez rodzaju
     * w `DockerEnvironment::fromRow()`. Adres niepoprawny **też** oddaje `null`,
     * a nie wyjątek: książka przyjmuje, co użytkownik wpisze (D105 nr 3), więc
     * spis rejestrów ma pominąć wpis zepsuty, a nie wywrócić się na nim.
     *
     * @param array<string, string|int|bool> $row
     */
    public static function fromRow(array $row): ?self
    {
        $id = $row['id'] ?? '';
        $address = $row['address'] ?? '';

        if (!is_string($id) || $id === '' || !is_string($address) || $address === '') {
            return null;
        }

        try {
            return new self(
                $id,
                is_string($row['name'] ?? null) ? $row['name'] : '',
                $address,
                self::text($row, 'user'),
                self::flag($row, 'default'),
                self::flag($row, 'insecure'),
                // Pole maskowane niesie w wierszu `set`/`unset`, nie treść.
                ($row['token'] ?? '') === 'set',
            );
        } catch (InvalidRegistryAddressException) {
            return null;
        }
    }

    /**
     * Przedrostek, którym nazwa obrazu wskazuje ten rejestr — adres plus
     * użytkownik, jeśli jest.
     */
    public function prefix(): string
    {
        return $this->user === '' ? $this->address : $this->address . '/' . $this->user;
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }

    /** @param array<string, string|int|bool> $row */
    private static function text(array $row, string $key): string
    {
        $value = $row[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /** @param array<string, string|int|bool> $row */
    private static function flag(array $row, string $key): bool
    {
        $value = $row[$key] ?? false;

        return $value === true || $value === 'true' || $value === 1 || $value === '1';
    }
}
