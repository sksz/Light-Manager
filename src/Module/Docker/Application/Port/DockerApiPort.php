<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Port;

use LightManager\Module\Docker\Application\DockerCall;
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
