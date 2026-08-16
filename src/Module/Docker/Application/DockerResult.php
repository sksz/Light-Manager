<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/**
 * Stan rozmowy z demonem w tej chwili — dana oglądana co klatkę (krok 51).
 *
 * **Treść znaczy dwie różne rzeczy i różnicę wyznacza rodzaj pytania**, nie
 * pole. Pytanie zwykłe (lista, czynność) oddaje treść **w całości i raz**, przy
 * etapie `Done`. Pytanie płynące (logi, postęp budowy) oddaje przy każdym
 * zajrzeniu **to, co doszło od poprzedniego** — i oddaje to tylko raz, bo
 * odczytane bajty znikają z bufora.
 *
 * Rozwiązanie z osobnym polem `chunk` obok `body` było tu rozważone i odrzucone:
 * przy pytaniu zwykłym byłoby zawsze puste, a przy płynącym zawsze pełne, więc
 * niosłoby wyłącznie powtórzenie tego, co i tak wiadomo z pytania.
 *
 * **Kod stanu HTTP nie jest sam z siebie niepowodzeniem** — dokładnie jak kod
 * wyjścia procesu w kroku 26. `304` przy uruchamianiu kontenera znaczy „już
 * działa”, a `404` przy usuwaniu — „już go nie ma”; co z tego wynika,
 * rozstrzyga ten, kto pytał.
 */
final readonly class DockerResult
{
    /** @param array<string, string|int|float> $problemParameters */
    private function __construct(
        public DockerStage $stage,
        /** Treść odpowiedzi albo porcja strumienia — patrz opis klasy. */
        public string $body,
        /** Kod stanu HTTP; `null` wszędzie poza `Done` i `Running` strumienia. */
        public ?int $status,
        /** Klucz katalogu z powodem — wyłącznie przy `Failed`. */
        public ?string $problemKey,
        public array $problemParameters,
    ) {
    }

    public static function idle(): self
    {
        return new self(DockerStage::Idle, '', null, null, []);
    }

    public static function running(string $body = '', ?int $status = null): self
    {
        return new self(DockerStage::Running, $body, $status, null, []);
    }

    public static function done(string $body, int $status): self
    {
        return new self(DockerStage::Done, $body, $status, null, []);
    }

    /** @param array<string, string|int|float> $parameters */
    public static function failed(string $problemKey, array $parameters = []): self
    {
        return new self(DockerStage::Failed, '', null, $problemKey, $parameters);
    }

    public function isRunning(): bool
    {
        return $this->stage === DockerStage::Running;
    }

    public function isDone(): bool
    {
        return $this->stage === DockerStage::Done;
    }

    /** Czy odpowiedź doszła i demon uznał pytanie za załatwione (2xx). */
    public function isSuccessful(): bool
    {
        return $this->stage === DockerStage::Done && $this->status !== null
            && $this->status >= 200 && $this->status < 300;
    }
}
