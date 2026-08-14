<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Command;

use LightManager\Application\Command\CommandInput;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * Komenda, która **potrzebuje okna**, zanim cokolwiek zrobi (krok 47, D78).
 *
 * Do kroku 41 komenda umiała otworzyć wyłącznie **ekran** i to identyfikatorem,
 * bo `CommandOutcome` leży w `Application`, a `ScreenInterface` w `Presentation`
 * (D39). Wyglądało to na granicę warstw i tak zostało zapisane — **niesłusznie**.
 * Wszystkie komendy w projekcie, rdzenia i modułów, leżą w `Presentation`
 * (`Presentation/Cli/Command`, `Module/<id>/Presentation/Command`);
 * w `Application` leży sam **kontrakt**. Okno widzą więc legalnie, a rozdzielał je
 * `CommandOutcome` stojący pośrodku rozmowy, której obie strony są w tej samej
 * warstwie.
 *
 * Zdolność deklaruje się **osobno**, jak `AppliesToSelection` (menu),
 * `RunsWork` (okno prowadzące pracę) i `NeedsTime` (element zmieniający się sam):
 * komenda, która okna nie potrzebuje, nie ma o czym milczeć ani czego
 * odpowiadać. `CommandInterface` nie rośnie ani o metodę, a `Application` zostaje
 * nietknięty.
 *
 * Miejsce klasy bierze się z precedensu kontraktu modułu (D38, P2): dane
 * i rejestr w `Application`, **zdolności wymieniające typy z `Presentation/Ui`
 * w `Presentation/Ui`** — stąd `Presentation/Ui/Module/ProvidesScreen`
 * i stąd ten katalog.
 */
interface OpensOverlay
{
    /**
     * Okno, którego komenda potrzebuje — albo `null`, gdy nie potrzebuje żadnego
     * i wolno ją wykonać zwyczajnie (`execute()`).
     *
     * Skutek jest `OverlayOutcome`, a nie samym oknem, bo „okno albo zdanie” to
     * jedna odpowiedź, nie dwie: usunięcie z wyłączonym pytaniem kończy się od
     * razu, a usunięcie bez zaznaczenia — zdaniem o tym, że nie ma czego usuwać.
     * Rozdzielenie tego na dwa wywołania znaczyłoby policzenie drzewa dwa razy.
     *
     * Wołający **pyta o zdolność przed `execute()`**; dziś robią to obaj, którzy
     * komendy wykonują — okno komend i menu kontekstowe.
     */
    public function overlayFor(CommandInput $input): ?OverlayOutcome;
}
