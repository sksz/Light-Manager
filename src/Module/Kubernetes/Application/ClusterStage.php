<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

/**
 * W jakim stanie jest rozmowa z klastrem (krok 52).
 *
 * **Pięć stanów, z których trzy są „nie ma podów” i różnią się tym, co
 * użytkownik ma z tym zrobić** — i to jest cała treść tego enuma. Plan kroku
 * żąda tego wprost: „brak bieżącego kontekstu i niedostępny klaster mają własne
 * zdania — żadne z nich nie wygląda jak awaria aplikacji”.
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
            self::Ready => 'ready',
        };
    }
}
