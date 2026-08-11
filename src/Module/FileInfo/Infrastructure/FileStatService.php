<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Infrastructure;

use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\FileInfo\Application\Dto\EntryKind;
use LightManager\Module\FileInfo\Application\Dto\FileStat;
use LightManager\Module\FileInfo\Application\Port\FileStatPort;

/**
 * `lstat` i to, co da się z niego wyprowadzić, bez uruchamiania czegokolwiek.
 *
 * **`lstat`, nie `stat`**, i to jest cała różnica między opisem dowiązania
 * a opisem pliku, na który ono wskazuje: użytkownik stojący na dowiązaniu chce
 * wiedzieć, że stoi na dowiązaniu, oraz dokąd ono prowadzi. Cel odwiedzamy
 * osobno i tylko po to, żeby powiedzieć, czy w ogóle istnieje.
 *
 * Nazwy właściciela i grupy zależą od rozszerzenia `posix`, którego bywa brak.
 * Usługa oddaje wtedy `null` zamiast zgadywać — zdanie „bez rozszerzenia posix”
 * należy do warstwy, która składa napisy, a nie tutaj.
 */
final class FileStatService extends AbstractSingleton implements FileStatPort
{
    /** Bity rodzaju wpisu w polu `mode`. */
    private const TYPE_MASK = 0170000;

    private const PERMISSION_MASK = 0007777;

    public function stat(string $path): ?FileStat
    {
        $stat = @lstat($path);

        if ($stat === false) {
            return null;
        }

        $kind = self::kindOf((int) $stat['mode']);
        [$target, $targetExists] = $this->link($path, $kind);

        return new FileStat(
            $kind,
            (int) $stat['size'],
            (int) $stat['blocks'] >= 0 ? (int) $stat['blocks'] : null,
            (int) $stat['mode'] & self::PERMISSION_MASK,
            (int) $stat['uid'],
            (int) $stat['gid'],
            self::ownerName((int) $stat['uid']),
            self::groupName((int) $stat['gid']),
            (int) $stat['mtime'],
            (int) $stat['ctime'],
            (int) $stat['atime'],
            (int) $stat['nlink'],
            (int) $stat['ino'],
            $target,
            $targetExists,
            $kind === EntryKind::Directory ? self::entryCount($path) : null,
        );
    }

    /** @return array{?string, ?bool} dokąd prowadzi dowiązanie i czy cel istnieje */
    private function link(string $path, EntryKind $kind): array
    {
        if ($kind !== EntryKind::Symlink) {
            return [null, null];
        }

        $target = @readlink($path);

        if ($target === false) {
            return [null, null];
        }

        // Cel podany względnie liczy się **wobec katalogu dowiązania**, a nie
        // wobec katalogu roboczego procesu — inaczej każde dowiązanie względne
        // wyglądałoby na zepsute.
        $resolved = str_starts_with($target, '/') ? $target : dirname($path) . '/' . $target;

        return [$target, @file_exists($resolved)];
    }

    /**
     * Liczba wpisów katalogu, bez `.` i `..`.
     *
     * Idziemy `readdir`iem, a nie `scandir`em: ten drugi buduje po drodze
     * tablicę wszystkich nazw, a nam wystarczy licznik. Przy katalogu z setką
     * tysięcy wpisów jest to różnica między kilkoma megabajtami a zerem.
     */
    private static function entryCount(string $path): ?int
    {
        $handle = @opendir($path);

        if ($handle === false) {
            return null;
        }

        $count = 0;

        while (($entry = readdir($handle)) !== false) {
            if ($entry !== '.' && $entry !== '..') {
                ++$count;
            }
        }

        closedir($handle);

        return $count;
    }

    private static function kindOf(int $mode): EntryKind
    {
        return match ($mode & self::TYPE_MASK) {
            0100000 => EntryKind::File,
            0040000 => EntryKind::Directory,
            0120000 => EntryKind::Symlink,
            0060000 => EntryKind::BlockDevice,
            0020000 => EntryKind::CharacterDevice,
            0010000 => EntryKind::Fifo,
            0140000 => EntryKind::Socket,
            default => EntryKind::Unknown,
        };
    }

    private static function ownerName(int $uid): ?string
    {
        if (!function_exists('posix_getpwuid')) {
            return null;
        }

        $entry = @posix_getpwuid($uid);

        return $entry === false ? null : $entry['name'];
    }

    private static function groupName(int $gid): ?string
    {
        if (!function_exists('posix_getgrgid')) {
            return null;
        }

        $entry = @posix_getgrgid($gid);

        return $entry === false ? null : $entry['name'];
    }
}
