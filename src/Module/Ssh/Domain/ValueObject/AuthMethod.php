<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Domain\ValueObject;

/**
 * Czym profil hosta się przedstawia (krok 48, D87 nr 4).
 *
 * Trzy drogi i wszystkie prowadzi klient OpenSSH — moduł nie uwierzytelnia
 * niczego sam, tylko mówi klientowi, czego ma spróbować. Kolejność przypadków
 * jest kolejnością malejącego bezpieczeństwa i to nie jest kosmetyka: pierwsza
 * wartość listy jest wartością domyślną w zakładce ustawień.
 *
 * Enum leży w `Domain`, bo `HostProfile` go w sobie nosi, a obiekt wartości nie
 * ma prawa sięgnąć po typ z warstwy leżącej na zewnątrz niego.
 */
enum AuthMethod: string
{
    /**
     * Agent — klucz **nie opuszcza** agenta, a aplikacja nie widzi go ani przez
     * chwilę. Jedyna droga, przy której nie ma czego zgubić.
     */
    case Agent = 'agent';

    /** Klucz z pliku wskazanego w profilu (`IdentityFile` plus `IdentitiesOnly`). */
    case Key = 'key';

    /**
     * Hasło wpisane w oknie z maskowanym polem.
     *
     * **W pliku hasła nie ma i nie będzie** — pytanie pada przy każdym
     * połączeniu, a odpowiedź nie przeżywa go ani o klatkę (D87 nr 4).
     */
    case Password = 'password';

    /** @return list<string> wartości do pozycji wyboru w zakładce ustawień */
    public static function choices(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public static function of(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
