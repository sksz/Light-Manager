<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Domain\ValueObject;

/**
 * Fragment nazwy, którym użytkownik zawęża listę (krok 30).
 *
 * Reguła dopasowania jest **jedna i leży tutaj**: podciąg bez rozróżniania
 * wielkości liter. Wzorce i wyrażenia regularne są poza zakresem kroku, a nie
 * przeoczeniem — podciąg wystarcza do zawężenia widocznej listy, a reszta to
 * osobna decyzja.
 *
 * Pusty fragment znaczy „bez filtra” i **przepuszcza wszystko**. Wariant, w
 * którym brak filtra jest `null`-em zamiast pustego obiektu, kosztowałby pytanie
 * o `null` w każdym miejscu, które o filtr pyta — a jest ich tyle, ile paneli.
 *
 * Wielkość liter składa `mb_stripos()`, więc obejmuje alfabety spoza ASCII:
 * `Ł` znajduje `łąka`, a nie tylko `L` znajduje `las`.
 */
final readonly class NameFilter
{
    public function __construct(
        public string $value,
    ) {
    }

    public static function none(): self
    {
        return new self('');
    }

    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    public function matches(string $name): bool
    {
        return $this->value === '' || mb_stripos($name, $this->value) !== false;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
