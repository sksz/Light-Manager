<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\FileInfo\Application\Dto\EntryKind;
use LightManager\Module\FileInfo\Application\Dto\FileStat;
use LightManager\Module\FileInfo\Application\Port\FileStatPort;

/**
 * `lstat` bez dysku: oddaje z góry ustalone dane dla każdej ścieżki.
 *
 * Testy opisu pliku nie mają czego szukać w prawdziwym systemie plików — nazwa
 * właściciela, czasy i i-węzeł zależałyby wtedy od maszyny, na której akurat
 * biegną. Wpisy nieznane oddają `null`, czyli „wpisu nie ma”, i to jest ta sama
 * odpowiedź, którą daje prawdziwa usługa dla ścieżki, która zniknęła.
 */
final class StubFileStat implements FileStatPort
{
    /**
     * Wpisy **jawnie** ustawione: ścieżka → dane albo `null`, czyli „nie ma”.
     *
     * @var array<string, FileStat|null>
     */
    private array $entries = [];

    /** @var list<string> ścieżki, o które pytano — w kolejności pytań */
    public array $requestedPaths = [];

    public function add(string $path, ?FileStat $stat = null): self
    {
        $this->entries[$path] = $stat ?? self::file();

        return $this;
    }

    /** Ścieżka, której nie ma — tak samo, jak wpis skasowany między klatkami. */
    public function deny(string $path): self
    {
        $this->entries[$path] = null;

        return $this;
    }

    /**
     * Ścieżka nieustawiona jawnie oddaje **zwykły plik**, a nie `null`.
     *
     * Domyślna odpowiedź brzmi „wpis istnieje”, bo tak jest w każdym teście poza
     * tym jednym, który sprawdza wpis znikający — a ten mówi o tym wprost przez
     * `deny()`. Odwrotny domyślny wymagałby rejestrowania ścieżek w połowie
     * zestawu testów, w których opis pliku jest tłem, a nie tematem.
     */
    public function stat(string $path): ?FileStat
    {
        $this->requestedPaths[] = $path;

        return array_key_exists($path, $this->entries) ? $this->entries[$path] : self::file();
    }

    /** Zwykły plik o wartościach, które da się wpisać wprost w asercję. */
    public static function file(int $sizeInBytes = 4096, EntryKind $kind = EntryKind::File): FileStat
    {
        return new FileStat(
            $kind,
            $sizeInBytes,
            8,
            0644,
            1000,
            1000,
            'uzytkownik',
            'uzytkownicy',
            1_700_000_000,
            1_700_000_100,
            1_700_000_200,
            1,
            424242,
        );
    }

    public static function directory(int $entryCount = 3): FileStat
    {
        $stat = self::file(4096, EntryKind::Directory);

        return new FileStat(
            $stat->kind,
            $stat->sizeInBytes,
            $stat->blocks,
            0755,
            $stat->ownerId,
            $stat->groupId,
            $stat->ownerName,
            $stat->groupName,
            $stat->modifiedAt,
            $stat->changedAt,
            $stat->accessedAt,
            2,
            $stat->inode,
            null,
            null,
            $entryCount,
        );
    }

    /** Dowiązanie symboliczne wraz z celem i informacją, czy cel istnieje. */
    public static function symlink(string $target, bool $targetExists): FileStat
    {
        $stat = self::file(12, EntryKind::Symlink);

        return new FileStat(
            $stat->kind,
            $stat->sizeInBytes,
            $stat->blocks,
            0777,
            $stat->ownerId,
            $stat->groupId,
            $stat->ownerName,
            $stat->groupName,
            $stat->modifiedAt,
            $stat->changedAt,
            $stat->accessedAt,
            $stat->links,
            $stat->inode,
            $target,
            $targetExists,
        );
    }

    /** Plik bez rozszerzenia `posix` — nazwy nie ma, zostaje sam numer. */
    public static function withoutNames(): FileStat
    {
        $stat = self::file();

        return new FileStat(
            $stat->kind,
            $stat->sizeInBytes,
            $stat->blocks,
            $stat->permissions,
            $stat->ownerId,
            $stat->groupId,
            null,
            null,
            $stat->modifiedAt,
            $stat->changedAt,
            $stat->accessedAt,
            $stat->links,
            $stat->inode,
        );
    }
}
