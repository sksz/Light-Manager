<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

/**
 * Skąd wpis w spisie klastrów się wziął (krok 59, D96 nr 3).
 *
 * **Pochodzenie jest widoczne** — pierwsza z trzech reguł dwóch źródeł jednej
 * listy, ta sama, co w spisie środowisk Dockera: wpis czytany z cudzego pliku
 * ma znacznik i nie da się go z aplikacji skasować, bo moduł do `kubeconfig`
 * nie pisze (zdanie z kroku 52, tu podtrzymane).
 *
 * Źródła czytane są dwa i rozróżnialne, bo różnią się radą dla użytkownika:
 * plik domyślny ma każdy, ścieżki z `KUBECONFIG` — tylko ten, kto je ustawił.
 */
enum ClusterOrigin: string
{
    /** Wpis własny — z książki modułu w sekcji `k8s` dokumentu stanu. */
    case Own = 'own';

    /** Kontekst z domyślnego `~/.kube/config` — czytany, nigdy nie zmieniany. */
    case DefaultConfig = 'config';

    /** Kontekst z pliku wskazanego zmienną `KUBECONFIG`. */
    case EnvConfig = 'env';

    public function labelKey(): string
    {
        return 'module.k8s.cluster.origin.' . $this->value;
    }
}
