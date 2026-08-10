<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Module;

/**
 * Moduł, który dopisuje własne wiersze do swojej zakładki w oknie pomocy.
 *
 * Zakładka modułu powstaje **zawsze**, gdy moduł jest przyjęty — jej część
 * automatyczną rdzeń składa z deklaracji: nazwa, opis, skrót, klawisze ekranu
 * i pozycje zakładki ustawień. Ta część nie ma prawa skłamać po zmianie
 * wiązania, bo pochodzi z tego samego miejsca, co samo wiązanie.
 *
 * Ta zdolność dokłada do niej **część własną**: to, czego z deklaracji nie da
 * się wyczytać. Metoda zwraca **klucze katalogu**, nie napisy (P16), więc treść
 * pomocy tłumaczy się tak samo jak reszta interfejsu.
 *
 * Zdolność leży w `Presentation`, bo zakładka pomocy jest elementem interfejsu,
 * a nie daną aplikacji — i dlatego, że rdzeń pyta o nią w tym samym miejscu, co
 * o wiązania klawiszy ekranu.
 */
interface ProvidesHelpTab
{
    /** @return list<string> klucze katalogu napisów, wiersz po wierszu */
    public function helpKeys(): array;
}
