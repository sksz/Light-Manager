<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Module\Docker\Domain\ValueObject\Image;

/**
 * To, co widać na liście obrazów w tej chwili — migawka (krok 54).
 *
 * Powstaje przy pytaniu i niczego nie posuwa. Migawka, a nie żywy `ImageList`,
 * i to jest rozstrzygnięcie warte zapisania: fasada oddająca obiekt roboczy
 * musiałaby oddawać go jako `?ImageList`, bo przy module wyłączonym nie ma czego
 * oddać — a wtedy **każde** miejsce odczytu powtarzałoby obsługę `null`a. To jest
 * dokładnie ta cena, przed którą broni reguła 15g („`ask()` oddaje wynik
 * z powodem, nie `null` do obsłużenia w każdym miejscu z osobna”).
 *
 * @see ContainerView bliźniak dla listy kontenerów
 */
final readonly class ImageView
{
    /** @param list<Image> $entries obrazy w kolejności pokazywania */
    public function __construct(
        public array $entries,
        public int $cursor,
        /** Czy odpowiedź demona przyszła choć raz. */
        public bool $loaded,
        public ?string $problemKey = null,
    ) {
    }

    /** Odpowiedź zastępcza fasady, gdy kwerendy nie ma kto wykonać (reguła 8). */
    public static function empty(): self
    {
        return new self([], 0, false);
    }

    public function selected(): ?Image
    {
        return $this->entries[$this->cursor] ?? null;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /** Obraz o tej etykiecie — czym `k8s.deploy-image` odnajduje to, co zbudował. */
    public function withTag(string $tag): ?Image
    {
        foreach ($this->entries as $image) {
            if (in_array($tag, $image->tags, true)) {
                return $image;
            }
        }

        return null;
    }
}
