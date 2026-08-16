<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Domain\ValueObject;

use LightManager\Module\Docker\Domain\Exception\InvalidImageRefException;

/**
 * Wskazanie obrazu: identyfikator `sha256:…` albo nazwa z etykietą (krok 51).
 *
 * Dwie postacie w jednym obiekcie, bo demon przyjmuje obie w tym samym miejscu
 * ścieżki żądania, a użytkownik widzi raz jedną, raz drugą: obraz zbudowany ma
 * nazwę, obraz osierocony przez nowszą budowę ma sam identyfikator. Rozdzielenie
 * ich na dwa typy kazałoby każdemu wołającemu pytać „którą mam”.
 *
 * Granica znaków jest tu **szersza niż przy kontenerze i to jest zamierzone**:
 * nazwa obrazu bywa adresem rejestru z ukośnikami i portem
 * (`localhost:5000/zespol/obraz:1.2`). Zakazane zostają wyłącznie znaki, które
 * zmieniłyby żądanie w co innego, niż napisano — biały znak, `?`, `#` i `..`.
 */
final readonly class ImageRef
{
    /** Tyle znaków skrótu pokazuje `docker images` — bez przedrostka `sha256:`. */
    public const SHORT_LENGTH = 12;

    private const DIGEST_PREFIX = 'sha256:';

    private function __construct(public string $value)
    {
    }

    public static function of(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidImageRefException::forEmptyReference();
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/@-]*$/', $trimmed) !== 1 || str_contains($trimmed, '..')) {
            throw InvalidImageRefException::forMalformedReference($trimmed);
        }

        return new self($trimmed);
    }

    /** Czy wskazanie jest samym skrótem treści, czyli obrazem bez nazwy. */
    public function isDigest(): bool
    {
        return str_starts_with($this->value, self::DIGEST_PREFIX);
    }

    /**
     * Postać widoczna na liście: nazwa w całości albo dwanaście znaków skrótu.
     *
     * Nazwy **nie skracamy nigdy** — ucięta przestaje być tym, co da się wpisać
     * z powrotem, a to jest jedyny powód, dla którego użytkownik ją czyta.
     */
    public function short(): string
    {
        return $this->isDigest()
            ? substr(substr($this->value, strlen(self::DIGEST_PREFIX)), 0, self::SHORT_LENGTH)
            : $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
