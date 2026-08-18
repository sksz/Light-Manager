<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Domain\ValueObject;

/**
 * Czym moduł przedstawia się hostowi — **własność modułu, nie książki**
 * (krok 60).
 *
 * Powstała z granicy 11w, a nie z potrzeby porządku: książka adresowa jest
 * czytana kwerendą przez **każdy** moduł, a ścieżka klucza prywatnego mówi
 * obcemu, gdzie tego klucza szukać. Adres wpisu leży więc w książce, a to —
 * w sekcji `ssh` dokumentu stanu, pod identyfikatorem wpisu.
 *
 * Bez walidacji ścieżki, bo tę robi `HostProfile`, do którego obie wartości
 * i tak trafiają; podwójna kończyłaby się dwoma zdaniami o tym samym błędzie.
 */
final readonly class HostCredentials
{
    public function __construct(
        public AuthMethod $auth = AuthMethod::Agent,
        public ?string $keyPath = null,
    ) {
    }

    public static function default(AuthMethod $auth): self
    {
        return new self($auth);
    }

    public function equals(self $other): bool
    {
        return $this->auth === $other->auth && $this->keyPath === $other->keyPath;
    }
}
