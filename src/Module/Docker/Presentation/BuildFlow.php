<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation;

use LightManager\Application\Dto\WorkProgress;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Docker\Application\BuildStage;
use LightManager\Module\Docker\Application\BuildWork;
use LightManager\Module\Docker\Application\DockerEvent;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\ImageList;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Overlay\ProgressOverlay;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\ScreenOutcome;

/**
 * Łańcuch okien budowy: katalog → nazwa → postęp (krok 51).
 *
 * Klasa jest odpowiednikiem `ConnectFlow` z kroku 48 i istnieje z tego samego
 * powodu: łańcuch dzielą **dwa wejścia** — klawisz `F7` na ekranie i komenda
 * `docker.build` — a dwie kopie tej samej kolejności okien rozjechałyby się przy
 * pierwszej poprawce (reguła 11n).
 *
 * Okna ustępują sobie miejsca przez `OverlayOutcome::replace()`: stos ma jedno
 * piętro, więc „zamknij i otwórz” musi stać się naraz (krok 41, D75). Ostatnie
 * ogniwo jest oknem pracy (`RunsWork`), więc pyta o kawałek raz na takt
 * i **zamyka się samo**, kiedy budowa się skończy.
 *
 * **Zdarzenia ogłasza to miejsce** — `docker.build.finished` i
 * `docker.build.failed`. Krok 53 oprze na nich współpracę modułów, bo budowa
 * trwa minutami: moduł, który ją zamówił, nie ma jak czekać w klatce, więc
 * dowie się zdarzeniem, a po wynik sięgnie kwerendą.
 */
final class BuildFlow
{
    public function __construct(
        private readonly BuildWork $work,
        private readonly ImageList $images,
        private readonly TranslatorPort $translator,
        private readonly LoopState $state,
    ) {
    }

    /**
     * Pierwsze ogniwo: pytanie o katalog, z kontekstem wpisanym wstępnie.
     *
     * Kontekst **proponuje, a nie rozstrzyga** (D90 nr 5): `Dockerfile` leży
     * zwykle w katalogu, w którym stoi użytkownik przeglądarki, ale budowa
     * cudzego projektu jest tak samo dobrym powodem, żeby tu być.
     */
    public function start(string $directory): ScreenOutcome
    {
        if ($this->work->isWorking()) {
            return ScreenOutcome::stay(Message::warning($this->text('build.busy')));
        }

        return ScreenOutcome::opens($this->directoryPrompt($directory));
    }

    /**
     * To samo, czego chce klawisz, w postaci zrozumiałej dla **komendy**
     * (`OpensOverlay`, krok 47).
     *
     * Dwie postacie tej samej czynności, bo dwa wejścia mówią o skutku dwoma
     * różnymi typami: ekran oddaje `ScreenOutcome`, a komenda `OverlayOutcome`.
     * Łańcuch okien zostaje przy tym **jeden** — i to jest cały powód, dla którego
     * ta klasa istnieje (reguła 11n).
     */
    public function request(string $directory): OverlayOutcome
    {
        if ($this->work->isWorking()) {
            return OverlayOutcome::close(Message::warning($this->text('build.busy')));
        }

        return OverlayOutcome::replace($this->directoryPrompt($directory));
    }

    private function directoryPrompt(string $directory): PromptOverlay
    {
        return new PromptOverlay(
            'module.' . DockerSettings::ID . '.build.directory',
            [],
            $directory,
            fn (string $value): OverlayOutcome => OverlayOutcome::replace($this->tagPrompt($value)),
            $this->translator,
            promptKey: 'prompt.path',
        );
    }

    /**
     * Drugie ogniwo: nazwa obrazu.
     *
     * Nazwy **nie proponujemy z nazwy katalogu** i jest to świadome: obraz
     * nazwany po katalogu roboczym („src”, „app”) zaśmieciłby spis obrazów
     * nazwami, których za tydzień nikt nie rozpozna. Puste pole każe wpisać nazwę
     * — a wpisana nazwa jest jedyną rzeczą, po której obraz się potem znajdzie.
     */
    private function tagPrompt(string $directory): PromptOverlay
    {
        return new PromptOverlay(
            'module.' . DockerSettings::ID . '.build.tag',
            [],
            '',
            fn (string $tag): OverlayOutcome => OverlayOutcome::replace($this->progress($directory, $tag)),
            $this->translator,
        );
    }

    /** Trzecie ogniwo: okno pracy, które posuwa budowę i zamyka się samo. */
    private function progress(string $directory, string $tag): ProgressOverlay
    {
        $this->work->begin($directory, $tag);

        return new ProgressOverlay(
            'module.' . DockerSettings::ID . '.build.title',
            ['tag' => $tag],
            $this->progressOf(),
            fn (): WorkProgress => $this->step(),
            fn (WorkProgress $progress): OverlayOutcome => $this->finish(),
            fn (WorkProgress $progress): Message => $this->cancel(),
            $this->translator,
        );
    }

    /** Kawałek pracy na takt — pakowanie albo czytanie strumienia budowy. */
    private function step(): WorkProgress
    {
        $this->work->tick();

        return $this->progressOf();
    }

    private function progressOf(): WorkProgress
    {
        $fraction = $this->work->fraction();
        $total = $fraction === null ? null : 1000;

        return new WorkProgress(
            $this->work->isWorking(),
            $this->work->note(),
            $total === null ? 0 : (int) round($fraction * $total),
            $total,
            $total === null ? $this->stageLabel() : '',
        );
    }

    /** Zdanie o etapie — przy budowie, gdzie ułamka nie ma i mieć nie może. */
    private function stageLabel(): string
    {
        return $this->text('build.stage.' . ($this->work->stage() === BuildStage::Building ? 'building' : 'packing'));
    }

    /**
     * Koniec pracy: zdanie do paska stanu i zdarzenie.
     *
     * Lista obrazów odświeża się **po udanej budowie** i jest to jedyna droga,
     * którą nowy obraz pojawi się na ekranie bez czekania na zegar.
     */
    private function finish(): OverlayOutcome
    {
        $stage = $this->work->takeFinished();
        $reference = $this->work->tag();
        $tag = $reference === null ? '' : $reference->value;

        if ($stage === BuildStage::Done) {
            $this->state->events()->publish(DockerEvent::BuildFinished->value);
            $this->images->refresh();

            return OverlayOutcome::close(Message::info($this->text('build.done', ['tag' => $tag])));
        }

        $this->state->events()->publish(DockerEvent::BuildFailed->value);

        return OverlayOutcome::close(Message::error($this->translator->translate(
            $this->work->problemKey() ?? 'module.' . DockerSettings::ID . '.build.failed',
            $this->work->problemParameters(),
        )));
    }

    /**
     * Przerwanie: praca staje, a archiwum tymczasowe znika.
     *
     * Zdarzenie idzie **to samo, co przy niepowodzeniu**, i jest to zgodne
     * z granicą z kroku 50: przerwanie przez użytkownika jest niepowodzeniem
     * pracy, a nie osobnym rodzajem zdarzenia.
     */
    private function cancel(): Message
    {
        $this->work->stop();
        $this->state->events()->publish(DockerEvent::BuildFailed->value);

        return Message::warning($this->text('build.cancelled'));
    }

    /** @param array<string, string|int|float> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate('module.' . DockerSettings::ID . '.' . $key, $parameters);
    }
}
