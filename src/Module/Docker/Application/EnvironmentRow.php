<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Module\Docker\Domain\ValueObject\DockerEnvironment;

/**
 * Jeden wiersz spisu środowisk — wpis własny albo kontekst klienta (krok 58).
 *
 * Adres jest tu w **dwóch postaciach** i to nie jest nadmiar: `address` widzi
 * właściciel na własnym ekranie (cel tunelu wolno mu pokazać), `publicAddress`
 * idzie do wierszy kwerendy `docker.environments` — a tam cel SSH i ścieżki
 * kluczy TLS **nie wchodzą** (reguła 11w, ta sama granica, którą `ssh.hosts`
 * trzyma dla odcisku klucza). Dla wpisu tunelu adresem publicznym jest ścieżka
 * gniazda lokalnego, czyli to, z czym moduł faktycznie rozmawia.
 */
final readonly class EnvironmentRow
{
    public function __construct(
        /**
         * Identyfikator wpisu książki; **pusty dla kontekstu klienta**, bo ten
         * w książce nie stoi i tożsamości poza własną nazwą nie ma (krok 60).
         */
        public string $id,
        public string $name,
        /** Rodzaj jako napis: wartość `EnvironmentKind` albo schemat adresu klienta. */
        public string $kind,
        public string $address,
        public string $publicAddress,
        public EnvironmentOrigin $origin,
        public bool $current,
        /** Wpis klienta przysłonięty wpisem własnym o tej samej nazwie. */
        public bool $shadowed,
        /** Wpis własny, którego dotyczą zmiana i usunięcie. */
        public ?DockerEnvironment $entry,
        /** Ścieżka gniazda dla wpisu klienta, gdy jego adres jest gniazdem. */
        public ?string $socketPath,
    ) {
    }
}
