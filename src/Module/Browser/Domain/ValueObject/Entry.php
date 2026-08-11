<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Domain\ValueObject;

use LightManager\Module\Browser\Domain\Exception\InvalidEntryException;

/**
 * Pojedynczy element katalogu. Nie ma własnej tożsamości — przy każdym odczycie
 * katalogu powstaje od nowa.
 *
 * **Czas zmiany i prawa dostępu doszły w kroku 27** i są `null`-owalne, bo to
 * uczciwa odpowiedź: wpis, którego nie dało się odczytać (zerwane dowiązanie,
 * plik zniknięty między `scandir()` a `stat()`), naprawdę ich nie ma. Kolumna
 * pokazuje wtedy pustkę zamiast zmyślonej daty.
 *
 * Że doszły dopiero teraz, ma prosty powód: do kroku 27 lista miała dwie kolumny
 * i nie było ich gdzie pokazać. Kosztują natomiast **mniej niż nic** — repozytorium
 * wołało dotąd `is_dir()` i `filesize()` osobno, a od tego kroku pyta system raz.
 */
final class Entry
{
    public function __construct(
        public readonly string $name,
        public readonly EntryType $type,
        public readonly int $sizeInBytes,
        /** Znacznik czasu ostatniej zmiany treści; `null` — nie dało się odczytać. */
        public readonly ?int $modifiedAt = null,
        /** Same bity uprawnień, bez rodzaju wpisu; `null` — nie dało się odczytać. */
        public readonly ?int $permissions = null,
    ) {
        if ($name === '' || $name === '.' || $name === '..' || str_contains($name, '/')) {
            throw InvalidEntryException::forName($name);
        }

        if ($sizeInBytes < 0) {
            throw InvalidEntryException::forNegativeSize($name, $sizeInBytes);
        }
    }

    public static function directory(string $name, ?int $modifiedAt = null, ?int $permissions = null): self
    {
        return new self($name, EntryType::Directory, 0, $modifiedAt, $permissions);
    }

    public static function file(
        string $name,
        int $sizeInBytes,
        ?int $modifiedAt = null,
        ?int $permissions = null,
    ): self {
        return new self($name, EntryType::File, $sizeInBytes, $modifiedAt, $permissions);
    }

    /**
     * Prawa w postaci `rwxr-xr-x`; pusty napis, gdy ich nie znamy.
     *
     * Rachunek jest ten sam, co w `FileStat` modułu opisu pliku, i **nie jest to
     * przeoczenie**: moduł nigdy nie sięga do innego modułu (reguła 15), a zapis
     * praw uniksowych jest własnością tego, kto je pokazuje. Wspólny mógłby stać
     * się dopiero w rdzeniu, a rdzeń nie wie, czym jest plik (D42).
     */
    public function permissionsAsText(): string
    {
        $permissions = $this->permissions;

        if ($permissions === null) {
            return '';
        }

        $text = '';

        foreach ([6, 3, 0] as $shift) {
            $bits = ($permissions >> $shift) & 7;
            $text .= ($bits & 4) === 4 ? 'r' : '-';
            $text .= ($bits & 2) === 2 ? 'w' : '-';
            $text .= ($bits & 1) === 1 ? 'x' : '-';
        }

        return $text;
    }

    public function isDirectory(): bool
    {
        return $this->type === EntryType::Directory;
    }

    /** Uniksowa konwencja: nazwa zaczynająca się od kropki jest ukryta. */
    public function isHidden(): bool
    {
        return str_starts_with($this->name, '.');
    }

    /**
     * Porównuje **wszystkie** wartości, także czas i prawa dodane w kroku 27 —
     * obiekt wartości jest tym, co niesie, a nie tylko tym, po czym się go
     * rozpoznaje. Plik o zmienionej dacie jest innym wpisem niż przed zmianą i to
     * jest właściwa odpowiedź, bo lista pokazuje odtąd tę datę.
     */
    public function equals(self $other): bool
    {
        return $this->name === $other->name
            && $this->type === $other->type
            && $this->sizeInBytes === $other->sizeInBytes
            && $this->modifiedAt === $other->modifiedAt
            && $this->permissions === $other->permissions;
    }
}
