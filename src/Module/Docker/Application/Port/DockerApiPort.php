<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Port;

use LightManager\Module\Docker\Application\DockerCall;
use LightManager\Module\Docker\Application\DockerEndpoint;
use LightManager\Module\Docker\Application\DockerResult;

/**
 * Rozmowa z demonem Dockera po gnieździe unixowym — **nigdy w rysowaniu klatki**
 * (krok 51).
 *
 * Kształt portu jest podyktowany tym samym, co kształt `BackgroundProcessPort`
 * w kroku 26, i celowo go przypomina: nie ma tu metody `containers(): array`,
 * bo taka metoda czekałaby na demona w środku klatki. Jest **zadanie pytania,
 * które nie czeka ani chwili**, **zaglądanie, które nigdy nie blokuje**,
 * i przerywanie.
 *
 * Reguła nadrzędna, ta sama, co w Fazie XVII: **żadne wywołanie sieciowe nie
 * pada w rysowaniu klatki**. Gniazdo unixowe jest tu wprawdzie tanie i lokalne,
 * ale demon potrafi milczeć — zajęty budową, zatrzymywany, przeciążony — a klatka
 * nie ma jak na to poczekać.
 *
 * **Dwa rodzaje pytań i to jest cały podział tego portu.** Zwykłe (`get`,
 * `post`, `delete`) kończy się odpowiedzią i oddaje ją w całości. Płynące
 * (`follow`) nie kończy się samo: logi z `follow=1` idą, dopóki żyje kontener,
 * a postęp budowy — dopóki trwa budowa. Odpowiedź takiego pytania odbiera się
 * porcjami, przy każdym zajrzeniu.
 *
 * **Pompowanie jest osobne od zaglądania** — jak w rdzeniowym porcie pracy
 * tłowej po kroku 51 i z tego samego powodu: rozmów bywa kilka, a bufor
 * nieczytany zatrzymuje nadawcę. `pump()` woła takt modułu, raz na klatkę, dla
 * wszystkich rozmów naraz.
 */
interface DockerApiPort
{
    /**
     * Dokąd mają iść kolejne pytania (krok 58) — dana z wybranego wpisu
     * środowiska.
     *
     * Podaje ją takt modułu, raz na klatkę, bo tylko on widzi naraz wpis
     * bieżący i stan tunelu. Rozmowy trwające w chwili zmiany **zostają przy
     * swojej drodze** — przełączenie środowiska i tak je kończy, bo unieważnia
     * listy, logi i budowę (kryterium kroku).
     */
    public function useEndpoint(DockerEndpoint $endpoint): void;

    /** Pytanie o dane: lista kontenerów, lista obrazów, opis. */
    public function get(string $path): DockerCall;

    /**
     * Pytanie zmieniające: start, stop, restart, budowa.
     *
     * @param ?string $body        treść żądania — `null` dla czynności bez treści
     * @param ?string $contentType typ treści; przy budowie to archiwum tar
     */
    public function post(string $path, ?string $body = null, ?string $contentType = null): DockerCall;

    /** Pytanie usuwające: kontener, obraz. */
    public function delete(string $path): DockerCall;

    /**
     * Wypchnięcie obrazu do rejestru — **POST płynący, z poświadczeniami
     * w nagłówku** (krok 54, D94 nr 2).
     *
     * Metoda osobna, a nie `post()` z dwoma argumentami więcej, bo różni się od
     * niej **dwiema rzeczami naraz** i obie są istotne. Po pierwsze płynie:
     * odpowiedź to strumień zdań o postępie warstw, a wypchnięcie gigabajtowego
     * obrazu trwa dłużej niż limit czasu zwykłego wywołania — `post()`
     * urwałoby je w połowie. Po drugie niesie `X-Registry-Auth`, którego demon
     * **nie ma skąd wziąć**: `~/.docker/config.json` jest plikiem klienta, a nie
     * demona, więc poświadczenia składa ten, kto pcha.
     *
     * @param string $registryAuth wartość nagłówka `X-Registry-Auth` — obiekt JSON
     *                             zakodowany base64 wedle URL (patrz `RegistryAuth`)
     */
    public function push(string $path, string $registryAuth): DockerCall;

    /**
     * Pytanie płynące: logi na żywo, postęp budowy.
     *
     * Różni się od `get()` dwiema rzeczami: **nie ma limitu czasu** (bo nie ma
     * końca, na który miałby czekać) i **oddaje treść porcjami**, a nie w całości
     * na końcu.
     */
    public function follow(string $path): DockerCall;

    /**
     * Zagląda, co doszło. **Nigdy nie blokuje.**
     *
     * Przy pytaniu płynącym zwraca **porcję od poprzedniego zajrzenia** i tę
     * porcję zabiera z bufora — dwa zajrzenia w tej samej klatce oddadzą treść
     * pierwszemu z nich. Wołanie z uchwytem nieznanym portowi oddaje `Idle`.
     */
    public function poll(DockerCall $call): DockerResult;

    /**
     * Kończy rozmowę i sprząta po niej. Wolno wołać zawsze — także dla uchwytu
     * rozmowy zakończonej albo nieznanej.
     */
    public function stop(DockerCall $call): void;

    /**
     * Posuwa wszystkie rozmowy o tyle, ile dało się bez czekania — jedno
     * wywołanie na takt.
     *
     * **Nigdy nie blokuje i nigdy nie rzuca.** Wołanie bez ani jednej rozmowy
     * kosztuje jedno sprawdzenie pustej tablicy.
     */
    public function pump(): void;
}
