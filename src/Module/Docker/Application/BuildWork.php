<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Module\Docker\Application\Port\BuildContextPort;
use LightManager\Module\Docker\Application\Port\BuildReaderPort;
use LightManager\Module\Docker\Application\Port\DockerApiPort;
use LightManager\Module\Docker\Domain\Exception\InvalidImageRefException;
use LightManager\Module\Docker\Domain\ValueObject\ImageRef;

/**
 * Budowa obrazu: spakuj kontekst, wyślij, czytaj postęp (krok 51).
 *
 * Praca ma **dwa etapy o zupełnie różnym koszcie** i to one wyznaczają kształt
 * tej klasy. Pakowanie jest miejscowe i policzalne — wiadomo, ile plików
 * zostało, więc pasek postępu ma mianownik. Budowa po stronie demona
 * policzalna **nie jest**: liczba kroków `Dockerfile`a nie mówi nic o czasie,
 * bo jeden `RUN apt-get` trwa dłużej niż dziesięć `COPY`. Drugi etap pokazuje
 * się więc paskiem w trybie „postęp nieznany” (krok 23) wraz z ostatnim
 * zdaniem, które budowa o sobie powiedziała.
 *
 * **Niepowodzenie budowy przychodzi w treści, a nie w kodzie odpowiedzi**, i to
 * jest pułapka warta zapamiętania: odpowiedź nieudanej budowy ma kod HTTP 200,
 * bo z punktu widzenia protokołu wszystko poszło dobrze. Jedynym miejscem,
 * w którym demon mówi o porażce, jest obiekt `error` w strumieniu.
 *
 * Zdarzenia ogłasza **nie ta klasa, tylko `Presentation`** — tu zostaje wynik do
 * zabrania. Powód jest ten sam, co przy `ActionOutcome`: rejestr zdarzeń mieszka
 * w stanie pętli, którego warstwa aplikacji modułu nie zna.
 */
final class BuildWork
{
    private BuildStage $stage = BuildStage::Idle;

    private ?DockerCall $call = null;

    private ?ImageRef $tag = null;

    private string $directory = '';

    /** Ostatnie zdanie budowy — to, co widać w oknie pracy. */
    private string $note = '';

    /** Skrót zbudowanego obrazu; `null`, dopóki budowa go nie poda. */
    private ?string $imageId = null;

    private ?string $problemKey = null;

    /** @var array<string, string|int|float> */
    private array $problemParameters = [];

    private bool $reported = false;

    public function __construct(
        private readonly DockerApiPort $api,
        private readonly BuildContextPort $context,
        private readonly BuildReaderPort $reader,
    ) {
    }

    /**
     * Zaczyna budowę katalogu pod wskazaną nazwą.
     *
     * Nazwa przechodzi przez obiekt wartości **przed** pakowaniem czegokolwiek:
     * wpisana z ręki wchodzi potem do ścieżki żądania, a spakowanie kontekstu
     * tylko po to, żeby odrzucić nazwę, byłoby pracą wykonaną na darmo.
     */
    public function begin(string $directory, string $tag): void
    {
        $this->stop();

        try {
            $this->tag = ImageRef::of($tag);
        } catch (InvalidImageRefException $exception) {
            $this->fail($exception->problemKey(), $exception->problemParameters());

            return;
        }

        $this->directory = $directory;
        $this->stage = BuildStage::Packing;
        $this->context->begin($directory);
    }

    /** Posunięcie o takt — jedno na klatkę, wołane przez okno pracy. */
    public function tick(): void
    {
        match ($this->stage) {
            BuildStage::Packing => $this->advancePacking(),
            BuildStage::Building => $this->advanceBuilding(),
            default => null,
        };
    }

    public function stage(): BuildStage
    {
        return $this->stage;
    }

    public function isWorking(): bool
    {
        return $this->stage === BuildStage::Packing || $this->stage === BuildStage::Building;
    }

    /** Zdanie do pokazania w oknie pracy — etap albo ostatni wiersz budowy. */
    public function note(): string
    {
        return $this->note;
    }

    /** Ułamek pakowania; `null` przy budowie, bo tam nie ma czego dzielić. */
    public function fraction(): ?float
    {
        return $this->stage === BuildStage::Packing ? $this->context->state()->fraction() : null;
    }

    public function tag(): ?ImageRef
    {
        return $this->tag;
    }

    public function imageId(): ?string
    {
        return $this->imageId;
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

    /**
     * Czy wynik czeka na zgłoszenie — **odbierany raz**.
     *
     * Ten sam wzorzec, co `ActionOutcome::take*`: bez niego zdarzenie „budowa
     * skończona” szłoby do odbiorców trzydzieści razy na sekundę.
     */
    public function takeFinished(): ?BuildStage
    {
        if ($this->reported || $this->isWorking() || $this->stage === BuildStage::Idle) {
            return null;
        }

        $this->reported = true;

        return $this->stage;
    }

    public function stop(): void
    {
        $this->context->stop();

        if ($this->call !== null) {
            $this->api->stop($this->call);
            $this->call = null;
        }

        $this->stage = BuildStage::Idle;
        $this->note = '';
        $this->imageId = null;
        $this->problemKey = null;
        $this->problemParameters = [];
        $this->reported = false;
        $this->tag = null;
    }

    /** Pakowanie: kawałek na takt, a po ostatnim — wysyłka. */
    private function advancePacking(): void
    {
        $this->context->advance();
        $state = $this->context->state();
        $this->note = $state->total > 0 ? $state->done . '/' . $state->total : '';

        if ($state->problemKey !== null) {
            $this->fail($state->problemKey, $state->problemParameters);

            return;
        }

        if (!$state->isPacked() || $state->archivePath === null) {
            return;
        }

        $this->send($state->archivePath);
    }

    /**
     * Wysyła archiwum i przechodzi do czytania postępu.
     *
     * Archiwum czytamy **w całości do pamięci** i jest to jedyne miejsce w tym
     * module, w którym coś takiego robimy. Powód jest po stronie `ext-curl`:
     * `CURLOPT_POSTFIELDS` przyjmuje napis, a wysyłka strumieniem
     * (`CURLOPT_READFUNCTION`) kazałaby portowi znać uchwyt pliku — czyli
     * wpuścić deskryptor do kontraktu, który dziś mówi wyłącznie o bajtach.
     * Granica z `BuildContextPacker` (pół gibibajta) jest zarazem granicą tego
     * odczytu i dlatego stoi tam, gdzie stoi.
     */
    private function send(string $archivePath): void
    {
        $tag = $this->tag;
        $archive = @file_get_contents($archivePath);

        // Plik zniknął między spakowaniem a wysyłką — zdarza się przy sprzątaniu
        // katalogu tymczasowego przez system w środku pracy.
        if ($archive === false || $tag === null) {
            $this->fail('module.docker.build.packFailed', ['reason' => $archivePath]);

            return;
        }

        $this->call = $this->api->post(
            '/build?t=' . rawurlencode($tag->value) . '&rm=1&forcerm=1',
            $archive,
            'application/x-tar',
        );

        // Archiwum poszło do demona; plik przestaje być nasz, ale wciąż zajmuje
        // dysk, więc kasujemy go tu, a nie w porcie kontekstu — ten zapomina
        // o nim właśnie po to.
        $this->context->forget();
        @unlink($archivePath);

        $this->stage = BuildStage::Building;
        $this->note = '';
    }

    /** Budowa: czytamy strumień zdań i szukamy w nim końca. */
    private function advanceBuilding(): void
    {
        if ($this->call === null) {
            return;
        }

        $result = $this->api->poll($this->call);

        foreach ($this->reader->push($result->body) as $message) {
            match ($message->kind) {
                BuildMessageKind::Step => $this->note = $message->text,
                BuildMessageKind::Built => $this->imageId = $message->text,
                BuildMessageKind::Failure => $this->fail('module.docker.build.rejected', [
                    'reason' => $message->text,
                ]),
            };
        }

        $call = $this->call;

        if ($this->stage !== BuildStage::Building || $result->isRunning() || $call === null) {
            return;
        }

        $this->call = null;
        $this->api->stop($call);

        if (!$result->isDone() || !$result->isSuccessful()) {
            $this->fail($result->problemKey ?? 'module.docker.build.failed', $result->problemParameters);

            return;
        }

        $this->stage = BuildStage::Done;
    }

    /** @param array<string, string|int|float> $parameters */
    private function fail(string $problemKey, array $parameters = []): void
    {
        $this->context->stop();

        if ($this->call !== null) {
            $this->api->stop($this->call);
            $this->call = null;
        }

        $this->stage = BuildStage::Failed;
        $this->problemKey = $problemKey;
        $this->problemParameters = $parameters;
    }

    /** Katalog, o który toczy się praca — pokazuje go okno pracy. */
    public function directory(): string
    {
        return $this->directory;
    }
}
