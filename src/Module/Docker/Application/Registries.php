<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Module\Docker\Domain\ValueObject\ImageRegistry;

/**
 * Koordynator spisu rejestrów — **wpisy dostaje podaną listą raz na takt**
 * (krok 61, etap 1).
 *
 * Rodzeństwo `Environments` i istnieje z tego samego, twardego powodu, a nie
 * dla symetrii: **kwerenda nie woła kwerendy** (11w). Rejestry mieszkają
 * w książce adresowej, czyli u obcego, więc `docker.registries` odpowiadająca
 * pytaniem do `address-book.entries` pytałaby **w trakcie odpowiadania** —
 * a strażnik `QueryRegistry` oddaje wtedy pustkę, i to po cichu. Wpisy podaje
 * więc takt modułu, przez `useEntries()`, a kwerenda czyta już tylko to, co tu
 * leży.
 *
 * Warstwa `Application` **nie widzi rejestru kwerend** i tak ma zostać (15h):
 * koordynator nie wie, skąd wpisy się wzięły — tak samo, jak `Environments` nie
 * wiedział, że kiedyś czytał je z pliku.
 *
 * Pokolenie jest **prawdziwym licznikiem**, a nie `VOLATILE`: rośnie wyłącznie
 * wtedy, gdy spis naprawdę się zmienił, więc odpowiedź da się pamiętać między
 * klatkami (warunek routingu z D93 nr 1).
 */
final class Registries
{
    /** @var list<ImageRegistry> */
    private array $entries = [];

    private int $revision = 0;

    /** @param list<ImageRegistry> $entries */
    public function useEntries(array $entries): void
    {
        if (self::fingerprintOf($entries) === self::fingerprintOf($this->entries)) {
            return;
        }

        $this->entries = $entries;
        ++$this->revision;
    }

    public function revision(): int
    {
        return $this->revision;
    }

    public function view(): RegistryView
    {
        return RegistryView::of($this->entries);
    }

    /**
     * Rejestr, który `docker.push` ma zaproponować: oznaczony jako domyślny,
     * a w jego braku — pierwszy z brzegu.
     *
     * Pierwszy z brzegu, a nie `null`, bo użytkownik z jednym rejestrem nie ma
     * powodu oznaczać go domyślnym, żeby wypchnięcie działało.
     */
    public function preferred(): ?ImageRegistry
    {
        foreach ($this->entries as $entry) {
            if ($entry->isDefault) {
                return $entry;
            }
        }

        return $this->entries[0] ?? null;
    }

    /** @return list<ImageRegistry> */
    public function all(): array
    {
        return $this->entries;
    }

    /** @param list<ImageRegistry> $entries */
    private static function fingerprintOf(array $entries): string
    {
        $parts = [];

        foreach ($entries as $entry) {
            $parts[] = implode('|', [
                $entry->id,
                $entry->name,
                $entry->address,
                $entry->user,
                $entry->isDefault ? '1' : '0',
                $entry->insecure ? '1' : '0',
                $entry->hasToken ? '1' : '0',
            ]);
        }

        return implode("\n", $parts);
    }
}
