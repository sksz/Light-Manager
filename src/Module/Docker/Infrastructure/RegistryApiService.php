<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Infrastructure;

use CurlHandle;
use CurlMultiHandle;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Docker\Application\Port\RegistryPort;
use LightManager\Module\Docker\Application\Registry\RegistryCall;
use LightManager\Module\Docker\Application\Registry\RegistryEndpoint;
use LightManager\Module\Docker\Application\Registry\RegistryResult;
use LightManager\Module\Docker\Application\Registry\RegistryStage;

/**
 * Rozmowa z rejestrem obrazów po HTTP — **ta sama maszyneria, co rozmowa
 * z demonem, i inny rozmówca** (krok 61, etap 2).
 *
 * `curl_multi_*` w trybie nieblokującym, pompowane raz na takt. Powód jest ten
 * sam, dla którego moduł nie ma blokującego klienta demona: **żadne wywołanie
 * sieciowe nie pada w rysowaniu klatki**, a rejestr stoi w internecie, więc
 * pytanie do niego trwa dziesiątki albo setki milisekund — czyli wielokrotność
 * budżetu klatki.
 *
 * **Trzy obiegi zamiast jednego** i to jest cała nowość wobec `DockerApiService`.
 * `GET /v2/…` bez tokenu oddaje `401` z nagłówkiem `WWW-Authenticate`; pytanie
 * pod `realm` — z podstawowym uwierzytelnieniem — oddaje token; dopiero trzecie
 * wywołanie, podpisane `Authorization: Bearer`, przynosi odpowiedź. Wszystkie
 * trzy są **nieblokujące**, więc jest to maszyna stanu (`RegistryConversation`),
 * a nie ciąg wywołań — kolejny obieg zaczyna się w `pump()`, nie w oczekiwaniu.
 *
 * Rejestr **bez uwierzytelnienia** — a takim jest `registry:2` z celu
 * `make registry-start` — odpowiada `200` już w pierwszym obiegu i pozostałe
 * dwa nie padają w ogóle.
 */
final class RegistryApiService extends AbstractSingleton implements RegistryPort
{
    private const CONNECT_TIMEOUT_MS = 3000;

    private const CALL_TIMEOUT_SECONDS = 20;

    /** Katalog dużego rejestru bywa długi; poza tę granicę i tak nie czytamy. */
    private const MAX_BUFFER_BYTES = 4 * 1024 * 1024;

    private ?CurlMultiHandle $multi = null;

    /** @var array<int, RegistryConversation> */
    private array $calls = [];

    private int $lastId = 0;

    private RegistryEndpoint $endpoint;

    protected function __construct()
    {
        parent::__construct();
        $this->endpoint = new RegistryEndpoint('localhost');
    }

    public function useRegistry(RegistryEndpoint $endpoint): void
    {
        $this->endpoint = $endpoint;
    }

    public function catalog(): RegistryCall
    {
        return $this->begin('/v2/_catalog');
    }

    public function tags(string $image): RegistryCall
    {
        return $this->begin('/v2/' . $image . '/tags/list');
    }

    public function poll(RegistryCall $call): RegistryResult
    {
        return ($this->calls[$call->id] ?? null)?->result() ?? RegistryResult::idle();
    }

    public function stop(RegistryCall $call): void
    {
        $conversation = $this->calls[$call->id] ?? null;

        if ($conversation === null) {
            return;
        }

        $this->detach($conversation);
        $conversation->close();
        unset($this->calls[$call->id]);
    }

    /**
     * Posunięcie rozmów o tyle, ile da się bez czekania — **raz na takt**.
     *
     * `curl_multi_select()` nie pada tu ani razu, tą samą decyzją, co przy
     * demonie: pytanie o gotowość deskryptorów kosztuje tyle samo, co samo
     * posunięcie transferu.
     */
    public function pump(): void
    {
        $multi = $this->multi;

        if ($multi === null) {
            return;
        }

        $running = 0;

        do {
            $status = curl_multi_exec($multi, $running);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        while (($message = curl_multi_info_read($multi)) !== false) {
            $this->settle($message);
        }
    }

    public function shutdown(): void
    {
        foreach ($this->calls as $conversation) {
            $this->detach($conversation);
            $conversation->close();
        }

        $this->calls = [];

        if ($this->multi !== null) {
            curl_multi_close($this->multi);
            $this->multi = null;
        }
    }

    private function begin(string $path): RegistryCall
    {
        $call = new RegistryCall(++$this->lastId);

        if (!extension_loaded('curl')) {
            $this->calls[$call->id] = RegistryConversation::refused('module.docker.registry.unsupported');

            return $call;
        }

        if ($this->endpoint->address === '') {
            $this->calls[$call->id] = RegistryConversation::refused('module.docker.registry.none');

            return $call;
        }

        $handle = curl_init();

        if (!$handle instanceof CurlHandle) {
            $this->calls[$call->id] = RegistryConversation::refused('module.docker.registry.unreachable');

            return $call;
        }

        $conversation = new RegistryConversation($handle, $path, self::MAX_BUFFER_BYTES);
        $this->attach($handle, $conversation, $this->endpoint->baseUrl() . $path, null);
        $this->calls[$call->id] = $conversation;

        return $call;
    }

    /**
     * Kolejny obieg tej samej rozmowy.
     *
     * Uchwyt `curl`a powstaje **nowy**, a nie jest przestawiany: `curl_setopt`
     * na uchwycie zdjętym z `curl_multi` bywa źródłem stanu, którego nikt nie
     * zamawiał, a koszt nowego uchwytu jest przy żądaniu sieciowym niewidoczny.
     */
    private function nextLeg(RegistryConversation $conversation): void
    {
        $handle = curl_init();

        if (!$handle instanceof CurlHandle) {
            return;
        }

        if ($conversation->stage() === RegistryStage::Asking) {
            $challenge = $conversation->challenge();

            if ($challenge === null) {
                return;
            }

            $conversation->nextLeg($handle, RegistryStage::Authenticating);
            $this->attach($handle, $conversation, $challenge->tokenUrl(), null, basic: true);

            return;
        }

        $token = self::tokenFrom($conversation->bodyNow());

        if ($token === '') {
            $conversation->finish(401, 0);

            return;
        }

        $conversation->nextLeg($handle, RegistryStage::Retrying, $token);
        $this->attach($handle, $conversation, $this->endpoint->baseUrl() . $conversation->path, $token);
    }

    /** @param non-empty-string $url */
    private function attach(
        CurlHandle $handle,
        RegistryConversation $conversation,
        string $url,
        ?string $token,
        bool $basic = false,
    ): void {
        $multi = $this->multi ??= curl_multi_init();
        $headers = ['Accept: application/json'];

        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CONNECTTIMEOUT_MS => self::CONNECT_TIMEOUT_MS,
            CURLOPT_TIMEOUT => self::CALL_TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_WRITEFUNCTION => static fn (CurlHandle $ignored, string $chunk): int
                => $conversation->collect($chunk),
            // Bez tego drugi obieg nie miałby dokąd pytać: wyzwanie stoi
            // w **nagłówku**, a nie w treści.
            CURLOPT_HEADERFUNCTION => static fn (CurlHandle $ignored, string $header): int
                => $conversation->collectHeader($header),
        ];

        if ($basic && $this->endpoint->hasCredentials()) {
            // Poświadczenie idzie **nagłówkiem**, nigdy wierszem polecenia —
            // ten zakaz pochodzi z kroku 48 i jest w kroku 61 twardy (D107 nr 1).
            $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $options[CURLOPT_USERPWD] = $this->endpoint->user . ':' . $this->endpoint->token;
        }

        curl_setopt_array($handle, $options);
        curl_multi_add_handle($multi, $handle);
    }

    /** @param array<array-key, mixed> $message wpis z `curl_multi_info_read()` */
    private function settle(array $message): void
    {
        $handle = $message['handle'] ?? null;
        $result = $message['result'] ?? CURLE_OK;

        if (!$handle instanceof CurlHandle) {
            return;
        }

        // `curl_getinfo()` z jedną opcją oddaje wartość tej opcji — dla kodu
        // odpowiedzi zawsze liczbę, także wtedy, gdy odpowiedzi nie było (zero).
        // Wpis z `curl_multi_info_read()` jest za to zwykłą tablicą i jego pola
        // trzeba sprawdzić — ta sama para zdań, co w `DockerApiService`.
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlResult = is_int($result) ? $result : CURLE_OK;

        foreach ($this->calls as $conversation) {
            if (!$conversation->owns($handle)) {
                continue;
            }

            $this->detachHandle($handle);

            if ($conversation->finish($status, $curlResult)) {
                $this->nextLeg($conversation);
            }

            return;
        }

        $this->detachHandle($handle);
    }

    /**
     * Token z odpowiedzi obiegu drugiego.
     *
     * Rejestry nie zgadzają się co do nazwy pola: specyfikacja Dockera mówi
     * `token`, a OCI dopuszcza `access_token` — i GHCR używa **obu**, zależnie
     * od zakresu. Czytamy więc oba, zamiast zakładać jeden.
     */
    private static function tokenFrom(string $body): string
    {
        try {
            $decoded = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return '';
        }

        if (!is_array($decoded)) {
            return '';
        }

        foreach (['token', 'access_token'] as $field) {
            $value = $decoded[$field] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function detach(RegistryConversation $conversation): void
    {
        $handle = $conversation->handle();

        if ($handle !== null) {
            $this->detachHandle($handle);
        }
    }

    private function detachHandle(CurlHandle $handle): void
    {
        if ($this->multi !== null) {
            curl_multi_remove_handle($this->multi, $handle);
        }

        curl_close($handle);
    }
}
