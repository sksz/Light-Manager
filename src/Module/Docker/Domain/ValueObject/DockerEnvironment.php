<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Domain\ValueObject;

use LightManager\Module\Docker\Domain\Exception\InvalidDockerEnvironmentException;

/**
 * Wpis książki środowisk: z którym demonem i którędy (krok 58).
 *
 * **Obiekt wartości, więc pilnuje się sam** (reguła 6), a wzorce są **wąskie
 * z założenia** — jak w `HostProfile` z kroku 48 i z tego samego powodu: nazwa
 * wchodzi do nazwy pliku gniazda tunelu i do wiersza polecenia, cel tunelu do
 * wiersza polecenia `ssh`, a ścieżki do opcji. Cytowanie zostaje obowiązkiem
 * usługi; te wzorce pilnują tego, czego cytowanie upilnować nie może — **żadna
 * wartość nie zaczyna się od `-`**.
 *
 * Pola są wspólną sumą trzech rodzajów, a nie trzema klasami, bo wpis żyje
 * w jednym pliku i w jednym spisie; o tym, które pole ma znaczenie, mówi
 * `EnvironmentKind`. Konstruktory nazwane trzymają tę granicę: nie da się
 * zbudować wpisu tunelu ze ścieżkami TLS.
 *
 * **Cel tunelu bywa dwiema rzeczami naraz** i rozstrzyga się to dopiero przy
 * podnoszeniu tunelu: napis równy nazwie wpisu książki hostów znaczy „weź dane
 * kwerendą `address-book.entry`" (reguła 15g — same napisy, ani jednego typu), każdy inny
 * czyta się jako `[user@]host`. Dokładne dopasowanie do książki wygrywa, więc
 * wpis książki o nazwie z dwukropkiem przegrywa z rozbiorem `host:port` —
 * znana, rzadka cena jednego pola zamiast dwóch.
 */
final readonly class DockerEnvironment
{
    public const DEFAULT_SOCKET = '/var/run/docker.sock';

    public const DEFAULT_TUNNEL_PORT = 22;

    public const DEFAULT_TLS_PORT = 2376;

    private const MAX_NAME_LENGTH = 64;

    private const MAX_PATH_LENGTH = 255;

    private const MAX_TARGET_LENGTH = 320;

    /**
     * Nazwa własna: wąsko, bo wchodzi do nazwy pliku gniazda
     * (`lm-docker-<nazwa>.sock`) i do wiersza polecenia.
     */
    private const NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]*$/';

    /**
     * Cel tunelu: nazwa wpisu książki hostów albo `[user@]host` (z IPv6
     * w nawiasach włącznie). Pierwszy znak **nie może** być myślnikiem.
     */
    private const TARGET_PATTERN = '/^[A-Za-z0-9_\[][A-Za-z0-9._@:\]\[-]*$/';

    /** Host demona TCP — jak host w `HostProfile` z kroku 48. */
    private const HOST_PATTERN = '/^[A-Za-z0-9:\[][A-Za-z0-9._:\]-]*$/';

    private function __construct(
        public string $name,
        public EnvironmentKind $kind,
        /** `LocalSocket`: gniazdo lokalne; `SshTunnel`: gniazdo po stronie zdalnej. */
        public string $socketPath,
        /** `SshTunnel`: wpis książki hostów albo `[user@]host`; `Tcp`: host demona. */
        public string $target,
        /** `SshTunnel`: port SSH; `Tcp`: port demona. */
        public int $port,
        public ?string $certPath,
        public ?string $keyPath,
        public ?string $caPath,
    ) {
        $this->validate();
    }

    public static function localSocket(string $name, string $socketPath = self::DEFAULT_SOCKET): self
    {
        return new self($name, EnvironmentKind::LocalSocket, $socketPath, '', 0, null, null, null);
    }

    public static function sshTunnel(
        string $name,
        string $target,
        int $port = self::DEFAULT_TUNNEL_PORT,
        string $remoteSocket = self::DEFAULT_SOCKET,
    ): self {
        return new self($name, EnvironmentKind::SshTunnel, $remoteSocket, $target, $port, null, null, null);
    }

    public static function tcp(
        string $name,
        string $host,
        int $port,
        string $certPath,
        string $keyPath,
        string $caPath,
    ): self {
        return new self($name, EnvironmentKind::Tcp, '', $host, $port, $certPath, $keyPath, $caPath);
    }

    /**
     * Adres do pokazania w spisie — **bez poświadczeń**, ale z celem: spis jest
     * ekranem właściciela, więc cel tunelu wolno tu pokazać (granica z reguły
     * 11w dotyczy wierszy kwerendy, nie własnego ekranu).
     */
    public function label(): string
    {
        return match ($this->kind) {
            EnvironmentKind::LocalSocket => $this->socketPath,
            EnvironmentKind::SshTunnel => $this->port === self::DEFAULT_TUNNEL_PORT
                ? $this->target
                : $this->target . ':' . $this->port,
            EnvironmentKind::Tcp => 'https://' . $this->target . ':' . $this->port,
        };
    }

    /** Tożsamość wpisu to nazwa własna — jak w książce hostów (krok 48). */
    public function equals(self $other): bool
    {
        return $this->name === $other->name;
    }

    private function validate(): void
    {
        if (trim($this->name) === '') {
            throw InvalidDockerEnvironmentException::forEmptyName();
        }

        if (
            strlen($this->name) > self::MAX_NAME_LENGTH
            || preg_match(self::NAME_PATTERN, $this->name) !== 1
        ) {
            throw InvalidDockerEnvironmentException::forInvalidName($this->name);
        }

        match ($this->kind) {
            EnvironmentKind::LocalSocket => $this->validateLocal(),
            EnvironmentKind::SshTunnel => $this->validateTunnel(),
            EnvironmentKind::Tcp => $this->validateTcp(),
        };
    }

    private function validateLocal(): void
    {
        if (!self::isUsablePath($this->socketPath)) {
            throw InvalidDockerEnvironmentException::forInvalidSocketPath($this->socketPath);
        }
    }

    private function validateTunnel(): void
    {
        if (
            $this->target === ''
            || strlen($this->target) > self::MAX_TARGET_LENGTH
            || preg_match(self::TARGET_PATTERN, $this->target) !== 1
        ) {
            throw InvalidDockerEnvironmentException::forInvalidTarget($this->target);
        }

        if ($this->port < 1 || $this->port > 65535) {
            throw InvalidDockerEnvironmentException::forInvalidPort($this->port);
        }

        if (!self::isUsablePath($this->socketPath)) {
            throw InvalidDockerEnvironmentException::forInvalidSocketPath($this->socketPath);
        }
    }

    private function validateTcp(): void
    {
        if (
            $this->target === ''
            || strlen($this->target) > self::MAX_PATH_LENGTH
            || preg_match(self::HOST_PATTERN, $this->target) !== 1
        ) {
            throw InvalidDockerEnvironmentException::forInvalidHost($this->target);
        }

        if ($this->port < 1 || $this->port > 65535) {
            throw InvalidDockerEnvironmentException::forInvalidPort($this->port);
        }

        foreach ([$this->certPath, $this->keyPath, $this->caPath] as $path) {
            if ($path === null || !self::isUsablePath($path)) {
                throw InvalidDockerEnvironmentException::forInvalidCertificatePath($path ?? '');
            }
        }
    }

    /**
     * Ścieżka musi być **bezwzględna**: wpis czyta się długo po tym, jak
     * zmienił się katalog roboczy, a względna ścieżka klucza TLS wskazywałaby
     * wtedy inny plik, niż użytkownik wpisał.
     */
    private static function isUsablePath(string $path): bool
    {
        return $path !== ''
            && strlen($path) <= self::MAX_PATH_LENGTH
            && str_starts_with($path, DIRECTORY_SEPARATOR)
            && preg_match('/^[^\x00-\x1F\x7F]+$/u', $path) === 1;
    }
}
