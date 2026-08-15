<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Domain\ValueObject;

/**
 * Rodzaj wpisu w zdalnym katalogu (krok 49).
 *
 * Odpowiada **pierwszej literze wypisu `sftp ls -l`** i to jest cała jego
 * definicja: `d` katalog, `l` dowiązanie, `-` zwykły plik, reszta — coś, czego
 * lista nie ma potrzeby rozróżniać.
 *
 * Dowiązanie jest tu osobnym przypadkiem, a nie ukrytym za rodzajem celu, i to
 * jest rozstrzygnięcie użytkownika ze startu kroku. Powód jest wymierny:
 * `sftp ls -l` widzi wpisy tak, jak `lstat` — dowiązanie do katalogu wygląda
 * w wypisie jak dowiązanie. Rozstrzygnięcie, dokąd prowadzi, kosztowałoby
 * **osobny obieg do serwera na każde dowiązanie**, czyli dokładnie to, czego
 * zastrzeżenie startowe kroku kazało unikać. Lista mówi więc, co widzi, a `Enter`
 * po prostu próbuje wejść.
 */
enum RemoteEntryType
{
    case File;
    case Directory;
    case Symlink;
    case Other;

    /** Rodzaj z pierwszego znaku pola praw w wypisie `ls -l`. */
    public static function fromMode(string $character): self
    {
        return match ($character) {
            'd' => self::Directory,
            'l' => self::Symlink,
            '-' => self::File,
            default => self::Other,
        };
    }

    public function isDirectory(): bool
    {
        return $this === self::Directory;
    }

    /**
     * Czy `Enter` ma prawo spróbować w to wejść.
     *
     * Dowiązanie **wchodzi do tej odpowiedzi**, choć nie wiadomo, dokąd
     * prowadzi: próba kosztuje jeden obieg, którego i tak trzeba by na
     * rozstrzygnięcie, a nieudana kończy się zdaniem w pasku stanu.
     */
    public function mayBeEntered(): bool
    {
        return $this === self::Directory || $this === self::Symlink;
    }

    public function labelKey(): string
    {
        return 'module.ssh.entry.' . match ($this) {
            self::File => 'file',
            self::Directory => 'directory',
            self::Symlink => 'symlink',
            self::Other => 'other',
        };
    }
}
