<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\Dto;

/**
 * Rodzaj wpisu widziany przez `lstat` — czyli **przed** pójściem za dowiązaniem.
 *
 * Enum jest własnością modułu opisującego pliki, a nie rdzenia, i nie pokrywa się
 * z `ContextEntryKind` z `Application/Module`: tamten ma trzy przypadki, bo tyle
 * wystarcza, żeby jeden moduł powiedział drugiemu „stoję na katalogu”. Tutaj
 * potrzeba pełnej listy, bo gniazdo i kolejka nazwana są tym, co opis pliku ma
 * pokazać zamiast udawać, że wpis jest zwykłym plikiem.
 */
enum EntryKind
{
    case File;
    case Directory;
    case Symlink;
    case BlockDevice;
    case CharacterDevice;
    case Fifo;
    case Socket;
    case Unknown;

    /** Klucz katalogu napisów z nazwą rodzaju. */
    public function labelKey(): string
    {
        return 'module.file-info.kind.' . match ($this) {
            self::File => 'file',
            self::Directory => 'directory',
            self::Symlink => 'symlink',
            self::BlockDevice => 'block',
            self::CharacterDevice => 'character',
            self::Fifo => 'fifo',
            self::Socket => 'socket',
            self::Unknown => 'unknown',
        };
    }
}
