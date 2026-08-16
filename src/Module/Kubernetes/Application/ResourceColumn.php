<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

/**
 * Kolumna listy zasobów ponad te, które ma **każdy** zasób (krok 52).
 *
 * Nazwa, przestrzeń nazw i wiek biorą się z `metadata` i są wszędzie takie same,
 * więc nie mają tu swoich pozycji. Enum opisuje to, co jest **własnością
 * rodzaju**: gotowość poda, liczbę restartów, typ usługi, liczbę kluczy sekretu.
 *
 * **Spis jest zamknięty i tak ma zostać** — konstrukcyjnie, jak słownik zdarzeń
 * z kroku 46. Rodzaj spoza pakietów (a więc każdy CRD) dostaje trzy kolumny
 * ogólne i **nie jest to brak**, tylko cena rozstrzygnięcia D91 nr 4: kolumny
 * liczy kod modułu, więc istnieją dokładnie te, które ktoś napisał. Odrzucona
 * przy tamtym rozstrzygnięciu droga — tabela drukowana przez serwer — dawała
 * kolumny każdemu rodzajowi za darmo i to była jej jedyna przewaga.
 */
enum ResourceColumn: string
{
    /** Ilu kontenerów poda działa wobec ilu zadeklarowanych (`1/1`). */
    case Ready = 'ready';

    /** Faza poda albo powód, dla którego w niej stoi (`Running`, `CrashLoopBackOff`). */
    case Status = 'status';

    case Restarts = 'restarts';

    /** Węzeł, na którym pod stoi — pierwsze pytanie przy podzie, który nie wstaje. */
    case Node = 'node';

    /** Ile replik wdrożenia ma nowe wydanie. */
    case UpToDate = 'upToDate';

    /** Ile replik wdrożenia jest gotowych do przyjmowania ruchu. */
    case Available = 'available';

    /** Rodzaj usługi (`ClusterIP`, `NodePort`, `LoadBalancer`). */
    case Type = 'type';

    case ClusterIp = 'clusterIp';

    case Ports = 'ports';

    /** Ile kluczy niesie sekret albo mapa konfiguracji — **nigdy ich wartości**. */
    case Data = 'data';

    public function labelKey(): string
    {
        return 'module.' . KubernetesSettings::ID . '.column.' . $this->value;
    }

    /**
     * Ile znaków rezerwuje kolumna.
     *
     * Miara jest tutaj, a nie w warstwie rysującej, bo wynika z **treści**, a nie
     * z wyglądu: `1/1` mieści się w pięciu znakach, a `CrashLoopBackOff` wymaga
     * dwudziestu i skrócone do dziesięciu przestaje odpowiadać na pytanie, które
     * zadaje się patrząc na tę kolumnę.
     */
    public function width(): int
    {
        return match ($this) {
            self::Ready => 7,
            self::Status => 20,
            self::Restarts => 9,
            self::Node => 22,
            self::UpToDate, self::Available => 10,
            self::Type => 14,
            self::ClusterIp => 16,
            self::Ports => 18,
            self::Data => 6,
        };
    }
}
