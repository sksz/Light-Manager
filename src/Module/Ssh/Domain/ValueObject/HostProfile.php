<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Domain\ValueObject;

use LightManager\Module\Ssh\Domain\Exception\InvalidHostProfileException;

/**
 * Wpis książki hostów: z kim, jako kto i czym się przedstawiając (krok 48).
 *
 * **Obiekt wartości, więc pilnuje się sam** (reguła 6) — i tutaj samowalidacja
 * robi więcej niż zwykle. Trzy z tych pól trafiają do wiersza polecenia
 * uruchamianego przez powłokę, więc każde ma wzorzec, a wzorce są **wąskie
 * z założenia**: łatwiej dopuścić brakujący znak później, niż odebrać
 * dopuszczony za szeroko.
 *
 * Cytowanie argumentów pozostaje mimo to obowiązkiem usługi
 * (`BackgroundProcessPort` mówi o tym wprost). Te wzorce są **drugą** warstwą,
 * a nie jedyną — i pilnują rzeczy, której cytowanie upilnować nie może: **żadna
 * wartość nie zaczyna się od `-`**, bo `ssh` przeczytałby ją jako opcję,
 * niezależnie od tego, jak dokładnie zacytowała ją powłoka.
 *
 * `remoteDirectory` nie ma dziś odbiorcy w kodzie i **jest to świadome**: pole
 * niesie się w pliku stanu od pierwszego dnia, bo krok 49 dopisze do tego samego
 * dokumentu ostatni katalog, a schemat dokumentu ma to unieść bez migracji.
 * Ekran go nie pokazuje i nikt go nie czyta — to dana przechowywana, nie funkcja
 * bez użytkownika.
 */
final readonly class HostProfile
{
    public const DEFAULT_PORT = 22;

    private const MAX_NAME_LENGTH = 64;

    private const MAX_HOST_LENGTH = 255;

    /** Nazwa własna: cokolwiek czytelnego, byle bez znaków sterujących. */
    private const NAME_PATTERN = '/^[^\x00-\x1F\x7F]+$/u';

    /**
     * Host: nazwa domenowa, adres IPv4 albo IPv6 (stąd dwukropki i nawiasy).
     * Pierwszy znak **nie może** być myślnikiem — patrz opis klasy.
     */
    private const HOST_PATTERN = '/^[A-Za-z0-9:\[][A-Za-z0-9._:\]-]*$/';

    /** Login: to, co dopuszczają `useradd` i `adduser`, bez myślnika na początku. */
    private const USER_PATTERN = '/^[A-Za-z0-9_][A-Za-z0-9._-]*$/';

    public function __construct(
        public string $name,
        public string $host,
        public int $port = self::DEFAULT_PORT,
        public string $user = '',
        public AuthMethod $auth = AuthMethod::Agent,
        public ?string $keyPath = null,
        public ?string $remoteDirectory = null,
    ) {
        $this->validate();
    }

    /** Cel dla klienta: `użytkownik@host` albo sam host, gdy loginu nie podano. */
    public function target(): string
    {
        return $this->user === '' ? $this->host : $this->user . '@' . $this->host;
    }

    /** To, co widać w kolumnie spisu — z portem, gdy nie jest domyślny. */
    public function label(): string
    {
        return $this->port === self::DEFAULT_PORT
            ? $this->target()
            : $this->target() . ':' . $this->port;
    }

    /**
     * Tożsamość wpisu to jego **nazwa własna**, a nie `użytkownik@host`.
     *
     * Dwa wpisy o tym samym adresie i różnym sposobie uwierzytelnienia są dwoma
     * wpisami — inaczej nie dałoby się mieć jednego przez agenta i drugiego przez
     * hasło, a to jest zwyczajna sytuacja przy serwerze zapasowym.
     */
    public function equals(self $other): bool
    {
        return $this->name === $other->name;
    }

    public function withAuth(AuthMethod $auth, ?string $keyPath = null): self
    {
        return new self($this->name, $this->host, $this->port, $this->user, $auth, $keyPath, $this->remoteDirectory);
    }

    public function withRemoteDirectory(?string $directory): self
    {
        return new self($this->name, $this->host, $this->port, $this->user, $this->auth, $this->keyPath, $directory);
    }

    private function validate(): void
    {
        if (trim($this->name) === '') {
            throw InvalidHostProfileException::emptyName();
        }

        if (mb_strlen($this->name) > self::MAX_NAME_LENGTH || preg_match(self::NAME_PATTERN, $this->name) !== 1) {
            throw InvalidHostProfileException::invalidName($this->name);
        }

        if (
            strlen($this->host) > self::MAX_HOST_LENGTH
            || preg_match(self::HOST_PATTERN, $this->host) !== 1
        ) {
            throw InvalidHostProfileException::invalidHost($this->host);
        }

        if ($this->port < 1 || $this->port > 65535) {
            throw InvalidHostProfileException::invalidPort($this->port);
        }

        if ($this->user !== '' && preg_match(self::USER_PATTERN, $this->user) !== 1) {
            throw InvalidHostProfileException::invalidUser($this->user);
        }

        if ($this->keyPath !== null && !self::isUsablePath($this->keyPath)) {
            throw InvalidHostProfileException::invalidKeyPath($this->keyPath);
        }
    }

    /**
     * Ścieżka klucza musi być **bezwzględna**: profil czyta się przy starcie
     * łączenia, a katalog roboczy aplikacji nie jest wtedy niczym, na czym można
     * polegać. Znaki sterujące odpadają z tego samego powodu, co w nazwie.
     */
    private static function isUsablePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            && preg_match('/^[^\x00-\x1F\x7F]+$/u', $path) === 1;
    }
}
