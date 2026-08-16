<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Application\Event\EventDeclaration;

/**
 * Momenty, o których ogłasza moduł Dockera (krok 51, przez mechanizm kroku 46).
 *
 * Nazwy stoją w **przestrzeni publikującego** (`docker.*`), bo spoza niej
 * odsiewa je `EventRegistry`. Zamknięcie słownika jest **konstrukcyjne**:
 * deklaracje powstają z `cases()`, więc publikacja i spis u odbiorcy nie mają
 * jak się rozjechać.
 *
 * **Dwa zdarzenia budowy są tu najważniejsze i nie dla użytkownika**:
 * `docker.build.finished` i `docker.build.failed` to jest to, **na czym stanie
 * krok 53**. Budowa trwa minutami, więc moduł, który ją zamówił, nie ma jak
 * czekać w klatce — dowie się zdarzeniem i dopiero wtedy zapyta kwerendą o wynik.
 * Plan kroku mówi o tym wprost: „to jest miejsce, w którym one powstają”.
 *
 * Czynności na kontenerze mają **jedno zdarzenie na powodzenie i jedno na
 * odmowę**, a nie po parze na czynność. Granica jest ta sama, którą krok 50
 * przyłożył do przesyłu: kierunek bywa szczegółem czynności, a nie inną
 * czynnością — „zatrzymany" i „uruchomiony" różnią się dla odbiorcy tym samym,
 * czym „pobrany" i „wysłany", czyli niczym, co warto odróżniać dźwiękiem.
 */
enum DockerEvent: string
{
    /** Kontener zmienił stan na życzenie użytkownika — start, stop, restart. */
    case ContainerChanged = 'docker.container.changed';

    /** Kontener albo obraz zniknął — czynność nieodwracalna, która się udała. */
    case Removed = 'docker.removed';

    /** Demon odmówił czynności albo rozmowa się urwała. */
    case ActionFailed = 'docker.action.failed';

    /** Obraz zbudowany — **na tym stanie krok 53**. */
    case BuildFinished = 'docker.build.finished';

    /** Budowa się nie udała — z dowolnego powodu, łącznie z przerwaniem. */
    case BuildFailed = 'docker.build.failed';

    /** Projekt compose podniesiony albo położony. */
    case ComposeChanged = 'docker.compose.changed';

    public function labelKey(): string
    {
        return 'module.' . DockerSettings::ID . '.event.'
            . substr($this->value, strlen(DockerSettings::ID) + 1);
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
