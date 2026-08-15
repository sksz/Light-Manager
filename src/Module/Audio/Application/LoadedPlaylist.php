<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Application;

/**
 * Wynik wczytania playlisty: lista plus ewentualny powód, dla którego jest pusta
 * (krok 45).
 *
 * Kształt wzięty wprost z `Application\Dto\LoadedSettings` i z tego samego
 * powodu: plik ruszony ręcznie nie ma prawa przerwać startu ani rzucić wyjątkiem
 * przez granicę portu. Problem wraca **kluczem katalogu napisów**, bo warstwa
 * `Application` napisów nie zna (reguła 7), a pokazuje go dopiero ten, kto ma
 * gdzie — pasek stanu przy pierwszym sięgnięciu po playlistę.
 */
final class LoadedPlaylist
{
    public function __construct(
        public readonly Playlist $playlist,
        /** Klucz katalogu napisów albo `null`, gdy wczytanie się udało. */
        public readonly ?string $problemKey = null,
        /**
         * Czy pliku jeszcze nie było.
         *
         * Pole istnieje dla **migracji** i tylko dla niej (krok 45, zakres 5):
         * playlista pusta znaczy co innego, gdy plik dopiero ma powstać („zasiej
         * ją utworem z dawnego klucza `track`”), a co innego, gdy użytkownik
         * własnoręcznie usunął z niej wszystko („zostaw pustą”). Bez tego
         * rozróżnienia dawny utwór wracałby po każdym opróżnieniu listy.
         */
        public readonly bool $fresh = false,
    ) {
    }
}
