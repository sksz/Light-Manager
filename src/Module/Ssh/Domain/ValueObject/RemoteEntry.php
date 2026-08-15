<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Domain\ValueObject;

/**
 * Pojedynczy wpis zdalnego katalogu (krok 49).
 *
 * **Powtarza `Entry` przeglądarki świadomie** — z tego samego powodu i w tych
 * samych granicach, co `RemotePath` powtarza `DirectoryPath`.
 *
 * Plan kroku zapowiadał tu jedną rzecz, której **nie ma**, i warto wiedzieć
 * dlaczego: znacznik „atrybutów jeszcze nie znam”. Miał istnieć, bo plan
 * zakładał odczyt dwuetapowy — najpierw nazwy jednym obiegiem, potem atrybuty po
 * jednym obiegu na wpis. Odwrócenie drogi technicznej fazy (D87) i sprawdzenie
 * na żywym serwerze zabrało temu podstawę: `sftp ls -l` oddaje **nazwę razem
 * z atrybutami w jednym obiegu**, więc wpis bez atrybutów nie ma jak powstać.
 * Pole, które zawsze miałoby tę samą wartość, byłoby pytaniem bez treści.
 *
 * Atrybuty są mimo to `null`-owalne i to nie jest sprzeczność: wiersz wypisu,
 * którego nie da się rozczytać w całości, ma prawo oddać nazwę bez rozmiaru —
 * lepiej pokazać wpis z pustą kolumną niż pominąć go w liście.
 */
final readonly class RemoteEntry
{
    public function __construct(
        public string $name,
        public RemoteEntryType $type,
        /** Rozmiar w bajtach; dla katalogu bez znaczenia, dla dowiązania — długość celu. */
        public ?int $sizeInBytes = null,
        /** Czas ostatniej zmiany; `null` — nie dało się go rozczytać z wypisu. */
        public ?int $modifiedAt = null,
        /** Same bity uprawnień, bez rodzaju wpisu; `null` — nie dało się ich rozczytać. */
        public ?int $permissions = null,
    ) {
    }

    public function isDirectory(): bool
    {
        return $this->type->isDirectory();
    }

    public function isHidden(): bool
    {
        return str_starts_with($this->name, '.');
    }

    /**
     * Prawa w postaci `rwxr-xr-x`; pusty napis, gdy ich nie znamy.
     *
     * Rachunek powtarza `Entry::permissionsAsText()` przeglądarki — świadomie
     * i wedle tej samej granicy: zapis praw uniksowych jest własnością tego, kto
     * je pokazuje, a wspólny mógłby stać dopiero w rdzeniu, który o plikach nie
     * wie nic (D42).
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
}
