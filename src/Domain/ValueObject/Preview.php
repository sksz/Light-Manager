<?php

declare(strict_types=1);

namespace LightManager\Domain\ValueObject;

use LightManager\Domain\Exception\InvalidPreviewException;

/**
 * Pas podglądu u dołu klatki: co pokazać i czym to podpisać.
 *
 * Klatka nie zna pikseli, więc podgląd niesie samą ścieżkę pliku — piksele
 * wczytuje dopiero renderer. `null` w miejscu ścieżki znaczy „nie ma czego
 * rysować”: wtedy zostaje sam podpis z powodem (plik uszkodzony, obraz za
 * duży, format nieobsługiwany), a renderer rysuje pustą ramkę.
 *
 * **Wysokości pasa tu nie ma.** Nosiło ją pole `rows` (D24), ale od kroku 13
 * podział okna na strefy żyje w jednym miejscu — `FrameLayout` — i to stamtąd
 * renderer bierze `preview->innerRows`. Pole przestało być czytane wtedy
 * i zostało usunięte w kroku 17; dwa źródła tej samej liczby mogły się tylko
 * rozjechać.
 */
final class Preview
{
    private function __construct(
        public readonly ?string $path,
        public readonly string $caption,
    ) {
        if (trim($caption) === '') {
            throw InvalidPreviewException::forEmptyCaption();
        }

        if ($path !== null && !str_starts_with($path, '/')) {
            throw InvalidPreviewException::forRelativePath($path);
        }
    }

    public static function ofImage(string $path, string $caption): self
    {
        return new self($path, $caption);
    }

    /** Wpis, którego nie da się pokazać — podpis mówi dlaczego. */
    public static function unavailable(string $caption): self
    {
        return new self(null, $caption);
    }

    public function isRenderable(): bool
    {
        return $this->path !== null;
    }

    public function equals(self $other): bool
    {
        return $this->path === $other->path && $this->caption === $other->caption;
    }
}
