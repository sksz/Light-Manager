<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Module\Docker\Application\Port\BuildReaderPort;
use LightManager\Module\Docker\Application\Port\DockerApiPort;
use LightManager\Module\Docker\Domain\Exception\InvalidImageRefException;
use LightManager\Module\Docker\Domain\ValueObject\ImageRef;

/**
 * Pobranie obrazu z rejestru — **kształtem bliźniacze do `PushWork`** (krok 61,
 * etap 3).
 *
 * Ta sama droga posuwania (**takt modułu, nie okno** — D94), ten sam rachunek
 * postępu (strumień zdań o warstwach, bez mianownika, bo demon nie mówi, ile
 * warstw zostało) i ten sam czytnik strumienia, bo demon odpowiada tu tym samym
 * formatem, co przy budowie i wypchnięciu.
 *
 * **Dwie rzeczy różnią je naprawdę.** Pobranie jest **krótsze o etap**: nie ma
 * co oznaczać, bo nazwa przychodzi z rejestru. I idzie **innym zasobem**:
 * `POST /images/create?fromImage=…&tag=…`, gdzie nazwa i etykieta stoją
 * **osobno w zapytaniu** — dokładnie tak, jak przy `push`, i z tego samego
 * powodu, dla którego rozdziela je `split()`: port rejestru
 * (`localhost:5000/…`) ma dwukropek w części adresowej i nie jest etykietą.
 */
final class PullWork
{
    private const DEFAULT_TAG = 'latest';

    private PullStage $stage = PullStage::Idle;

    private ?ImageRef $target = null;

    private ?DockerCall $call = null;

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
     * Zaczyna pobieranie obrazu o wskazanej nazwie.
     *
     * Poświadczenia przychodzą **gotowym nagłówkiem**, jak przy wypchnięciu:
     * składanie `X-Registry-Auth` należy do `Infrastructure`, a ta klasa jest
     * warstwą aplikacji i nie ma prawa znać kodowania cudzego protokołu.
     */
    public function begin(string $image, string $registryAuth): void
    {
        $this->stop();

        try {
            $this->target = ImageRef::of($image);
        } catch (InvalidImageRefException $exception) {
            $this->fail($exception->problemKey(), $exception->problemParameters());

            return;
        }

        [$name, $tag] = self::split($this->target->value);
        $this->stage = PullStage::Pulling;
        $this->note = '';
        $this->call = $this->api->push(
            '/images/create?fromImage=' . rawurlencode($name) . '&tag=' . rawurlencode($tag),
            $registryAuth,
        );
    }

    /** Posunięcie o takt — jedno na klatkę, wołane przez takt modułu. */
    public function tick(): void
    {
        if ($this->stage !== PullStage::Pulling || $this->call === null) {
            return;
        }

        $result = $this->api->poll($this->call);

        foreach ($this->reader->push($result->body) as $message) {
            match ($message->kind) {
                BuildMessageKind::Step => $this->note = $message->text,
                BuildMessageKind::Built => null,
                // **Niepowodzenie przychodzi w treści, nie w kodzie odpowiedzi** —
                // ta sama pułapka, co przy budowie (11t): nieudane pobranie
                // kończy się HTTP 200.
                BuildMessageKind::Failure => $this->fail('module.docker.pull.rejected', [
                    'reason' => $message->text,
                ]),
            };
        }

        $call = $this->call;

        if ($this->stage !== PullStage::Pulling || $result->isRunning() || $call === null) {
            return;
        }

        $this->call = null;
        $this->api->stop($call);

        if (!$result->isDone() || !$result->isSuccessful()) {
            $this->fail($result->problemKey ?? 'module.docker.pull.failed', $result->problemParameters);

            return;
        }

        $this->stage = PullStage::Done;
    }

    public function stage(): PullStage
    {
        return $this->stage;
    }

    public function isWorking(): bool
    {
        return $this->stage === PullStage::Pulling;
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

    /** Czy wynik czeka na zgłoszenie — **odbierany raz**, wzorem `PushWork`. */
    public function takeFinished(): ?PullStage
    {
        if ($this->reported || $this->isWorking() || $this->stage === PullStage::Idle) {
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

        $this->stage = PullStage::Idle;
        $this->note = '';
        $this->problemKey = null;
        $this->problemParameters = [];
        $this->reported = false;
        $this->target = null;
    }

    /**
     * Nazwa i etykieta rozdzielone — **powtórzenie rachunku z `PushWork`
     * i powtórzenie świadome**.
     *
     * Wyniesienie go do wspólnego miejsca rozważano i odłożono: dziesięć linii
     * bez skutków ubocznych wolno powtórzyć (precedens `permissionsAsText()`
     * z 15b), a **trzeci** odbiorca uruchomi przegląd, nie drugi.
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

        $this->stage = PullStage::Failed;
        $this->problemKey = $problemKey;
        $this->problemParameters = $parameters;
    }
}
