<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application;

use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntry;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteNameFilter;
use LightManager\Module\Ssh\Domain\ValueObject\RemotePath;

/**
 * To, co widać w panelu zdalnym w tej chwili — **jednym obiektem** (krok 54).
 *
 * Ten sam powód, co przy `HostBookView`, tylko dobitniejszy: ekran zdalny czyta
 * z `RemoteBrowser`a **dziewięć** rzeczy (etap, ścieżkę, wpisy, kursor, wskazany
 * wpis, filtr, ukryte, host i liczbę), a wszystkie opisują jedną chwilę. Dziewięć
 * kwerend znaczyłoby dziewięć wpisów w oknie kwerend na jedno pytanie „co jest
 * w panelu" — i dziewięć okazji, żeby dwie z nich odpowiedziały o różnych
 * klatkach.
 *
 * **Kursor i filtr są tu razem z wpisami, a nie osobno**, i to jest różnica wobec
 * przeglądarki, gdzie `browser.entries` i `browser.panes` stoją obok siebie. Tam
 * panele są dwa, więc układ jest osobnym pytaniem; tutaj panel jest jeden i jego
 * układem jest to, co właśnie widać.
 *
 * Obiekt jest **migawką**: powstaje przy pytaniu i niczego nie posuwa. Wpisy są
 * już po filtrze, bo filtr należy do tego, kto pokazuje (D42 w wersji dla modułu
 * zdalnego, 15e).
 */
final readonly class RemoteView
{
    /** @param list<RemoteEntry> $entries wpisy **po filtrze**, w kolejności pokazywania */
    public function __construct(
        public ListingStage $stage,
        public ?HostProfile $host,
        public ?RemotePath $path,
        public array $entries,
        public int $cursor,
        public RemoteNameFilter $filter,
        public bool $showsHidden,
        /**
         * Czy lista przyszła choć raz i nie została porzucona.
         *
         * Pole, a nie rachunek z pozostałych: pusty katalog **ma** listę, a wpisy
         * odsiane filtrem to nadal lista — więc „`entries` jest puste" znaczy tu
         * co innego niż „nie ma czego pokazać".
         */
        public bool $hasListing,
        public ?string $problemKey = null,
        /** @var array<string, string|int|float> */
        public array $problemParameters = [],
    ) {
    }

    /** Odpowiedź zastępcza fasady, gdy kwerendy nie ma kto wykonać (reguła 8). */
    public static function empty(): self
    {
        return new self(ListingStage::Idle, null, null, [], 0, RemoteNameFilter::none(), false, false);
    }

    public function selected(): ?RemoteEntry
    {
        return $this->entries[$this->cursor] ?? null;
    }

    public function count(): int
    {
        return count($this->entries);
    }
}
