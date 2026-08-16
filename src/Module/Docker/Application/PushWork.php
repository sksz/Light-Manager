<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Module\Docker\Application\Port\BuildReaderPort;
use LightManager\Module\Docker\Application\Port\DockerApiPort;
use LightManager\Module\Docker\Domain\Exception\InvalidImageRefException;
use LightManager\Module\Docker\Domain\ValueObject\ImageRef;

/**
 * Wypchnięcie obrazu do rejestru (krok 54, D94 nr 1 i 2).
 *
 * **Powód, dla którego ta praca w ogóle istnieje, jest po stronie klastra, a nie
 * Dockera**: obraz zbudowany na demonie hosta **nie jest widoczny dla klastra**,
 * bo minikube ze sterownikiem `docker` prowadzi własnego demona wewnątrz
 * kontenera. Bez rejestru pomiędzy nimi `k8s.deploy-image` kończyłoby się podem
 * w stanie `ImagePullBackOff` — czyli funkcją, która wygląda jak usterka.
 *
 * Bliźniak `BuildWork` z **jedną różnicą i jednym podobieństwem** wartymi
 * zapisania. Różnica: nie ma etapu pakowania, bo obraz leży już u demona — praca
 * jest rozmową demona z rejestrem, w której aplikacja wyłącznie czyta postęp.
 * Podobieństwo, i to ono jest pułapką: **niepowodzenie przychodzi w treści, nie
 * w kodzie odpowiedzi**. Odmowa rejestru (zły token, brak uprawnienia
 * `write:packages`, nieistniejąca przestrzeń nazw) kończy się HTTP 200 i obiektem
 * `error` w strumieniu — dokładnie tak, jak nieudana budowa (11t).
 *
 * Postęp czyta **ten sam `BuildReaderPort`**, co budowa, i nie jest to
 * oszczędność na siłę: demon nadaje jedno i drugie tym samym strumieniem obiektów
 * JSON, z polem `status` zamiast `stream`. Drugi czytnik byłby drugim miejscem do
 * poprawienia przy pierwszej zmianie formatu.
 */
final class PushWork
{
    /**
     * Etykieta domyślna — ta sama, którą dokłada demon.
     *
     * Nazwa bez etykiety znaczy `:latest` w całym ekosystemie, a zasób
     * wypychania **żąda etykiety osobno**, więc trzeba ją tu podać wprost.
     */
    private const DEFAULT_TAG = 'latest';

    private PushStage $stage = PushStage::Idle;

    private ?DockerCall $call = null;

    /** Nazwa, pod którą obraz idzie do rejestru — z rejestrem i etykietą. */
    private ?ImageRef $target = null;

    /** Nazwa lokalna obrazu — ta, którą trzeba najpierw oznaczyć docelową. */
    private ?ImageRef $source = null;

    /** Poświadczenia na czas pracy: oznaczanie ich nie potrzebuje, wypychanie tak. */
    private string $auth = '';

    /** Ostatnie zdanie rozmowy — to, co widać w oknie pracy. */
    private string $note = '';

    private ?string $problemKey = null;

    /** @var array<string, string|int|float> */
    private array $problemParameters = [];

    private bool $reported = false;

    public function __construct(
        private readonly DockerApiPort $api,
        private readonly BuildReaderPort $reader,
    ) {
    }

    /**
     * Zaczyna wypychanie obrazu pod wskazaną nazwą.
     *
     * Nazwa przechodzi przez obiekt wartości **przed** wywołaniem, bo wchodzi do
     * ścieżki żądania. Poświadczenia przychodzą gotowe — składanie nagłówka
     * należy do `Infrastructure` (`RegistryAuth`), a ta klasa jest warstwą
     * aplikacji i nie ma prawa znać kodowania cudzego protokołu.
     */
    public function begin(string $source, string $target, string $registryAuth): void
    {
        $this->stop();

        try {
            $this->source = ImageRef::of($source);
            $this->target = ImageRef::of($target);
        } catch (InvalidImageRefException $exception) {
            $this->fail($exception->problemKey(), $exception->problemParameters());

            return;
        }

        $this->auth = $registryAuth;
        $this->stage = PushStage::Tagging;
        $this->note = '';

        // **Oznaczenie musi paść przed wypchnięciem** — wyszło z próby na żywym
        // demonie, a nie z dokumentacji: `push` odmawia nazwie, której obraz
        // lokalnie nie nosi, i robi to z kodem HTTP 200. Nazwa i etykieta idą
        // **osobno**, bo tego żąda zasób.
        [$name, $tag] = self::split($this->target->value);
        $this->call = $this->api->post(
            '/images/' . rawurlencode($this->source->value) . '/tag'
                . '?repo=' . rawurlencode($name) . '&tag=' . rawurlencode($tag),
        );
    }

    /** Posunięcie o takt — jedno na klatkę, wołane przez takt modułu (D94 nr 5). */
    public function tick(): void
    {
        match ($this->stage) {
            PushStage::Tagging => $this->advanceTagging(),
            PushStage::Pushing => $this->advancePushing(),
            default => null,
        };
    }

    /**
     * Oznaczanie: krótkie wywołanie, po którym rusza wypychanie.
     *
     * Demon odsyła `201` przy powodzeniu i `404`, gdy obrazu źródłowego nie ma —
     * a to drugie jest zwykłym stanem, bo obraz mógł zniknąć między wybraniem go
     * z listy a naciśnięciem klawisza.
     */
    private function advanceTagging(): void
    {
        $call = $this->call;

        if ($call === null) {
            return;
        }

        $result = $this->api->poll($call);

        if ($result->isRunning()) {
            return;
        }

        $this->call = null;
        $this->api->stop($call);

        if (!$result->isDone() || !$result->isSuccessful()) {
            $this->fail('module.docker.push.notTagged', [
                'source' => $this->source->value ?? '',
                'target' => $this->target->value ?? '',
            ]);

            return;
        }

        [$name, $tag] = self::split($this->target->value ?? '');

        $this->stage = PushStage::Pushing;
        $this->call = $this->api->push(
            '/images/' . rawurlencode($name) . '/push?tag=' . rawurlencode($tag),
            $this->auth,
        );
    }

    private function advancePushing(): void
    {
        if ($this->call === null) {
            return;
        }

        $result = $this->api->poll($this->call);

        foreach ($this->reader->push($result->body) as $message) {
            match ($message->kind) {
                BuildMessageKind::Step => $this->note = $message->text,
                BuildMessageKind::Built => null,
                BuildMessageKind::Failure => $this->fail('module.docker.push.rejected', [
                    'reason' => $message->text,
                ]),
            };
        }

        $call = $this->call;

        if ($this->stage !== PushStage::Pushing || $result->isRunning() || $call === null) {
            return;
        }

        $this->call = null;
        $this->api->stop($call);

        if (!$result->isDone() || !$result->isSuccessful()) {
            $this->fail($result->problemKey ?? 'module.docker.push.failed', $result->problemParameters);

            return;
        }

        $this->stage = PushStage::Done;
    }

    public function stage(): PushStage
    {
        return $this->stage;
    }

    public function isWorking(): bool
    {
        return $this->stage === PushStage::Tagging || $this->stage === PushStage::Pushing;
    }

    public function note(): string
    {
        return $this->note;
    }

    public function target(): ?ImageRef
    {
        return $this->target;
    }

    public function problemKey(): ?string
    {
        return $this->problemKey;
    }

    /** @return array<string, string|int|float> */
    public function problemParameters(): array
    {
        return $this->problemParameters;
    }

    /** Czy wynik czeka na zgłoszenie — **odbierany raz**, wzorem `BuildWork`. */
    public function takeFinished(): ?PushStage
    {
        if ($this->reported || $this->isWorking() || $this->stage === PushStage::Idle) {
            return null;
        }

        $this->reported = true;

        return $this->stage;
    }

    public function stop(): void
    {
        if ($this->call !== null) {
            $this->api->stop($this->call);
            $this->call = null;
        }

        $this->stage = PushStage::Idle;
        $this->note = '';
        $this->problemKey = null;
        $this->problemParameters = [];
        $this->reported = false;
        $this->target = null;
        $this->source = null;
        $this->auth = '';
    }

    /**
     * Nazwa i etykieta rozdzielone — **po ostatnim dwukropku, ale nie zawsze**.
     *
     * Pułapka jest w porcie rejestru: `localhost:5000/lm/proba` ma dwukropek
     * w części adresowej i **nie ma etykiety**, więc podział po ostatnim
     * dwukropku dałby nazwę `localhost` i etykietę `5000/lm/proba`. Rozstrzyga
     * ukośnik: dwukropek stojący **przed** ostatnim ukośnikiem należy do adresu,
     * nie do etykiety.
     *
     * @return array{string, string}
     */
    private static function split(string $reference): array
    {
        $colon = strrpos($reference, ':');
        $slash = strrpos($reference, '/');

        if ($colon === false || ($slash !== false && $colon < $slash)) {
            return [$reference, self::DEFAULT_TAG];
        }

        return [substr($reference, 0, $colon), substr($reference, $colon + 1)];
    }

    /** @param array<string, string|int|float> $parameters */
    private function fail(string $problemKey, array $parameters = []): void
    {
        if ($this->call !== null) {
            $this->api->stop($this->call);
            $this->call = null;
        }

        $this->stage = PushStage::Failed;
        $this->problemKey = $problemKey;
        $this->problemParameters = $parameters;
    }
}
