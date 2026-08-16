<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Domain\ValueObject;

use LightManager\Module\Kubernetes\Domain\Exception\InvalidClusterNameException;

/**
 * Nazwa przestrzeni nazw (krok 52).
 *
 * **Klasa nazywa się `NamespaceName`, a nie `Namespace`, i nie jest to kwestia
 * gustu**: `namespace` to w PHP słowo zastrzeżone, więc klasa o tej nazwie nie
 * skompilowałaby się w ogóle. Plan kroku wymieniał `Namespace.php` — to jedyna
 * pozycja z jego spisu plików, której nie dało się wykonać dosłownie
 * ([00-decyzje.md](../../../../../docs/plans/00-decyzje.md), D91).
 *
 * Odsiew jest **węższy niż przy kontekście**, bo tu regułę dyktuje Kubernetes,
 * a nie człowiek: przestrzeń nazw jest etykietą DNS-1123 — małe litery, cyfry
 * i myślnik, najwyżej 63 znaki, brzegi wyłącznie alfanumeryczne. Serwer odrzuci
 * wszystko inne, więc odrzucenie tego u siebie zamienia okrężną drogę przez
 * proces potomny i błąd z API na jedno zdanie od razu.
 */
final readonly class NamespaceName
{
    /** Granica etykiety DNS-1123 — tyle, ile przyjmuje serwer. */
    public const MAXIMUM_LENGTH = 63;

    /** Przestrzeń, w której zaczyna każdy `kubeconfig` bez wskazania własnej. */
    public const DEFAULT = 'default';

    private const SUBJECT = 'namespace';

    private function __construct(public string $value)
    {
    }

    public static function of(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidClusterNameException::forEmptyValue(self::SUBJECT);
        }

        // Wiodący myślnik sprawdzamy **przed** wzorcem, choć wzorzec też by go
        // odrzucił: powód „to wygląda na opcję” jest dla użytkownika inną
        // wiadomością niż „to nie jest nazwa”, a różnica bywa całą podpowiedzią.
        if (str_starts_with($trimmed, '-')) {
            throw InvalidClusterNameException::forOptionLike(self::SUBJECT, $trimmed);
        }

        if (strlen($trimmed) > self::MAXIMUM_LENGTH) {
            throw InvalidClusterNameException::forTooLongValue(self::SUBJECT, $trimmed, self::MAXIMUM_LENGTH);
        }

        if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $trimmed) !== 1) {
            throw InvalidClusterNameException::forMalformedValue(self::SUBJECT, $trimmed);
        }

        return new self($trimmed);
    }

    public static function fallback(): self
    {
        return new self(self::DEFAULT);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
