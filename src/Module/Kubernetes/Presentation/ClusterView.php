<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation;

use LightManager\Module\Kubernetes\Application\KubernetesSettings;

/**
 * Postać ekranu klastra (krok 52).
 *
 * **Jeden ekran w trzech postaciach**, wzorem kroku 49 i 51 i z tego samego
 * powodu: `ScreenStack` liczy ekrany po tożsamości, a użytkownik widzi jedno
 * miejsce, w którym zmienia się treść.
 *
 * Logi zajmują ekran w całości — ta sama decyzja, co w module Dockera i ten sam
 * rachunek: log obok drzewa miałby czterdzieści kolumn, czyli mniej, niż wynosi
 * typowy wiersz wypisu, i zawijałby każdy dwukrotnie.
 */
enum ClusterView
{
    /** Drzewo po lewej, lista albo opis po prawej. */
    case Resources;

    /** Drzewo po lewej, surowy YAML po prawej. */
    case Yaml;

    /** Logi na całym ekranie. */
    case Logs;

    public function labelKey(): string
    {
        return 'module.' . KubernetesSettings::ID . '.view.' . match ($this) {
            self::Resources => 'resources',
            self::Yaml => 'yaml',
            self::Logs => 'logs',
        };
    }
}
