<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Domain\ValueObject;

/**
 * Fragment nazwy, którym użytkownik zawęża zdalną listę (krok 49).
 *
 * **Powtarza `NameFilter` przeglądarki świadomie** — wedle granicy zapisanej
 * w `SKILL.md`: wolno powtórzyć pojęcia domeny, nie wolno powtórzyć mechanizmu
 * rdzenia. Podświetlenie dopasowania **nie jest** tu powtórzone: zakresy liczy
 * `TextSpan::occurrencesOf()` z rdzenia i rysuje `TableRow` z kroku 30, czyli
 * dokładnie ten sam mechanizm, którego używa lista lokalna.
 *
 * Filtr działa **na tym, co już przyszło**, i to jest cała różnica wobec wpisów
 * ukrytych: te trzeba zamówić u serwera osobnym obiegiem (`ls -a`), a zawężenie
 * nazwą nie kosztuje ani jednego bajtu w sieci.
 *
 * Reguła dopasowania jest ta sama, co w przeglądarce — podciąg bez rozróżniania
 * wielkości liter, `mb_stripos()`, więc `Ł` znajduje `łąka`. Inna byłaby
 * niespodzianką: użytkownik nie ma powodu podejrzewać, że to samo pole
 * w dwóch listach działa inaczej.
 */
final readonly class RemoteNameFilter
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
