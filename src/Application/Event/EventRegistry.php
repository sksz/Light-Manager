<?php

declare(strict_types=1);

namespace LightManager\Application\Event;

use LightManager\Application\Module\DeclaresEvents;
use LightManager\Application\Module\ListensToEvents;
use LightManager\Application\Module\ModuleInterface;
use Throwable;

/**
 * Zamknięty słownik zdarzeń wraz z odbiorcami — jedyne miejsce, przez które
 * przechodzi publikacja (krok 46, D83).
 *
 * Nazwa „rejestr", a nie „szyna", jest tu dosłowna i wyznacza granicę zakresu:
 * kolejek nie ma, priorytetów nie ma, zdarzeń odłożonych w czasie nie ma.
 * Publikacja jest **synchroniczna, w tym samym takcie, w którym padła**, i kończy
 * się, zanim wróci wołający. Rzecz jest bliższa `CommandRegistry` niż czemukolwiek
 * z podręcznika o zdarzeniach — łącznie z regułą przestrzeni nazw, którą stamtąd
 * powtarza co do joty.
 *
 * Trzy reguły publikacji, wszystkie wykonane w `publish()` i żadna nie zostawiona
 * dobrej woli wołającego:
 *
 * - **publikacja jest tania i nie rzuca** — wyjątek odbiorcy ginie tutaj, bo
 *   publikacja stoi w środku `LoopState::report()` i w środku czynności na
 *   plikach, a te nie mają dokąd zgłosić cudzego kłopotu; zgłoszenie przez
 *   `report()` byłoby zresztą publikacją kolejnego zdarzenia;
 * - **rdzeń nie wie, kto słucha** — przy zerze odbiorców `publish()` kończy się na
 *   jednym sprawdzeniu w tablicy, a wołający nie ma jak się dowiedzieć, czy ktoś
 *   był;
 * - **zdarzenie nie rodzi zdarzenia** — odbiorca, który spróbuje opublikować
 *   cokolwiek w trakcie odbioru, zostaje zignorowany. Bez tego zamknięty słownik
 *   przestałby być zamknięty w tej samej chwili, w której zaczęłyby się łańcuchy,
 *   a pojedynczy błąd zapętliłby pętlę główną.
 *
 * Rejestr rodzi się ze słownikiem rdzenia (`AppEvent`), bo ten istnieje zawsze —
 * moduły dochodzą jedną linią przy składaniu aplikacji.
 */
final class EventRegistry
{
    /** Przestrzeń nazw zdarzeń rdzenia — ta sama, co w rejestrze komend. */
    public const CORE = 'core';

    /** @var array<string, EventDeclaration> */
    private array $declarations = [];

    /** @var list<ListensToEvents> */
    private array $listeners = [];

    /** Czy właśnie trwa publikacja — strażnik reguły „zdarzenie nie rodzi zdarzenia". */
    private bool $publishing = false;

    public function __construct()
    {
        $this->declare(self::CORE, AppEvent::declarations());
    }

    /**
     * Dokłada zdarzenia jednego właściciela, odsiewając te, które wyszły poza jego
     * przestrzeń nazw albo powtarzają nazwę już zajętą.
     *
     * Odrzucenie jest **ciche**, w odróżnieniu od rejestru komend, który zbiera
     * powody i pokazuje je użytkownikowi. Różnica bierze się z tego, kto jest
     * adresatem: nazwę komendy wpisuje człowiek i musi wiedzieć, czemu jej nie ma,
     * a nazwa zdarzenia jest wyłącznie umową między modułem a odbiorcą — jej błąd
     * jest błędem programisty i łapie go test, nie pasek stanu.
     *
     * @param list<EventDeclaration> $declarations
     */
    public function declare(string $owner, array $declarations): void
    {
        $prefix = $owner . '.';

        foreach ($declarations as $declaration) {
            $name = $declaration->name;

            if (!str_starts_with($name, $prefix) || $name === $prefix || isset($this->declarations[$name])) {
                continue;
            }

            $this->declarations[$name] = $declaration;
        }
    }

    /**
     * Moduły przyjęte przez rejestr: co wnoszą do słownika i kto z nich słucha.
     *
     * Jedna linia w `Bootstrapie` zamiast pętli po dwóch zdolnościach — wzorem
     * `ModuleTicker::of()`. Odsiew zdarza się **raz, przy składaniu aplikacji**,
     * a nie przy każdej publikacji.
     *
     * @param list<ModuleInterface> $modules
     */
    public function useModules(array $modules): void
    {
        foreach ($modules as $module) {
            if ($module instanceof DeclaresEvents) {
                $this->declare($module->id(), $module->events());
            }

            if ($module instanceof ListensToEvents) {
                $this->listeners[] = $module;
            }
        }
    }

    /** @return list<EventDeclaration> słownik w kolejności deklarowania */
    public function all(): array
    {
        return array_values($this->declarations);
    }

    public function has(string $event): bool
    {
        return isset($this->declarations[$event]);
    }

    public function isEmpty(): bool
    {
        return $this->listeners === [];
    }

    /**
     * Ogłasza zdarzenie wszystkim odbiorcom — **co nie jest zadeklarowane, nie
     * jest publikowane**.
     *
     * Sprawdzenie słownika kosztuje jedno wyszukanie w tablicy i jest ceną za to,
     * żeby obietnica zamkniętego zbioru była dosłowna: literówka w nazwie milczy
     * tu, a nie w odbiorcy — a odbiorca, który dostałby nazwę spoza spisu, nie
     * miałby jej gdzie pokazać.
     */
    public function publish(string $event): void
    {
        if ($this->listeners === [] || $this->publishing || !isset($this->declarations[$event])) {
            return;
        }

        $this->publishing = true;

        try {
            foreach ($this->listeners as $listener) {
                try {
                    $listener->onEvent($event);
                } catch (Throwable) {
                    // Świadomie połykamy **wszystko**, a nie samą hierarchię
                    // domenową jak `ModuleTicker`: tamten łapie wyjątek w fazie
                    // „aktualizuj stan" i ma gdzie go pokazać, a tu publikacja
                    // stoi w środku cudzej czynności — także w środku `report()`,
                    // czyli w miejscu, którym komunikaty się zgłasza. Odbiorca
                    // zepsuty nie zabiera przy tym zdarzenia pozostałym.
                }
            }
        } finally {
            $this->publishing = false;
        }
    }
}
