<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Infrastructure;

use CurlHandle;
use CurlMultiHandle;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Docker\Application\DockerCall;
use LightManager\Module\Docker\Application\DockerResult;
use LightManager\Module\Docker\Application\Port\DockerApiPort;

/**
 * Demon Dockera przez gniazdo unixowe, **bez czekania ani przez chwilę**
 * (krok 51).
 *
 * Cała klasa stoi na jednej właściwości `ext-curl`, sprawdzonej przed
 * napisaniem pierwszej linii: `CURLOPT_UNIX_SOCKET_PATH` przenosi zwykłe
 * żądanie HTTP na gniazdo pliku, a rodzina `curl_multi_*` prowadzi je
 * **nieblokująco** — `curl_multi_exec()` robi tyle, ile da się bez czekania,
 * i wraca. Dzięki temu rozmowa z demonem kosztuje klatkę tyle, co jedno
 * przejście po tablicy uchwytów, i nie potrzebuje ani procesu potomnego, ani
 * jednej zależności Composera.
 *
 * **`curl_multi_select()` nie pada tu ani razu** i to jest różnica warta
 * zapamiętania: nawet z zerowym czasem oczekiwania jest to wywołanie systemowe
 * pytające o gotowość deskryptorów, a odpowiedź „nic nie przyszło” kosztuje
 * dokładnie tyle samo, co `curl_multi_exec()` nad tymi samymi deskryptorami.
 * Pytanie zadawane trzydzieści razy na sekundę byłoby więc kosztem bez zysku.
 *
 * **Dwa rodzaje rozmów różnią się tutaj trzema ustawieniami**, nie kodem:
 * pytanie zwykłe ma limit czasu i oddaje treść na końcu, pytanie płynące limitu
 * nie ma (bo nie ma końca, na który miałoby czekać) i oddaje treść porcjami.
 * Reszta — zbieranie bajtów, kod stanu, sprzątanie — jest wspólna.
 *
 * Sprzątanie idzie **dwiema drogami naraz**, wzorem procesu potomnego z kroku 26
 * (D47): jawnie, gdy moduł kończy rozmowę, i przez funkcję zamknięcia procesu
 * rejestrowaną leniwie przy pierwszym pytaniu. Uchwyt curl niezamknięty trzyma
 * otwarty deskryptor gniazda — a demon liczy je po swojej stronie.
 */
final class DockerApiService extends AbstractSingleton implements DockerApiPort
{
    /** Gniazdo demona w miejscu, w którym stawia je każda dzisiejsza instalacja. */
    public const SOCKET_PATH = '/var/run/docker.sock';

    /**
     * Wersja API w ścieżce żądania.
     *
     * Wpisana, a nie negocjowana, i to jest świadome: demon **odmawia** żądaniu
     * o wersję nowszą niż jego własna, a wersja w ścieżce jest jedynym sposobem,
     * żeby odpowiedź miała kształt, którego się spodziewamy. `1.41` jest tu
     * ostrożnym dołem — pochodzi z Dockera 20.10 (2020), a wszystkie pola,
     * których używamy, są w nim od dawna. Demon w wersji nowszej obsłuży ją bez
     * zastrzeżeń; maszyna projektu ma 1.47.
     */
    private const API_VERSION = 'v1.41';

    /** Nazwa hosta jest tu obowiązkowa i bez znaczenia — żądanie i tak idzie gniazdem. */
    private const BASE_URL = 'http://localhost/' . self::API_VERSION;

    /** Ile sekund czekać na odpowiedź pytania zwykłego, zanim uznamy je za stracone. */
    private const CALL_TIMEOUT_SECONDS = 20;

    /** Ile milisekund czekać na samo połączenie z gniazdem — lokalne, więc krótko. */
    private const CONNECT_TIMEOUT_MS = 2000;

    /**
     * Ile najwyżej bajtów wolno uzbierać jednej rozmowie między zajrzeniami.
     *
     * Rozmowa płynąca jest opróżniana co klatkę, więc w praktyce mieści się tu
     * jedna trzydziesta sekundy wypisu. Granica istnieje dla przypadku
     * patologicznego — kontenera sypiącego megabajtami na sekundę — i **kończy
     * rozmowę zamiast uciąć bajty**: ucięcie w środku ośmiobajtowej ramki
     * rozjechałoby wszystko, co po niej przyjdzie, i wyglądałoby jak uszkodzone
     * logi, a nie jak przekroczona granica.
     */
    private const MAX_BUFFER_BYTES = 8 * 1024 * 1024;

    private ?CurlMultiHandle $multi = null;

    /**
     * Rozmowy tego uruchomienia — numer uchwytu → rozmowa.
     *
     * @var array<int, DockerConversation>
     */
    private array $calls = [];

    private int $lastId = 0;

    private bool $shutdownRegistered = false;

    /** Czy w tym środowisku jest czym i przez co rozmawiać (reguła 11s — tanio). */
    public static function isSupported(): bool
    {
        return extension_loaded('curl')
            && defined('CURLOPT_UNIX_SOCKET_PATH')
            && file_exists(self::SOCKET_PATH);
    }

    public function get(string $path): DockerCall
    {
        return $this->begin($path, streaming: false);
    }

    public function post(string $path, ?string $body = null, ?string $contentType = null): DockerCall
    {
        return $this->begin($path, streaming: false, method: 'POST', body: $body, contentType: $contentType);
    }

    public function delete(string $path): DockerCall
    {
        return $this->begin($path, streaming: false, method: 'DELETE');
    }

    public function follow(string $path): DockerCall
    {
        return $this->begin($path, streaming: true);
    }

    public function poll(DockerCall $call): DockerResult
    {
        return ($this->calls[$call->id] ?? null)?->result() ?? DockerResult::idle();
    }

    public function pump(): void
    {
        $multi = $this->multi;

        if ($multi === null || $this->calls === []) {
            return;
        }

        $running = 0;

        // Pierwsze wywołanie posuwa transfery, dalsze są potrzebne dopóty, dopóki
        // curl mówi, że ma co robić **bez czekania**. Pętla kończy się zawsze —
        // `CURLM_CALL_MULTI_PERFORM` znaczy „mam gotową pracę”, a nie „poczekaj”.
        do {
            $status = curl_multi_exec($multi, $running);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        while (($message = curl_multi_info_read($multi)) !== false) {
            $this->settle($message);
        }
    }

    public function stop(DockerCall $call): void
    {
        $conversation = $this->calls[$call->id] ?? null;

        if ($conversation === null) {
            return;
        }

        $this->release($conversation);
        unset($this->calls[$call->id]);
    }

    /**
     * Sprzątanie przy wyjściu z aplikacji — metoda spoza portu, wzorem
     * `BackgroundProcessService::shutdown()`.
     *
     * Moduł kończy własne rozmowy uchwytami, które trzyma; o zamykaniu aplikacji
     * nie wie i nie ma prawa wiedzieć.
     */
    public function shutdown(): void
    {
        foreach ($this->calls as $conversation) {
            $this->release($conversation);
        }

        $this->calls = [];

        if ($this->multi !== null) {
            curl_multi_close($this->multi);
            $this->multi = null;
        }
    }

    private function begin(
        string $path,
        bool $streaming,
        string $method = 'GET',
        ?string $body = null,
        ?string $contentType = null,
    ): DockerCall {
        $call = new DockerCall(++$this->lastId);

        if (!self::isSupported()) {
            $this->calls[$call->id] = DockerConversation::refused('module.docker.daemon.unsupported');

            return $call;
        }

        $handle = curl_init();
        $multi = $this->multi ??= curl_multi_init();

        if (!$handle instanceof CurlHandle) {
            $this->calls[$call->id] = DockerConversation::refused('module.docker.daemon.failed');

            return $call;
        }

        $conversation = new DockerConversation($handle, $streaming, self::MAX_BUFFER_BYTES);

        curl_setopt_array($handle, $this->options($handle, $conversation, $path, $streaming, $method, $body, $contentType));
        curl_multi_add_handle($multi, $handle);

        $this->calls[$call->id] = $conversation;
        $this->registerShutdownHandler();

        return $call;
    }

    /**
     * Ustawienia jednego żądania.
     *
     * `CURLOPT_WRITEFUNCTION` jest tu sercem rzeczy, a nie ozdobą: bez niego curl
     * zbierałby całą odpowiedź u siebie i oddał ją na końcu — czyli **rozmowa
     * płynąca nie oddałaby ani bajtu, dopóki kontener żyje**. Z nim każda porcja
     * ląduje w buforze rozmowy, a moduł zabiera ją w najbliższej klatce.
     *
     * @return array<int, mixed>
     */
    private function options(
        CurlHandle $handle,
        DockerConversation $conversation,
        string $path,
        bool $streaming,
        string $method,
        ?string $body,
        ?string $contentType,
    ): array {
        $options = [
            CURLOPT_UNIX_SOCKET_PATH => self::SOCKET_PATH,
            CURLOPT_URL => self::BASE_URL . $path,
            CURLOPT_CONNECTTIMEOUT_MS => self::CONNECT_TIMEOUT_MS,
            CURLOPT_WRITEFUNCTION => static fn (CurlHandle $ignored, string $chunk): int
                => $conversation->collect($chunk),
            // Limitu czasu **nie ma przy rozmowie płynącej** i nie jest to
            // niedopatrzenie: logi z `follow=1` płyną tak długo, jak żyje
            // kontener, więc limit znaczyłby „urwij działającą funkcję po
            // dwudziestu sekundach”.
            CURLOPT_TIMEOUT => $streaming ? 0 : self::CALL_TIMEOUT_SECONDS,
        ];

        if ($method !== 'GET') {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
        }

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        // Nagłówek typu treści idzie **zawsze przy żądaniu z treścią**, bo demon
        // odsyła `400` żądaniu budowy bez `application/x-tar` — i odsyła je
        // zanim przeczyta choćby bajt archiwum.
        $options[CURLOPT_HTTPHEADER] = $contentType !== null
            ? ['Content-Type: ' . $contentType]
            : ['Content-Type: application/json'];

        return $options;
    }

    /**
     * Zamyka rozmowę, którą curl uznał za skończoną.
     *
     * Kod stanu HTTP bierzemy **tutaj**, a nie przy odczycie: po zamknięciu
     * uchwytu `curl_getinfo()` nie ma już czego zapytać.
     *
     * @param array<array-key, mixed> $message wpis z `curl_multi_info_read()`
     */
    private function settle(array $message): void
    {
        $handle = $message['handle'] ?? null;
        $result = $message['result'] ?? CURLE_OK;

        if (!$handle instanceof CurlHandle) {
            return;
        }

        foreach ($this->calls as $conversation) {
            if (!$conversation->owns($handle)) {
                continue;
            }

            // `curl_getinfo()` z jedną opcją oddaje wartość tej opcji — dla kodu
            // odpowiedzi zawsze liczbę, także wtedy, gdy odpowiedzi nie było
            // (zero). Wpis z `curl_multi_info_read()` jest za to zwykłą tablicą
            // i jego pola trzeba sprawdzić.
            $conversation->finish(
                curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
                is_int($result) ? $result : CURLE_OK,
            );
            $this->detach($conversation);

            return;
        }
    }

    private function release(DockerConversation $conversation): void
    {
        $this->detach($conversation);
        $conversation->close();
    }

    private function detach(DockerConversation $conversation): void
    {
        if ($this->multi !== null) {
            $conversation->detachFrom($this->multi);
        }
    }

    private function registerShutdownHandler(): void
    {
        if ($this->shutdownRegistered) {
            return;
        }

        $this->shutdownRegistered = true;

        // Rejestracja jest **leniwa**, jak przy procesie potomnym: aplikacja,
        // która nie zapytała demona o nic, nie ma czego sprzątać.
        register_shutdown_function(function (): void {
            $this->shutdown();
        });
    }
}
