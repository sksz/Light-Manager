<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Domain\ValueObject;

/**
 * Odcisk klucza hosta — to, co użytkownik ogląda, zanim powie „tak” (krok 48).
 *
 * **Postać jest ta sama, którą pokazuje `ssh`**, i to jest zysk nieoczywisty,
 * bo plan zakładał inną. `ssh2_fingerprint()` z odrzuconego wariantu umiał
 * wyłącznie MD5 i SHA1, więc krok godził się pokazywać SHA1 — czyli napis,
 * którego użytkownik nie miałby z czym porównać, bo dzisiejszy OpenSSH mówi
 * SHA256. Droga przez `ssh-keygen -lf` oddaje `SHA256:…` wprost (D87 nr 5).
 *
 * Obiekt **niczego nie waliduje wzorcem** i to jest celowe: odcisk pochodzi
 * z wyjścia `ssh-keygen`, więc jedyną rzeczą, którą warto o nim wiedzieć, jest
 * to, czy w ogóle udało się go odczytać. Za odsiew wierszy nie do rozczytania
 * odpowiada ten, kto je czyta (`FingerprintParser`), a nie ten, kto je nosi —
 * inaczej odpowiedź serwera nieznanego typu kończyłaby się wyjątkiem w środku
 * pytania o zaufanie.
 */
final readonly class HostFingerprint
{
    public function __construct(
        /** Typ klucza tak, jak nazywa go `ssh-keygen`: `ED25519`, `RSA`, `ECDSA`. */
        public string $type,
        /** Odcisk wraz z przedrostkiem funkcji skrótu: `SHA256:…`. */
        public string $value,
        /** Długość klucza w bitach; `null`, gdy `ssh-keygen` jej nie podał. */
        public ?int $bits = null,
    ) {
    }

    /** Jeden wiersz dla okna pytania: `ED25519 SHA256:…`. */
    public function describe(): string
    {
        return $this->type . ' ' . $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->value === $other->value;
    }
}
