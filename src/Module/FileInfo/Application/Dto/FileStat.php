<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\Dto;

/**
 * To, co system mówi o wpisie **bez uruchamiania czegokolwiek**.
 *
 * Odpowiednik `stat`/`lstat` sprowadzony do danych pierwotnych: liczby, napisy
 * i jeden enum. Warstwa aplikacji nie zna `SplFileInfo` ani tablicy zwracanej
 * przez `stat()` — obie niosłyby ze sobą kształt implementacji, a ta różni się
 * między systemami i między wywołaniami dla dowiązania i dla jego celu.
 *
 * **Nazwa właściciela bywa `null` i to jest informacja, nie brak.** Rozszerzenie
 * `posix` nie zawsze jest dostępne, a wtedy zostaje sam numer — ekran ma
 * powiedzieć dlaczego, zamiast pokazać pustkę.
 */
final class FileStat
{
    public function __construct(
        public readonly EntryKind $kind,
        /** Rozmiar w bajtach — dla katalogu rozmiar samego i-węzła, nie zawartości. */
        public readonly int $sizeInBytes,
        /** Zajęte bloki po 512 bajtów; `null`, gdy system ich nie podaje. */
        public readonly ?int $blocks,
        /** Prawa dostępu — same bity uprawnień, bez rodzaju wpisu. */
        public readonly int $permissions,
        public readonly int $ownerId,
        public readonly int $groupId,
        /** Nazwa właściciela albo `null`, gdy nie da się jej ustalić. */
        public readonly ?string $ownerName,
        public readonly ?string $groupName,
        /** Czas zmiany treści. */
        public readonly int $modifiedAt,
        /** Czas zmiany i-węzła (uprawnienia, nazwa, właściciel). */
        public readonly int $changedAt,
        public readonly int $accessedAt,
        public readonly int $links,
        public readonly int $inode,
        /** Dokąd prowadzi dowiązanie; `null`, gdy wpis dowiązaniem nie jest. */
        public readonly ?string $linkTarget = null,
        /** Czy cel dowiązania istnieje — `null`, gdy nie ma czego sprawdzać. */
        public readonly ?bool $linkTargetExists = null,
        /** Liczba wpisów w katalogu; `null` dla wszystkiego, co katalogiem nie jest. */
        public readonly ?int $entryCount = null,
    ) {
    }

    /** Uprawnienia w postaci `rwxr-xr-x`. */
    public function permissionsAsText(): string
    {
        $text = '';

        foreach ([6, 3, 0] as $shift) {
            $bits = ($this->permissions >> $shift) & 7;
            $text .= ($bits & 4) === 4 ? 'r' : '-';
            $text .= ($bits & 2) === 2 ? 'w' : '-';
            $text .= ($bits & 1) === 1 ? 'x' : '-';
        }

        return $text;
    }

    /** Uprawnienia ósemkowo, zawsze na czterech cyfrach (`0644`). */
    public function permissionsAsOctal(): string
    {
        return sprintf('%04o', $this->permissions);
    }
}
