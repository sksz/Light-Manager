<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Domain\ValueObject;

use LightManager\Module\Kubernetes\Domain\Exception\InvalidClusterNameException;

/**
 * Wskazanie jednego zasobu: rodzaj, przestrzeń nazw i nazwa (krok 52).
 *
 * Trójka, a nie para, bo **nazwa zasobu jest jednoznaczna dopiero w trójce**:
 * pod `web` istnieje w każdej przestrzeni nazw z osobna, a `pods/web`
 * i `deployments/web` to dwie różne rzeczy. Wszystkie trzy części idą do wiersza
 * polecenia, więc wszystkie trzy odsiewa się tutaj, a nie przy składaniu
 * polecenia — inaczej sprawdzenie trzeba by powtórzyć przy każdym czasowniku.
 *
 * Przestrzeń jest `null` dla zasobów klastrowych (węzły, `PersistentVolume`,
 * `ClusterRole`) i to jest różnica, którą widać w poleceniu: `-n` przy nich nie
 * pada w ogóle, bo `kubectl` odpowiedziałby wtedy o zasobie, którego nie ma.
 */
final readonly class ResourceRef
{
    /** Granica nazwy zasobu w Kubernetesie — poddomena DNS-1123. */
    private const MAXIMUM_LENGTH = 253;

    private const SUBJECT = 'resource';

    private function __construct(
        public ResourceKind $kind,
        public ?NamespaceName $namespace,
        public string $name,
    ) {
    }

    public static function of(ResourceKind $kind, ?NamespaceName $namespace, string $name): self
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw InvalidClusterNameException::forEmptyValue(self::SUBJECT);
        }

        if (str_starts_with($trimmed, '-')) {
            throw InvalidClusterNameException::forOptionLike(self::SUBJECT, $trimmed);
        }

        if (strlen($trimmed) > self::MAXIMUM_LENGTH) {
            throw InvalidClusterNameException::forTooLongValue(self::SUBJECT, $trimmed, self::MAXIMUM_LENGTH);
        }

        // Poddomena DNS-1123: człony alfanumeryczne rozdzielone kropkami, wewnątrz
        // członu wolno myślnik. Nazwy podów bywają właśnie takie
        // (`web-7d9f8b5c4-x2k9p`), a nazwy z kropką mają choćby certyfikaty.
        if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/', $trimmed) !== 1) {
            throw InvalidClusterNameException::forMalformedValue(self::SUBJECT, $trimmed);
        }

        // Zasób klastrowy z przestrzenią nazw i zasób przestrzenny bez niej to
        // dwa różne nieporozumienia, ale skutek mają ten sam: polecenie pyta
        // o coś, czego nie ma. Rozstrzyga rodzaj, bo tylko on wie.
        return new self($kind, $kind->namespaced ? $namespace : null, $trimmed);
    }

    /** Postać `rodzaj/nazwa` — tak wskazuje się zasób `kubectl`owi i tak wygląda w wypisie. */
    public function address(): string
    {
        return $this->kind->address() . '/' . $this->name;
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name
            && $this->kind->equals($other->kind)
            && ($this->namespace?->value) === ($other->namespace?->value);
    }
}
