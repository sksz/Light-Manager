<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

/**
 * W jakim stanie jest rozmowa z klastrem (krok 52; dwa stany dołożone w kroku
 * 59).
 *
 * **Siedem stanów, z których pięć jest „nie ma podów” i różnią się tym, co
 * użytkownik ma z tym zrobić** — i to jest cała treść tego enuma. Plan kroku 52
 * żądał tego wprost: „brak bieżącego kontekstu i niedostępny klaster mają
 * własne zdania — żadne z nich nie wygląda jak awaria aplikacji”.
 *
 * Krok 59 dokłada dwa, bo miejsce ma odtąd dwie współrzędne i **każda umie być
 * nie tak**: pliku nie ma albo nie ma w nim wskazanego kontekstu. Oba są
 * odwracalne czynnością użytkownika i oba muszą powiedzieć **którą** — pod
 * zdaniem „klaster nie odpowiada" nie widać, że to literówka w ścieżce.
 */
enum ClusterStage
{
    /** Jeszcze o nic nie pytaliśmy — ekranu nikt nie otworzył. */
    case Unknown;

    /** Pytanie w drodze; pętla rysuje klatki, a odpowiedź przyjdzie, kiedy przyjdzie. */
    case Reading;

    /**
     * Plik konfiguracyjny nie wskazuje kontekstu, którym dałoby się pytać.
     *
     * Stan maszyny projektu w dniu pisania kroku i **najczęstszy stan świeżej
     * instalacji**. Odpowiedzią jest wybór, nie ponowienie.
     */
    case NoContext;

    /**
     * Kontekst jest, klaster nie odpowiada.
     *
     * Powód pochodzi ze **strumienia błędów klienta**, a nie z domysłu aplikacji
     * — „connection refused” i „certyfikat wygasł” to dwie różne rady dla
     * użytkownika, a moduł nie ma czym ich odróżnić samodzielnie.
     */
    case Unreachable;

    /**
     * Wpis wskazuje plik, którego nie ma (krok 59).
     *
     * Osobny stan, a nie odmiana nieosiągalności, bo **rada jest inna**: tu nie
     * ma czego ponawiać, dopóki ktoś nie poprawi ścieżki albo nie podepnie
     * dysku. Istnienia pliku nie sprawdza samowalidacja wpisu (plik na dysku
     * sieciowym bywa chwilowo nieobecny), więc sprawdza się je **przy użyciu**
     * — i to jest odpowiedź.
     */
    case MissingFile;

    /**
     * Plik jest, ale nie ma w nim wskazanego kontekstu (krok 59).
     *
     * Zdarza się po skasowaniu klastra (`minikube delete`), po literówce
     * w nazwie i po wskazaniu wpisem cudzego pliku. Odpowiedzią jest wybór
     * kontekstu **z tego pliku**, więc to on stoi w zdaniu.
     */
    case UnknownContext;

    case Ready;

    /** Czy wolno pytać o zasoby. */
    public function allowsQueries(): bool
    {
        return $this === self::Ready;
    }

    public function labelKey(): string
    {
        return 'module.' . KubernetesSettings::ID . '.stage.' . match ($this) {
            self::Unknown => 'unknown',
            self::Reading => 'reading',
            self::NoContext => 'noContext',
            self::Unreachable => 'unreachable',
            self::MissingFile => 'missingFile',
            self::UnknownContext => 'unknownContext',
            self::Ready => 'ready',
        };
    }
}
