<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Domain\ValueObject;

/**
 * Rodzaj środowiska Dockera — którędy idzie rozmowa z demonem (krok 58).
 *
 * Trzy drogi z rozstrzygnięcia D96 nr 2: gniazdo lokalne, tunel gniazda przez
 * `ssh -L` i TCP z TLS-em klienta. Rodzaj jest **daną wpisu**, a nie stałą
 * usługi — to jest całe odwrócenie, które ten krok robi wobec kroku 51.
 *
 * Wartości wchodzą do pliku `docker.json`, więc enum jest napisowy — jak
 * `AuthMethod` w książce hostów i z tego samego powodu: plik ma się dać
 * przeczytać oczami, a numer przypadku zmienia się przy pierwszej zmianie
 * kolejności.
 */
enum EnvironmentKind: string
{
    /** Gniazdo unixowe na tej maszynie — dzisiejsza, domyślna droga. */
    case LocalSocket = 'local';

    /** Gniazdo zdalnego demona przywiezione tunelem `ssh -L`. */
    case SshTunnel = 'tunnel';

    /** Demon wystawiony po sieci, z TLS-em klienta (`https://host:2376`). */
    case Tcp = 'tcp';

    public function labelKey(): string
    {
        return 'module.docker.env.kind.' . $this->value;
    }

    public static function of(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
