<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

/**
 * Gdzie stoi zakładanie sekretu rejestru (krok 61, etap 3).
 *
 * Etapy są trzy, bo `ClusterActions` prowadzi **jedną czynność naraz**, a ta
 * potrzebuje dwóch wywołań `kubectl` po kolei: zastosowania manifestu
 * i dopięcia sekretu do wdrożenia. Ciąg wywołań byłby tu blokowaniem klatki,
 * więc jest to maszyna stanu posuwana taktem — ta sama droga, co przy rozmowie
 * z rejestrem.
 */
enum PullSecretStage
{
    case Idle;

    /** `kubectl apply -f <plik>` — sekret powstaje w klastrze. */
    case Applying;

    /** `kubectl patch` — sekret dopina się do wdrożenia (łata **strategiczna**). */
    case Attaching;

    case Done;

    case Failed;
}
