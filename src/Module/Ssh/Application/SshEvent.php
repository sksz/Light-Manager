<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application;

use LightManager\Application\Event\EventDeclaration;

/**
 * Momenty, o których moduł sesji zdalnej ogłasza (krok 48, przez mechanizm
 * kroku 46).
 *
 * **To jest pierwszy sprawdzian zdarzeń przez moduł, którego przy ich
 * powstawaniu nie było** — i cała jego treść jest w tym, ile kosztuje: enum,
 * jedna zdolność zadeklarowana przy klasie modułu i ani jednej linii w rdzeniu.
 * Gdyby kosztował więcej, zamknięcie słownika z 11o'' miałoby usterkę, o której
 * dziś nikt nie wie.
 *
 * Nazwy stoją w **przestrzeni publikującego** (`ssh.*`), bo spoza niej odsiewa
 * je `EventRegistry` — tak samo, jak `CommandRegistry` odsiewa komendy.
 * Zamknięcie słownika jest **konstrukcyjne**: deklaracje powstają z `cases()`,
 * więc publikacja i spis u odbiorcy nie mają jak się rozjechać.
 *
 * Trzy zdarzenia, nie sześć: etapy pośrednie (`Probing`, `AwaitingApproval`)
 * zdarzeniem nie są, bo zdarzenie ma opisywać **rzecz, która się stała**, a nie
 * fazę pracy, która trwa. Efekt dźwiękowy przypisany do „właśnie pytam
 * o odcisk" grałby w środku pytania.
 *
 * **Krok 50 dokłada dwa i ani jednego więcej.** Przesył ma prawo do własnych,
 * bo trzy zdarzenia rdzenia odróżniają powodzenie od awarii, ale nie odróżniają
 * **przesyłu** od czegokolwiek innego (D83, rozstrzygnięcie 1) — a przeniesiony
 * plik jest dokładnie tym rodzajem zdarzenia, dla którego efekty w kroku 46
 * powstały. Pobrania od wysłania **nie odróżniają** i to też jest wybór: kierunek
 * jest szczegółem czynności, a nie inną czynnością.
 */
enum SshEvent: string
{
    /** Sesja stanęła — mistrz połączenia żyje. */
    case Connected = 'ssh.connected';

    /** Sesja zamknięta na życzenie użytkownika. */
    case Disconnected = 'ssh.disconnected';

    /** Połączenia nie udało się nawiązać — z dowolnego powodu. */
    case Failed = 'ssh.failed';

    /** Plik doszedł na drugą stronę — pobrany albo wysłany (krok 50). */
    case TransferDone = 'ssh.transfer.done';

    /** Przesył stanął: niepowodzenie albo przerwanie przez użytkownika. */
    case TransferFailed = 'ssh.transfer.failed';

    public function labelKey(): string
    {
        return 'module.' . SshSettings::ID . '.event.'
            . substr($this->value, strlen(SshSettings::ID) + 1);
    }

    /** @return list<EventDeclaration> */
    public static function declarations(): array
    {
        return array_map(
            static fn (self $event): EventDeclaration => new EventDeclaration($event->value, $event->labelKey()),
            self::cases(),
        );
    }
}
