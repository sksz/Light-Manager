<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Imagick;

use Imagick;

/**
 * Zapamiętane bitmapy wierszy listy.
 *
 * Przy przewijaniu o jeden wiersz prawie cała lista jest znakowo identyczna
 * z poprzednią klatką, a pętla składa klatkę dwadzieścia razy na sekundę i
 * przerysowuje ją także wtedy, gdy nic się nie zmieniło (D19). Rasteryzacja
 * tych samych liter w kółko jest więc regułą, nie wyjątkiem.
 *
 * **Klucz niesie wszystko, co wpływa na piksele** — treść, styl, wymiary,
 * motyw, font i wygładzanie. Dzięki temu nie istnieje ścieżka unieważnienia,
 * o której można zapomnieć: zmiana motywu czy rozmiaru okna daje po prostu
 * inny klucz, a stare wpisy wypada z pamięci limit. Wariant z krótkim kluczem
 * i jawnym czyszczeniem był tańszy w pamięci, ale każde nowe ustawienie
 * wpływające na wygląd trzeba by pamiętać, żeby dopisać je do czyszczenia.
 */
final class RowBitmapCache
{
    /**
     * Powyżej tylu wpisów pamięć jest czyszczona w całości — tak samo jak
     * pamięć szerokości napisów w enkoderze. Wyrzucanie najstarszych po kolei
     * wymagałoby prowadzenia kolejki, a przy tym rozmiarze nie zwróciłoby się:
     * limit jest kilkukrotnie większy niż najdłuższa lista mieszcząca się
     * w oknie, więc do czyszczenia dochodzi przy zmianie katalogu, nie
     * przy przewijaniu.
     */
    private const LIMIT = 512;

    /** @var array<string, Imagick> */
    private array $bitmaps = [];

    public function get(string $key): ?Imagick
    {
        return $this->bitmaps[$key] ?? null;
    }

    public function put(string $key, Imagick $bitmap): void
    {
        if (count($this->bitmaps) >= self::LIMIT) {
            $this->clear();
        }

        $this->bitmaps[$key] = $bitmap;
    }

    public function count(): int
    {
        return count($this->bitmaps);
    }

    /** Zwalnia wszystkie bitmapy — zasoby ImageMagicka nie znikają same. */
    public function clear(): void
    {
        foreach ($this->bitmaps as $bitmap) {
            $bitmap->clear();
        }

        $this->bitmaps = [];
    }
}
