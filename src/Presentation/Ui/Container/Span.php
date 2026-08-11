<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Container;

/**
 * Ile miejsca chce zająć jeden uczestnik podziału — trzy liczby i nic więcej.
 *
 * Klasa powstała w kroku 27 i jest **wyprowadzeniem tego, co już istniało**:
 * `Slot` niósł te same trzy liczby od kroku 18, a jego dokumentacja mówiła wprost,
 * że są **bezwymiarowe** — „wiersze w kontenerze pionowym, kolumny w poziomym”.
 * Zapowiedź czekała półtora kroku na drugą oś; kolumny tabeli są nią.
 *
 * Rozdzielenie miary od uczestnika ma jeden konkretny powód: `Slot` trzyma
 * dziecko-komponent, a **kolumna tabeli żadnego dziecka nie ma** — komórka jest
 * napisem, nie komponentem. Bez tej klasy kolumna musiałaby albo udawać
 * szczelinę z pustym dzieckiem, albo dostać własny rachunek podziału. Drugie
 * wyjście oznaczałoby tę samą regułę w dwóch plikach, a projekt odrzucił już raz
 * dokładnie taki układ, gdy `ComponentInterface` nie dostał metody `measure()`.
 *
 * Miara jest bezwymiarowa: nadaje jej znaczenie ten, kto dzieli — `VStack`
 * czyta ją jako wiersze, `Table` jako kolumny.
 */
final readonly class Span
{
    public function __construct(
        /** Poniżej tylu miar uczestnik nie ma sensu; 0 znaczy „może zniknąć”. */
        public int $minimum,
        /** Ile chce dostać, gdy jest z czego dawać; `null` — „resztę”. */
        public ?int $preferred,
        /** Kolejność ustępowania: im mniejsza, tym wcześniej oddaje miejsce. */
        public int $yieldOrder = 0,
    ) {
    }

    /** Bierze wszystko, co zostanie po pozostałych. */
    public static function flexible(int $minimum = 1): self
    {
        return new self($minimum, null, PHP_INT_MAX);
    }

    /**
     * Stała miara, która **kurczy się stopniowo** aż do zera.
     *
     * Znaczenie odziedziczone po `Slot::fixed()` z kroku 18 i takie ma zostać:
     * pas podglądu wysoki na pięć wierszy jest przy czterech nadal pasem
     * podglądu, tylko niższym.
     */
    public static function fixed(int $size, int $yieldOrder = 0): self
    {
        return new self(0, $size, $yieldOrder);
    }

    /**
     * Stała miara **nie do skurczenia: tyle albo nic**.
     *
     * Różnica wobec `fixed()` jest cała w minimum i wyszła na jaw dopiero
     * w kroku 27, przy pierwszym teście rozdziału pisanym wprost. Kolumna z datą
     * zwężona o trzy znaki nie jest „węższą datą” — jest napisem `2026-08-…`,
     * czyli niczym, a przy okazji zabiera te znaki nazwie, która by je
     * wykorzystała. Tam, gdzie treść ma **z góry znaną szerokość**, stopniowe
     * kurczenie jest po prostu błędem.
     */
    public static function rigid(int $size, int $yieldOrder = 0): self
    {
        return new self($size, $size, $yieldOrder);
    }

    public function isFlexible(): bool
    {
        return $this->preferred === null;
    }
}
