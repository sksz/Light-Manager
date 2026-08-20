<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Infrastructure;

use CurlHandle;
use LightManager\Module\Docker\Application\Registry\RegistryChallenge;
use LightManager\Module\Docker\Application\Registry\RegistryResult;
use LightManager\Module\Docker\Application\Registry\RegistryStage;

/**
 * Jedna rozmowa z rejestrem wraz z jej **maszyną stanu na trzy obiegi**
 * (krok 61, etap 2).
 *
 * Różni się od `DockerConversation` dwiema rzeczami i obie biorą się z tego, że
 * rozmówcą jest rejestr, a nie demon.
 *
 * **Pierwsza: zbiera nagłówki, nie tylko treść.** Demonowi wystarczyło
 * `CURLOPT_WRITEFUNCTION`, bo wszystko, co mówi, mówi w treści. Rejestr stawia
 * wyzwanie **w nagłówku** `WWW-Authenticate`, więc bez `CURLOPT_HEADERFUNCTION`
 * drugi obieg nie miałby dokąd pytać.
 *
 * **Druga: jedna rozmowa to nawet trzy żądania.** `finish()` nie kończy jej
 * z automatu — przy `401` z wyzwaniem mówi wołającemu „zacznij następny obieg”,
 * a stan przechodzi `Asking` → `Authenticating` → `Retrying` → `Done`. Uchwyt
 * `curl`a zmienia się przy tym za każdym razem, bo każdy obieg idzie gdzie
 * indziej i z innym nagłówkiem.
 *
 * **Obieg trzeci pada raz i tylko raz.** Kolejne `401` — już z tokenem — znaczy
 * **złe poświadczenia** i ma własne zdanie; ponawianie w kółko byłoby pytaniem
 * o to samo trzydzieści razy na sekundę, czyli tym, przed czym broni się krok 59
 * przy wersjach serwera.
 */
final class RegistryConversation
{
    private string $body = '';

    /** @var list<string> */
    private array $headers = [];

    private RegistryStage $stage;

    private ?RegistryChallenge $challenge = null;

    private ?string $token = null;

    private ?RegistryResult $settled = null;

    public function __construct(
        private ?CurlHandle $handle,
        /** Ścieżka pytania właściwego — powtarzana w obiegu trzecim. */
        public readonly string $path,
        private readonly int $maxBytes,
        RegistryStage $stage = RegistryStage::Asking,
    ) {
        $this->stage = $stage;
    }

    /** Rozmowa odrzucona, zanim cokolwiek wyszło — bez uchwytu i bez obiegów. */
    public static function refused(string $problemKey): self
    {
        $conversation = new self(null, '', 0, RegistryStage::Failed);
        $conversation->settled = RegistryResult::failed($problemKey);

        return $conversation;
    }

    public function owns(CurlHandle $handle): bool
    {
        return $this->handle === $handle;
    }

    public function handle(): ?CurlHandle
    {
        return $this->handle;
    }

    public function stage(): RegistryStage
    {
        return $this->stage;
    }

    public function challenge(): ?RegistryChallenge
    {
        return $this->challenge;
    }

    public function token(): ?string
    {
        return $this->token;
    }

    public function collect(string $chunk): int
    {
        $length = strlen($chunk);

        if (strlen($this->body) + $length <= $this->maxBytes) {
            $this->body .= $chunk;
        }

        // Zwracamy **pełną** długość niezależnie od tego, czy porcję zapisano:
        // liczba mniejsza od podanej znaczy dla `curl`a błąd zapisu i zrywa
        // transfer, a my nadmiar chcemy porzucić, nie zerwać rozmowę.
        return $length;
    }

    public function collectHeader(string $header): int
    {
        $this->headers[] = $header;

        return strlen($header);
    }

    public function result(): RegistryResult
    {
        return $this->settled ?? RegistryResult::working($this->stage);
    }

    /**
     * Koniec jednego obiegu.
     *
     * @return bool czy trzeba zacząć następny obieg — i wtedy wołający pyta
     *              `challenge()` albo `token()` o to, dokąd
     */
    public function finish(int $status, int $curlResult): bool
    {
        $this->handle = null;

        if ($curlResult !== 0) {
            $this->settled = RegistryResult::failed('module.docker.registry.unreachable');
            $this->stage = RegistryStage::Failed;

            return false;
        }

        if ($status === 401) {
            return $this->challenged();
        }

        $this->settled = RegistryResult::done($this->body, $status);
        $this->stage = RegistryStage::Done;

        return false;
    }

    /** Przygotowanie kolejnego obiegu: bufory czyste, etap przestawiony. */
    public function nextLeg(CurlHandle $handle, RegistryStage $stage, ?string $token = null): void
    {
        $this->handle = $handle;
        $this->body = '';
        $this->headers = [];
        $this->stage = $stage;

        if ($token !== null) {
            $this->token = $token;
        }
    }

    /** Treść obiegu drugiego — token stąd podpisuje obieg trzeci. */
    public function bodyNow(): string
    {
        return $this->body;
    }

    public function close(): void
    {
        $this->handle = null;
    }

    /**
     * `401` — albo wyzwanie do podjęcia, albo odmowa.
     *
     * Rozstrzyga o tym **etap, na którym stoimy**, a nie sam kod: `401`
     * w pierwszym obiegu jest zaproszeniem po token, `401` po tokenie jest
     * odpowiedzią „te poświadczenia są złe” i ma własne zdanie.
     */
    private function challenged(): bool
    {
        if ($this->stage === RegistryStage::Retrying) {
            $this->settled = RegistryResult::failed('module.docker.registry.denied');
            $this->stage = RegistryStage::Failed;

            return false;
        }

        $challenge = RegistryChallenge::fromHeaders($this->headers);

        if ($challenge === null) {
            $this->settled = RegistryResult::failed('module.docker.registry.denied');
            $this->stage = RegistryStage::Failed;

            return false;
        }

        $this->challenge = $challenge;

        return true;
    }
}
