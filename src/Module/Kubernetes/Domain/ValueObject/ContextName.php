<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Domain\ValueObject;

use LightManager\Module\Kubernetes\Domain\Exception\InvalidClusterNameException;

/**
 * Nazwa kontekstu z `kubeconfig` (krok 52).
 *
 * **Granica znaków jest tu najszersza w module i to jest zamierzone.** Nazwy
 * zasobów dyktuje Kubernetes (DNS-1123), ale nazwę kontekstu wpisuje **człowiek
 * albo narzędzie**, które go zakładało — i wychodzą z tego napisy w rodzaju
 * `gke_projekt_europe-west1_klaster`, `uzytkownik@klaster.region.eksctl.io` czy
 * `kind-lokalny`. Odsiew, który by je odrzucił, odrzuciłby połowę prawdziwych
 * plików konfiguracyjnych, a moduł ma pokazywać klaster, który użytkownik ma,
 * a nie ten, który wyglądałby ładniej.
 *
 * Zakazane zostaje dokładnie to, co zmieniłoby polecenie w co innego, niż
 * napisano: **wiodący `-`** (reguła 11r — opcja, nie argument), biały znak
 * i znaki sterujące. Reszta przechodzi, bo cytowanie `escapeshellarg()` robi z
 * nich argument, a `kubectl` porównuje je z plikiem konfiguracyjnym znak w znak.
 */
final readonly class ContextName
{
    /** Tyle, ile przyjmuje `kubectl config` — spisu nie ma, więc bierzemy granicę zasobu. */
    private const MAXIMUM_LENGTH = 253;

    private const SUBJECT = 'context';

    private function __construct(public string $value)
    {
    }

    public static function of(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw InvalidClusterNameException::forEmptyValue(self::SUBJECT);
        }

        if (str_starts_with($trimmed, '-')) {
            throw InvalidClusterNameException::forOptionLike(self::SUBJECT, $trimmed);
        }

        if (strlen($trimmed) > self::MAXIMUM_LENGTH) {
            throw InvalidClusterNameException::forTooLongValue(self::SUBJECT, $trimmed, self::MAXIMUM_LENGTH);
        }

        // Znak sterujący i biały znak — jedyne, co poza wiodącym myślnikiem
        // odrzucamy. `\p{C}` łapie też znaki niewidoczne, które w nazwie
        // kontekstu nie mają czego szukać, a w wypisie wyglądałyby jak nic.
        if (preg_match('/[\s\p{C}]/u', $trimmed) === 1) {
            throw InvalidClusterNameException::forMalformedValue(self::SUBJECT, $trimmed);
        }

        return new self($trimmed);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
