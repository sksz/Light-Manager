<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Application\Undo;

/**
 * Jedna operacja w stosie cofnięć — wraz z drogą powrotną (krok 44).
 *
 * Dana, nie proces: wzorem `TransferState` ma prywatny konstruktor i nazwany
 * konstruktor na każdy rodzaj, a pola pokrywają sumę ich potrzeb — pole obce
 * danemu rodzajowi zostaje puste. Wszystko jest napisem albo listą napisów,
 * bo droga powrotna musi opisać się sama: ścieżki, nazwy i nazwy w koszu.
 *
 * **`reversible()` jest jedynym źródłem prawdy o odwracalności** — widok,
 * klawisz cofania i wykonawca pytają tutaj, a nie każde po swojemu.
 */
final class UndoEntry
{
    /**
     * @param list<string>          $names      nazwy wpisów, których operacja dotyczyła
     * @param ?string               $from       Rename: nazwa sprzed zmiany; Move: katalog źródłowy
     * @param ?string               $trashDirectory Trash: kosz, do którego wpisy pojechały
     * @param array<string, string> $trashNames Trash: nazwa wpisu → nazwa w `files/` kosza
     * @param int                   $total      PermanentDelete: ile wpisów naprawdę zniknęło
     */
    private function __construct(
        public readonly UndoKind $kind,
        public readonly string $directory,
        public readonly array $names,
        public readonly ?string $from = null,
        public readonly ?string $trashDirectory = null,
        public readonly array $trashNames = [],
        public readonly int $total = 0,
    ) {
    }

    public static function renamed(string $directory, string $from, string $to): self
    {
        return new self(UndoKind::Rename, $directory, [$to], $from);
    }

    public static function directoryCreated(string $directory, string $name): self
    {
        return new self(UndoKind::MakeDirectory, $directory, [$name]);
    }

    /** @param array<string, string> $trashNames nazwa wpisu → nazwa w koszu */
    public static function trashed(string $directory, array $trashNames, string $trashDirectory): self
    {
        return new self(
            UndoKind::Trash,
            $directory,
            array_map(strval(...), array_keys($trashNames)),
            null,
            $trashDirectory,
            $trashNames,
        );
    }

    /** @param list<string> $names wpisy stoją teraz w `$directory`; cofnięcie wraca do `$from` */
    public static function moved(string $from, string $directory, array $names): self
    {
        return new self(UndoKind::Move, $directory, $names, $from);
    }

    /** @param list<string> $names */
    public static function copied(string $directory, array $names): self
    {
        return new self(UndoKind::Copy, $directory, $names);
    }

    /** @param list<string> $names */
    public static function deletedPermanently(string $directory, array $names, int $total): self
    {
        return new self(UndoKind::PermanentDelete, $directory, $names, null, null, [], $total);
    }

    public function reversible(): bool
    {
        return match ($this->kind) {
            UndoKind::Rename, UndoKind::MakeDirectory, UndoKind::Trash, UndoKind::Move => true,
            UndoKind::Copy, UndoKind::PermanentDelete => false,
        };
    }

    /**
     * Ten sam wpis pomniejszony o wpisy już przywrócone — na wypadek cofnięcia,
     * które stanęło w połowie: zapis nie znika (użytkownik nie ma prawa stracić
     * informacji o tym, co zostało w koszu), ale nie ma też obiecywać ponownego
     * przywrócenia tego, co już wróciło.
     *
     * @param array<string, string> $remaining
     */
    public function withTrashNames(array $remaining): self
    {
        return new self(
            $this->kind,
            $this->directory,
            array_map(strval(...), array_keys($remaining)),
            $this->from,
            $this->trashDirectory,
            $remaining,
            $this->total,
        );
    }
}
