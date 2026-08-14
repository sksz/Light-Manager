<?php

declare(strict_types=1);

namespace LightManager\Domain\Exception;

/**
 * Niepowodzenie czynności zmieniającej zawartość dysku (krok 41).
 *
 * Wyjątek jest **domenowy**, choć rzuca go usługa infrastruktury, i to nie jest
 * niekonsekwencja: dokładnie tak samo działa od kroku 21 repozytorium katalogów
 * (`FilesystemDirectoryRepository` rzuca `DirectoryNotReadableException`).
 * Reguła 8 zabrania przekraczania granicy portu wyjątkom hierarchii
 * `InfrastructureException` — bo tamte mówią o nośniku, nie o sytuacji
 * użytkownika — a „nazwa jest już zajęta” jest sytuacją użytkownika.
 *
 * Zdanie dla użytkownika wyjątek podaje **sam** (`DescribesProblem`), więc
 * `ProblemPresenter` nie uczy się przy okazji, czym jest plik. Przekazywana
 * dana jest przy tym zawsze jedna i ta sama: **napis ze ścieżką albo nazwą** —
 * granica wiedzy rdzenia z D66 przebiega dokładnie tutaj.
 *
 * `{detail}` w wariancie ogólnym jest **techniczny i angielski**, bo pochodzi
 * wprost od systemu (`No space left on device`, `Text file busy`). Precedens:
 * `problem.terminal.stty` pokazuje szczegół `stty` tą samą drogą — zdanie
 * ogólne bez niego nie odróżniłoby pełnego dysku od zajętego pliku.
 */
final class FileOperationException extends DomainException implements DescribesProblem
{
    /** @param array<string, string> $problemParameters */
    private function __construct(
        string $message,
        private readonly string $problemKey,
        private readonly array $problemParameters,
    ) {
        parent::__construct($message);
    }

    /** Wpis zniknął między narysowaniem listy a naciśnięciem klawisza. */
    public static function missing(string $path): self
    {
        return new self(
            sprintf('Entry "%s" does not exist.', $path),
            'problem.fileops.missing',
            ['name' => basename($path)],
        );
    }

    public static function nameTaken(string $path): self
    {
        return new self(
            sprintf('Path "%s" already exists.', $path),
            'problem.fileops.taken',
            ['name' => basename($path)],
        );
    }

    /** Brak prawa zapisu w katalogu, w którym wpis mieszka — nie w samym wpisie. */
    public static function denied(string $path): self
    {
        return new self(
            sprintf('No permission to modify "%s".', $path),
            'problem.fileops.denied',
            ['name' => basename($path)],
        );
    }

    /**
     * Katalog niepusty przy usunięciu **pojedynczego wpisu**.
     *
     * Zwykłą drogą do niepustego katalogu jest praca kawałkowa, więc ten wariant
     * zdarza się wtedy, gdy coś powstało w katalogu między liczeniem a usuwaniem
     * — i wtedy odmowa jest właściwą odpowiedzią, bo pytanie mówiło o innej
     * liczbie wpisów, niż zniknęłaby naprawdę.
     */
    public static function notEmpty(string $path): self
    {
        return new self(
            sprintf('Directory "%s" is not empty.', $path),
            'problem.fileops.notEmpty',
            ['name' => basename($path)],
        );
    }

    /** Wszystko, czego nie da się rozpoznać przed czynnością: pełny dysk, plik zajęty, awaria nośnika. */
    public static function failed(string $path, string $detail): self
    {
        return new self(
            sprintf('Operation on "%s" failed: %s', $path, $detail),
            'problem.fileops.failed',
            ['name' => basename($path), 'detail' => $detail],
        );
    }

    public function problemKey(): string
    {
        return $this->problemKey;
    }

    public function problemParameters(): array
    {
        return $this->problemParameters;
    }
}
