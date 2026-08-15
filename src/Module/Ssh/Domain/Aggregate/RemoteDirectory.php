<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Domain\Aggregate;

use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntry;
use LightManager\Module\Ssh\Domain\ValueObject\RemotePath;

/**
 * Zdalny katalog wraz z zawartością (krok 49).
 *
 * **Różni się od `Directory` przeglądarki jedną rzeczą i jest to różnica
 * istotna: nie trzyma zaznaczenia.** Tam zaznaczenie należy do agregatu, bo
 * katalog i kursor zmieniają się razem — wejście w podkatalog jest jedną
 * czynnością. Tutaj katalog **przychodzi z sieci po kilkuset milisekundach**,
 * a kursor musi istnieć wcześniej: lista rysuje się już w chwili, gdy odczyt
 * trwa. Kursor mieszka więc obok, w stanie oglądania (`RemoteBrowseState`) —
 * dokładnie tą samą regułą, którą krok 18 zastosował do `ScrollWindow`: stan
 * przeżywający klatkę leży **obok** tego, co rysuje, a nie w nim.
 *
 * Agregat jest przez to niemutowalny i to jest cała jego treść: jest zdjęciem
 * katalogu z chwili, w której serwer odpowiedział.
 */
final readonly class RemoteDirectory
{
    /** @param list<RemoteEntry> $entries wpisy w kolejności do pokazania */
    public function __construct(
        public RemotePath $path,
        public array $entries,
    ) {
    }

    public static function empty(RemotePath $path): self
    {
        return new self($path, []);
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function at(int $index): ?RemoteEntry
    {
        return $this->entries[$index] ?? null;
    }

    /** Pozycja wpisu o tej nazwie albo `null` — po powrocie z podkatalogu. */
    public function indexOf(string $name): ?int
    {
        foreach ($this->entries as $index => $entry) {
            if ($entry->name === $name) {
                return $index;
            }
        }

        return null;
    }
}
