<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Infrastructure;

use CurlHandle;
use CurlMultiHandle;
use LightManager\Module\Docker\Application\DockerResult;

/**
 * Jedna rozmowa z demonem: uchwyt curl, bufor i to, czym się skończyła
 * (krok 51).
 *
 * Klasa jest wobec `DockerApiService` tym, czym `BackgroundJob` wobec
 * `BackgroundProcessService`, i powstała z tego samego powodu: pól opisujących
 * „bieżące żądanie” jest siedem, a rozmów bywa kilka naraz. Siedem tablic
 * trzymanych obok siebie i zgodnych co do kluczy byłoby zaproszeniem do rozmowy,
 * o której nikt już nie pamięta.
 *
 * Usługą to nie jest i Singletonem być nie może (reguła 3 mówi o usługach):
 * zapis wewnętrzny, powoływany `new`-em, ginący razem z rozmową i nigdy nie
 * wychodzący poza katalog `Infrastructure` modułu. **Uchwytu curl nie wypuszcza
 * na zewnątrz** — odłączenie od zbioru prowadzonego przez `curl_multi_*` robi
 * u siebie, bo inaczej wołający musiałby najpierw zapytać, czy uchwyt w ogóle
 * istnieje.
 */
final class DockerConversation
{
    private string $buffer = '';

    private bool $attached;

    private bool $finished = false;

    private int $status = 0;

    private ?string $problemKey = null;

    public function __construct(
        /** `null` wyłącznie w rozmowie odmówionej — tej, która nie ruszyła w ogóle. */
        private readonly ?CurlHandle $curl,
        private readonly bool $streaming,
        private readonly int $bufferLimit,
    ) {
        $this->attached = $curl instanceof CurlHandle;
    }

    /** Rozmowa, której nie było — powód zamiast uchwytu. */
    public static function refused(string $problemKey): self
    {
        $conversation = new self(null, false, 0);
        $conversation->finished = true;
        $conversation->problemKey = $problemKey;

        return $conversation;
    }

    public function owns(CurlHandle $handle): bool
    {
        return $this->curl === $handle;
    }

    /**
     * Zdejmuje uchwyt ze zbioru prowadzonego przez curl — **bez zamykania go**.
     *
     * Rozdzielenie odłączenia od zamknięcia jest konieczne: rozmowa zakończona
     * ma jeszcze oddać treść temu, kto o nią pytał, więc ze zbioru schodzi od
     * razu, a ginie dopiero razem z rozmową.
     */
    public function detachFrom(CurlMultiHandle $multi): void
    {
        if (!$this->attached || !$this->curl instanceof CurlHandle) {
            return;
        }

        curl_multi_remove_handle($multi, $this->curl);
        $this->attached = false;
    }

    /**
     * Przyjmuje porcję odpowiedzi. Wołane przez curl, **nie przez nas**.
     *
     * Zwrócona liczba jest dla curla umową: tyle bajtów przyjęliśmy. Liczba
     * mniejsza niż długość porcji **przerywa transfer** — i to jest jedyny
     * sposób, żeby zatrzymać rozmowę sypiącą ponad granicę.
     */
    public function collect(string $chunk): int
    {
        if (strlen($this->buffer) + strlen($chunk) > $this->bufferLimit) {
            $this->problemKey = 'module.docker.stream.flood';
            $this->finished = true;

            return 0;
        }

        $this->buffer .= $chunk;

        return strlen($chunk);
    }

    /** Stan do obejrzenia; przy rozmowie płynącej **zabiera** uzbierane bajty. */
    public function result(): DockerResult
    {
        if ($this->problemKey !== null) {
            return DockerResult::failed($this->problemKey);
        }

        if (!$this->finished) {
            return DockerResult::running($this->streaming ? $this->take() : '', $this->status);
        }

        return DockerResult::done($this->streaming ? $this->take() : $this->buffer, $this->status);
    }

    /**
     * Zamyka rozmowę wynikiem curla.
     *
     * Kod curla różny od zera znaczy, że **rozmowy nie było albo się urwała** —
     * i jest to co innego niż odmowa demona: ta przychodzi kodem HTTP i jest
     * odpowiedzią, a nie brakiem odpowiedzi.
     */
    public function finish(int $status, int $curlResult): void
    {
        $this->finished = true;
        $this->status = $status;

        if ($curlResult !== CURLE_OK && $this->problemKey === null) {
            $this->problemKey = $curlResult === CURLE_OPERATION_TIMEDOUT
                ? 'module.docker.daemon.timedOut'
                : 'module.docker.daemon.unreachable';
        }
    }

    /**
     * Koniec rozmowy.
     *
     * `curl_close()` tu **nie pada i paść nie może**: od PHP 8.0 uchwyt jest
     * obiektem zwalnianym przez licznik odwołań, a funkcja jest od 8.6
     * przestarzała. Uchwyt ginie razem z tą rozmową — a rozmowa ginie wtedy, gdy
     * usługa wyrzuci ją ze swojej tablicy. Metoda zostaje mimo to, bo zamknięcie
     * jest **czynnością w cyklu życia rozmowy**, a nie skutkiem ubocznym
     * sprzątania pamięci: odłączenie od zbioru curla musi się zdarzyć jawnie
     * i przed zwolnieniem uchwytu.
     */
    public function close(): void
    {
        $this->finished = true;
        $this->buffer = '';
    }

    /** Bajty uzbierane od poprzedniego zajrzenia — i tylko raz. */
    private function take(): string
    {
        $taken = $this->buffer;
        $this->buffer = '';

        return $taken;
    }
}
