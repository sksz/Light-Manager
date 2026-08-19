<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Domain\ValueObject;

use LightManager\Module\Ssh\Domain\Exception\InvalidHostProfileException;

/**
 * Cel połączenia: z kim, jako kto i czym się przedstawiając (krok 48;
 * **od kroku 60 nie jest już wpisem książki**).
 *
 * Do kroku 60 był wpisem książki hostów tego modułu i jego tożsamością była
 * **nazwa własna**. Książka wyprowadziła się do wspólnego rejestru, a profil
 * został tym, czym naprawdę jest: **odczytem z wiersza kwerendy**
 * `address-book.entries ssh`, złożonym na czas jednego połączenia. Tożsamością
 * jest odtąd **identyfikator wpisu**, bo nazwę wolno zmienić, powtórzyć
 * i zostawić pustą.
 *
 * Czego tu **nie ma i nie będzie**: zapamiętanego katalogu zdalnego. To jest
 * pamięć modułu, a nie adres — mieszka w sekcji `ssh` dokumentu stanu,
 * kluczowana identyfikatorem wpisu.
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
 */
final readonly class HostProfile
{
    public const DEFAULT_PORT = 22;

    private const MAX_NAME_LENGTH = 64;

    private const MAX_HOST_LENGTH = 255;

    /** Nazwa własna: cokolwiek czytelnego, byle bez znaków sterujących. */
    private const NAME_PATTERN = '/^[^\x00-\x1F\x7F]+$/uD';

    /**
     * Host: nazwa domenowa, adres IPv4 albo IPv6 (stąd dwukropki i nawiasy).
     * Pierwszy znak **nie może** być myślnikiem — patrz opis klasy.
     */
    private const HOST_PATTERN = '/^[A-Za-z0-9:\[][A-Za-z0-9._:\]-]*$/D';

    /** Login: to, co dopuszczają `useradd` i `adduser`, bez myślnika na początku. */
    private const USER_PATTERN = '/^[A-Za-z0-9_][A-Za-z0-9._-]*$/D';

    public function __construct(
        /** Identyfikator wpisu książki — tożsamość profilu (krok 60). */
        public string $id,
        public string $name,
        public string $host,
        public int $port = self::DEFAULT_PORT,
        public string $user = '',
        public AuthMethod $auth = AuthMethod::Agent,
        public ?string $keyPath = null,
    ) {
        $this->validate();
    }

    /**
     * Profil z wiersza kwerendy `address-book.entries ssh` albo `null`, gdy
     * wiersz nie opisuje celu, z którym da się połączyć.
     *
     * **To jest jedyna droga, którą profil powstaje z książki**, i idzie
     * wyłącznie przez napisy i liczby (reguła 15g) — ani jeden typ modułu
     * książki nie przechodzi przez tę granicę. Wiersz bez adresu **nie jest
     * błędem**: wpis może istnieć po to, żeby nieść pola zupełnie innego
     * rozdziału, a książka jest wspólna.
     *
     * **Ścieżki klucza tu nie ma i to nie jest przeoczenie.** Pole `keyPath`
     * jest w książce **maskowane**, więc w wierszach spisu stoi w jego miejscu
     * `set`/`unset` — znacznik, a nie ścieżka. Wzięty tu wprost wywracałby
     * samowalidację (`unset` nie jest ścieżką bezwzględną) i **wycinał ze spisu
     * każdy wpis z kluczem**. Wartość dokłada się osobnym pytaniem, w chwili
     * łączenia (`SshQueries::entry()`).
     *
     * @param array<string, string|int|bool> $row
     */
    public static function fromRow(array $row): ?self
    {
        $id = $row['id'] ?? '';
        $host = $row['host'] ?? '';

        if (!is_string($id) || $id === '' || !is_string($host) || $host === '') {
            return null;
        }

        $port = $row['port'] ?? self::DEFAULT_PORT;
        $user = $row['user'] ?? '';
        $auth = $row['auth'] ?? '';

        try {
            return new self(
                $id,
                is_string($row['name'] ?? null) ? $row['name'] : '',
                $host,
                is_int($port) ? $port : (int) (is_string($port) && $port !== '' ? $port : self::DEFAULT_PORT),
                is_string($user) ? $user : '',
                is_string($auth) ? AuthMethod::of($auth) ?? AuthMethod::Agent : AuthMethod::Agent,
            );
        } catch (InvalidHostProfileException) {
            // Wpis nie do przyjęcia wypada, a reszta spisu zostaje — tak samo,
            // jak robił to odczyt książki przed krokiem 60. Powód jest teraz
            // mocniejszy: książka jest wspólna, więc wpis psujący ten spis bywa
            // poprawnym wpisem kogoś innego.
            return null;
        }
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
     * Tożsamością jest **identyfikator wpisu książki** (krok 60).
     *
     * Do tamtego kroku była nią nazwa własna, i było to uczciwe, dopóki książka
     * należała do tego modułu. Odkąd jest wspólna, nazwa jest zwykłym polem:
     * wolno ją zmienić, powtórzyć i zostawić pustą — a sesja ma nadal wiedzieć,
     * z kim stoi.
     */
    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }

    public function withAuth(AuthMethod $auth, ?string $keyPath = null): self
    {
        return new self($this->id, $this->name, $this->host, $this->port, $this->user, $auth, $keyPath);
    }

    private function validate(): void
    {
        // Nazwa **wolno pusta** — tożsamość niesie identyfikator, a książka
        // dopuszcza wpisy bez nazwy (krok 60). Pusty adres nie przechodzi, bo
        // bez niego nie ma z czym się łączyć.
        if ($this->name !== ''
            && (mb_strlen($this->name) > self::MAX_NAME_LENGTH || preg_match(self::NAME_PATTERN, $this->name) !== 1)) {
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
            && preg_match('/^[^\x00-\x1F\x7F]+$/uD', $path) === 1;
    }
}
