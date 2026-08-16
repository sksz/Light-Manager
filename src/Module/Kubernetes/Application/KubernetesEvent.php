<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Application\Event\EventDeclaration;

/**
 * Momenty, o których ogłasza moduł klastra (krok 52, przez mechanizm kroku 46).
 *
 * Cztery, i tyle właśnie wymienia plan kroku: zastosowano plik, usunięto zasób,
 * zmieniono sekret, utracono połączenie z klastrem. Nazwy stoją w przestrzeni
 * publikującego (`k8s.*`), bo spoza niej odsiewa je `EventRegistry`, a zamknięcie
 * słownika jest **konstrukcyjne** — deklaracje powstają z `cases()`.
 *
 * **Zmiana sekretu ma własne zdarzenie**, choć technicznie jest tym samym
 * `patch`em, co każda inna zmiana w miejscu. Powód jest ten sam, dla którego
 * krok 46 dał przeglądarce siedemnaście zdarzeń zamiast trzech: odbiorca ma
 * odróżnić „coś się zmieniło w klastrze” od „zmieniło się hasło”, bo to dwie
 * różne wiadomości dla człowieka siedzącego przed terminalem.
 */
enum KubernetesEvent: string
{
    /** Plik zastosowany — `kubectl apply` zakończony powodzeniem. */
    case Applied = 'k8s.applied';

    /** Zasób usunięty — czynność nieodwracalna, która się udała. */
    case Deleted = 'k8s.deleted';

    /** Sekret zmieniony: wartość, nowy klucz albo skasowany klucz. */
    case SecretChanged = 'k8s.secret.changed';

    /** Klaster przestał odpowiadać albo odmówił czynności. */
    case ConnectionLost = 'k8s.connection.lost';

    public function labelKey(): string
    {
        return 'module.' . KubernetesSettings::ID . '.event.'
            . substr($this->value, strlen(KubernetesSettings::ID) + 1);
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
