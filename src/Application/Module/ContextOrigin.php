<?php

declare(strict_types=1);

namespace LightManager\Application\Module;

/**
 * Czyja jest ścieżka, którą niesie kontekst (krok 49).
 *
 * Pojęcie weszło do rdzenia dlatego, że bez niego kontekst **cicho kłamał**.
 * `ModuleContext` niósł ścieżkę jako napis, bez informacji, na której maszynie
 * ona leży — więc ekran zdalny, który opublikowałby `/var/log`, sprawiłby, że
 * moduł opisu pliku pokaże **lokalny** `/var/log`: tamten czyta ścieżkę
 * `lstat`em i nie ma jak zauważyć, że mówi o cudzej maszynie. Kłamstwo było
 * przy tym najgorszego rodzaju — obie ścieżki istnieją, obie się czytają,
 * a użytkownik ogląda opis nie tego pliku, na który patrzy.
 *
 * Enum jest dwustanowy i **ma taki zostać, dopóki nie przyjdzie trzeci
 * wydawca**: „zdalne" znaczy tu „nie do przeczytania wywołaniem systemowym
 * tego procesu”, a nie „przez SSH”. Protokół, host i sposób połączenia są
 * własnością modułu, który kontekst publikuje — rdzeń ma znać wyłącznie
 * granicę, za którą jego własne narzędzia przestają działać, i podpis
 * miejsca (`originLabel`) do pokazania użytkownikowi.
 *
 * Rozstrzygnięcie użytkownika ze startu kroku 49 poszło **wbrew rekomendacji
 * planu**, która proponowała najtańsze wyjście: ekran zdalny nie publikuje
 * kontekstu w ogóle. Cena wybranego wariantu jest ta sama, co zawsze przy
 * regule 13 — mechanizm wchodzi **razem z odbiorcą**, więc moduł opisu pliku
 * uczy się w tym samym kroku opisywać wpis, którego nie może dotknąć.
 */
enum ContextOrigin
{
    /** Ścieżka na maszynie, na której działa aplikacja — stan domyślny. */
    case Local;

    /**
     * Ścieżka na innej maszynie: istnieje, ale nie dla `stat()` tego procesu.
     *
     * Odbiorca ma z niej prawo pokazać **wyłącznie to, co niesie kontekst**.
     * Sięgnięcie po resztę wymagałoby sieci, a ta nie pada w rysowaniu klatki
     * (reguła nadrzędna Fazy XVII).
     */
    case Remote;
}
