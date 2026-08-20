<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation;

use LightManager\Application\Dto\WorkProgress;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\PullStage;
use LightManager\Module\Docker\Application\PullWork;
use LightManager\Module\Docker\Application\Registries;
use LightManager\Module\Docker\Infrastructure\RegistryAuth;
use LightManager\Presentation\Ui\Overlay\ProgressOverlay;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\ScreenOutcome;

/**
 * Łańcuch okien pobierania: nazwa obrazu → postęp (krok 61, etap 3).
 *
 * Rodzeństwo `PushFlow` i **krótsze od niego o pytanie o rejestr**. Powód jest
 * w treści, nie w oszczędności: przy wypchnięciu rejestr trzeba wybrać, bo nazwa
 * docelowa dopiero powstaje; przy pobraniu rejestr **stoi już w nazwie obrazu**
 * (`ghcr.io/zespol/api:1`), więc pytanie o niego byłoby pytaniem o rzecz,
 * którą użytkownik właśnie wpisał.
 *
 * Poświadczenia dobiera się przez to **po adresie**: bierzemy wpis książki,
 * którego adres jest przedrostkiem nazwy. Obraz z rejestru, którego w książce
 * nie ma, idzie **bez nagłówka** — i to jest poprawne, bo rejestr publiczny
 * przyjmuje odczyt bez logowania, a o odmowie rozstrzyga demon, nie my.
 */
final class PullFlow
{
    public function __construct(
        private readonly PullWork $work,
        private readonly TranslatorPort $translator,
        private readonly Registries $registries,
        private readonly DockerQueries $reader,
    ) {
    }

    /** Klawisz na liście obrazów. */
    public function start(): ScreenOutcome
    {
        if ($this->work->isWorking()) {
            return ScreenOutcome::stay(Message::warning($this->text('pull.busy')));
        }

        return ScreenOutcome::opens($this->namePrompt(''));
    }

    /** To samo w postaci zrozumiałej dla komendy (`OpensOverlay`). */
    public function request(string $image): OverlayOutcome
    {
        if ($this->work->isWorking()) {
            return OverlayOutcome::close(Message::warning($this->text('pull.busy')));
        }

        return OverlayOutcome::replace($this->namePrompt($image));
    }

    /** Pobranie **bez pytania** — droga dla czynności prowadzonej skądinąd. */
    public function begin(string $image): void
    {
        $this->work->begin($image, $this->auth($image));
    }

    /** Posunięcie pracy o takt — wołane przez takt modułu, jak wypchnięcie. */
    public function advance(): void
    {
        $this->work->tick();
    }

    /**
     * Nagłówek dobrany **po adresie zawartym w nazwie obrazu**.
     *
     * Najdłuższy pasujący przedrostek, a nie pierwszy z brzegu: `example.com`
     * i `example.com:5000` to dwa różne rejestry, a nazwa zaczynająca się od
     * drugiego zaczyna się też od pierwszego.
     */
    private function auth(string $image): string
    {
        $best = null;

        foreach ($this->registries->all() as $registry) {
            if (!str_starts_with($image, $registry->address . '/')) {
                continue;
            }

            if ($best === null || strlen($registry->address) > strlen($best->address)) {
                $best = $registry;
            }
        }

        if ($best === null) {
            return '';
        }

        return RegistryAuth::header($best->address, $best->user, $this->reader->registryToken($best->id));
    }

    private function namePrompt(string $image): PromptOverlay
    {
        return new PromptOverlay(
            'module.' . DockerSettings::ID . '.pull.image',
            [],
            $image,
            fn (string $value): OverlayOutcome => trim($value) === ''
                ? OverlayOutcome::close()
                : OverlayOutcome::replace($this->progress(trim($value))),
            $this->translator,
        );
    }

    private function progress(string $image): ProgressOverlay
    {
        $this->begin($image);

        return new ProgressOverlay(
            'module.' . DockerSettings::ID . '.pull.title',
            ['tag' => $image],
            $this->progressOf(),
            fn (): WorkProgress => $this->progressOf(),
            fn (WorkProgress $progress): OverlayOutcome => $this->finish(),
            fn (WorkProgress $progress): Message => $this->cancel(),
            $this->translator,
        );
    }

    /** Postęp **bez mianownika** — demon nie mówi, ile warstw zostało. */
    private function progressOf(): WorkProgress
    {
        return new WorkProgress(
            $this->work->isWorking(),
            $this->work->note(),
            0,
            null,
            $this->text('pull.stage'),
        );
    }

    private function finish(): OverlayOutcome
    {
        $reference = $this->work->target();
        $image = $reference === null ? '' : $reference->value;

        if ($this->work->stage() === PullStage::Done) {
            return OverlayOutcome::close(Message::info($this->text('pull.done', ['tag' => $image])));
        }

        $key = $this->work->problemKey() ?? 'module.' . DockerSettings::ID . '.pull.failed';

        return OverlayOutcome::close(Message::error(
            $this->translator->translate($key, $this->work->problemParameters()),
        ));
    }

    private function cancel(): Message
    {
        $this->work->stop();

        return Message::warning($this->text('pull.cancelled'));
    }

    /** @param array<string, string|int|float> $parameters */
    private function text(string $suffix, array $parameters = []): string
    {
        return $this->translator->translate('module.' . DockerSettings::ID . '.' . $suffix, $parameters);
    }
}
