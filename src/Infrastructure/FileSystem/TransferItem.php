<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\FileSystem;

/**
 * Jedna pozycja kolejki kopiowania: co, skąd i dokąd (krok 42).
 *
 * Powstaje przy liczeniu i od tej chwili jest niezmienna — z jednym wyjątkiem,
 * który ma własną metodę: odpowiedź „zmień nazwę” na kolizję przepisuje **cel**,
 * a wraz z nim cele wszystkich pozycji leżących w środku zmienianego katalogu.
 *
 * Prawa i czas zmiany **nie są tu zapamiętane** z rozmysłu: czyta się je tuż
 * przed skopiowaniem, wprost ze źródła, które w tym momencie na pewno istnieje.
 * Trzy dodatkowe liczby na pozycję znaczyłyby przy drzewie o stu tysiącach wpisów
 * megabajty pamięci trzymane przez całą pracę po to, żeby zaoszczędzić jedno
 * `stat()` na plik.
 */
final class TransferItem
{
    public function __construct(
        public readonly TransferItemKind $kind,
        /** Ścieżka bezwzględna źródła; przy `Stamp` — katalog, z którego bierzemy prawa. */
        public readonly string $source,
        /** Ścieżka bezwzględna celu; przy `Drop` bez znaczenia. */
        public readonly string $target,
        /** Rozmiar w bajtach — niezerowy wyłącznie dla plików. */
        public readonly int $size = 0,
    ) {
    }

    /** Ta sama pozycja z innym celem — dla odpowiedzi „zmień nazwę”. */
    public function toTarget(string $target): self
    {
        return new self($this->kind, $this->source, $target, $this->size);
    }

    /** Czy pozycja jest wpisem, który użytkownik liczy — pieczątki i sprzątanie nie są. */
    public function isEntry(): bool
    {
        return $this->kind !== TransferItemKind::Stamp && $this->kind !== TransferItemKind::Drop;
    }
}
