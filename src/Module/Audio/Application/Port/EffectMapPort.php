<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Application\Port;

use LightManager\Module\Audio\Application\EffectMap;

/**
 * Trwałość przypisań „zdarzenie → plik" (krok 46).
 *
 * Port jest **drugi obok `PlaylistPort`, a plik jeden** — i to była cała treść
 * rozstrzygnięcia D82 nr 3, podjętego krok wcześniej właśnie po to: dokument
 * `~/.light-manager/audio.json` dostał wtedy kształt, który uniesie mapę
 * **kluczem, a nie drugim plikiem**, a klucze nieznane danej wersji przeżywają
 * zapis nietknięte. Dzięki temu ten krok nie dotyka ani jednej linii zapisu
 * playlisty.
 *
 * Dwa porty zamiast jednego, choć usługa jest ta sama, z tego samego powodu, dla
 * którego rdzeń ma trzy porty pisania po dysku (D79 nr 1): odbiorcy są różni
 * i żaden nie ma powodu widzieć cudzych metod. Playlisty dotyka odtwarzacz,
 * mapy — odtwarzacz efektów.
 *
 * Żadna metoda nie rzuca. Plik ruszony ręcznie daje **pustą mapę**, a nieudany
 * zapis ginie po cichu: aplikacja działa wtedy tak, jak działała przed tym
 * krokiem.
 */
interface EffectMapPort
{
    public function loadEffects(): EffectMap;

    public function saveEffects(EffectMap $effects): void;
}
