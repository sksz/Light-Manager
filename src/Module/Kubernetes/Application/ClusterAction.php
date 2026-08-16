<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

/**
 * Czynność zmieniająca klaster (krok 52).
 *
 * Trzy, i każda weszła rozstrzygnięciem: `apply` i usunięcie razem (D91 nr 9),
 * zmiana sekretu osobno (nr 10). Wszystkie trzy idą tą samą drogą — proces
 * potomny, uchwyt, doglądanie — i różnią się wyłącznie tym, co powiedzą po
 * zakończeniu, więc jeden enum wystarcza za trzy klasy.
 *
 * **Czynności nieodwracalnej nie odróżnia tu nic prócz `isDestructive()`** —
 * i to wystarczy, bo pytanie zadaje ekran `ConfirmOverlay`em w wariancie
 * `dangerous`, a nie ta warstwa.
 */
enum ClusterAction: string
{
    case Apply = 'apply';

    case Delete = 'delete';

    /** Zmiana wartości, dodanie klucza albo skasowanie klucza w sekrecie. */
    case PatchSecret = 'patchSecret';

    /** Podmiana obrazu kontenera we wdrożeniu — ostatni etap `k8s.deploy-image` (krok 54). */
    case SetImage = 'setImage';

    public function isDestructive(): bool
    {
        return $this === self::Delete;
    }

    /**
     * Zdarzenie, którym czynność ogłasza swoje powodzenie.
     *
     * Mapowanie stoi **przy czynności, a nie przy ekranie**, bo to ona wie, co
     * się stało; ekran wie wyłącznie, że coś się skończyło.
     */
    public function event(): KubernetesEvent
    {
        return match ($this) {
            self::Apply => KubernetesEvent::Applied,
            self::Delete => KubernetesEvent::Deleted,
            self::PatchSecret => KubernetesEvent::SecretChanged,
            // Podmiana obrazu **jest zastosowaniem zmiany w klastrze**, więc
            // ogłasza się tym samym zdarzeniem, co `apply`. Osobne zdarzenie
            // wymagałoby rozszerzenia zamkniętego słownika (11o''), a odbiorca
            // i tak nie odróżniłby jednego od drugiego inaczej niż nazwą.
            self::SetImage => KubernetesEvent::Applied,
        };
    }

    public function doneKey(): string
    {
        return 'module.' . KubernetesSettings::ID . '.action.done.' . $this->value;
    }
}
