<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Domain\ValueObject;

/**
 * Obraz widziany z listy (krok 51).
 *
 * Nazw bywa **kilka na jeden obraz** i to nie jest szczegół: ten sam skrót
 * treści nosi `n8nio/n8n:latest` i `n8nio/n8n:2.33`, bo etykieta jest
 * wskaźnikiem, a nie własnością obrazu. Lista pokazuje przez to pierwszą nazwę,
 * a panel opisu wszystkie — inaczej usunięcie „jednego obrazu” zabierałoby
 * użytkownikowi rzecz, której nazwy nawet nie widział.
 *
 * **Liczby kontenerów korzystających z obrazu tu nie ma i nie będzie**, choć
 * odpowiedź demona niesie pole `Containers`. Powód wyszedł z **obejrzenia
 * klatki**, a nie z projektu: pole ma wartość −1 w każdym wierszu i we wszystkich
 * trzech wariantach zapytania (`bez opcji`, `?shared-size=true`, `?all=true`) —
 * demon liczy je wyłącznie na osobne żądanie, którego ten zasób nie przyjmuje.
 * Kolumna „Użyć" stała przez to pusta w każdym wierszu, zabierając osiem znaków
 * szerokości nazwie obrazu. Kolumna, której nie da się zapełnić, nie jest
 * kolumną.
 *
 * Obraz **bez ani jednej nazwy** jest zwykłym stanem, a nie uszkodzeniem:
 * zostaje po przebudowie, gdy etykieta przeszła na nowszą warstwę. To właśnie
 * takie obrazy zajmują dysk niezauważone, więc lista ma je pokazywać wprost.
 */
final readonly class Image
{
    /**
     * @param list<string> $tags nazwy z etykietami; pusta lista — obraz osierocony
     */
    public function __construct(
        public ImageRef $id,
        public array $tags = [],
        /** Rozmiar w bajtach; `null` — demon go nie podał. */
        public ?int $sizeInBytes = null,
        /** Czas utworzenia w sekundach epoki; `null` — demon go nie podał. */
        public ?int $createdAt = null,
    ) {
    }

    /** Obraz, którego żadna etykieta już nie wskazuje. */
    public function isDangling(): bool
    {
        return $this->tags === [];
    }

    /** Nazwa do pokazania na liście: pierwsza etykieta albo skrót treści. */
    public function label(): string
    {
        return $this->tags[0] ?? $this->id->short();
    }

    /**
     * Czym się go usuwa — nazwą, gdy ją ma, inaczej skrótem treści.
     *
     * Nazwa, a nie identyfikator, i to jest różnica widoczna dla użytkownika:
     * usunięcie po identyfikatorze obrazu o dwóch etykietach demon **odrzuca**,
     * bo nie wie, którą z nich chcemy stracić; usunięcie po nazwie zdejmuje tę
     * jedną i zostawia resztę.
     */
    public function removalRef(): ImageRef
    {
        return $this->tags === [] ? $this->id : ImageRef::of($this->tags[0]);
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }
}
