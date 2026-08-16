<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation;

use LightManager\Application\Dto\WorkProgress;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\PushStage;
use LightManager\Module\Docker\Application\PushWork;
use LightManager\Module\Docker\Infrastructure\RegistryAuth;
use LightManager\Presentation\Ui\Overlay\ProgressOverlay;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\ScreenOutcome;

/**
 * Łańcuch okien wypychania: nazwa docelowa → postęp (krok 54).
 *
 * Krótszy od `BuildFlow` o jedno ogniwo, bo katalogu tu nie ma — obraz leży już
 * u demona. Istnieje z tego samego powodu, co tamten (11n): czynność ma **dwa
 * wejścia**, klawisz na liście obrazów i komendę `docker.push`, a dwie kopie
 * kolejności okien rozjechałyby się przy pierwszej poprawce.
 *
 * **Nazwa docelowa proponuje się sama i to jest tu najważniejsza rzecz dla
 * użytkownika.** Obraz zbudowany lokalnie nazywa się `lm/proba:1`, a do rejestru
 * musi pójść jako `ghcr.io/sksz/lm/proba:1` — bez podpowiedzi każde wypchnięcie
 * zaczynałoby się od przypominania sobie, jak brzmi własna przestrzeń nazw.
 * Propozycja **nie rozstrzyga**: wpisana nazwa idzie taka, jaka jest.
 */
final class PushFlow
{
    public function __construct(
        private readonly PushWork $work,
        private readonly TranslatorPort $translator,
        private readonly SettingsPort $settings,
    ) {
    }

    /** Klawisz na liście obrazów. */
    public function start(string $tag): ScreenOutcome
    {
        if ($this->work->isWorking()) {
            return ScreenOutcome::stay(Message::warning($this->text('push.busy')));
        }

        if ($tag === '') {
            return ScreenOutcome::stay(Message::warning($this->text('push.noImage')));
        }

        return ScreenOutcome::opens($this->targetPrompt($tag));
    }

    /** To samo w postaci zrozumiałej dla komendy (`OpensOverlay`, krok 47). */
    public function request(string $tag): OverlayOutcome
    {
        if ($this->work->isWorking()) {
            return OverlayOutcome::close(Message::warning($this->text('push.busy')));
        }

        if ($tag === '') {
            return OverlayOutcome::close(Message::warning($this->text('push.noImage')));
        }

        return OverlayOutcome::replace($this->targetPrompt($tag));
    }

    /**
     * Wypchnięcie **bez pytania** — droga dla czynności prowadzonej przez inny
     * moduł (`k8s.deploy-image`).
     *
     * Okna tu nie ma, bo tamta czynność ma **własne** i prowadzi je od pierwszego
     * etapu; drugie okno nie miałoby gdzie stanąć (stos ma jedno piętro). Postęp
     * ogląda się kwerendą `docker.push`, dokładnie tak, jak postęp budowy ogląda
     * się przez `docker.build`.
     */
    public function begin(string $source, string $target): void
    {
        $this->work->begin($source, $target, $this->auth());
    }

    /**
     * Nazwa, którą podpowiadamy: rejestr, użytkownik i nazwa obrazu bez rejestru.
     *
     * Obraz, który **już ma rejestr w nazwie**, zostaje bez zmian — podwójny
     * przedrostek (`ghcr.io/sksz/ghcr.io/…`) byłby nazwą, której rejestr nie
     * przyjmie, a użytkownik zauważy dopiero po odmowie.
     */
    public function suggest(string $tag): string
    {
        $settings = $this->settings->current();
        $registry = DockerSettings::registryFrom($settings);
        $user = DockerSettings::registryUserFrom($settings);

        if ($tag === '' || str_starts_with($tag, $registry . '/')) {
            return $tag;
        }

        return $user === '' ? $registry . '/' . $tag : $registry . '/' . $user . '/' . $tag;
    }

    /** Posunięcie pracy o takt — wołane przez takt modułu, jak budowa (D94 nr 5). */
    public function advance(): void
    {
        $this->work->tick();
    }

    private function auth(): string
    {
        $settings = $this->settings->current();

        return RegistryAuth::header(
            DockerSettings::registryFrom($settings),
            DockerSettings::registryUserFrom($settings),
            DockerSettings::registryTokenFrom($settings),
        );
    }

    private function targetPrompt(string $tag): PromptOverlay
    {
        return new PromptOverlay(
            'module.' . DockerSettings::ID . '.push.target',
            [],
            $this->suggest($tag),
            fn (string $value): OverlayOutcome => OverlayOutcome::replace($this->progress($tag, $value)),
            $this->translator,
        );
    }

    private function progress(string $source, string $target): ProgressOverlay
    {
        $this->begin($source, $target);

        return new ProgressOverlay(
            'module.' . DockerSettings::ID . '.push.title',
            ['tag' => $target],
            $this->progressOf(),
            fn (): WorkProgress => $this->progressOf(),
            fn (WorkProgress $progress): OverlayOutcome => $this->finish(),
            fn (WorkProgress $progress): Message => $this->cancel(),
            $this->translator,
        );
    }

    /**
     * Postęp **bez mianownika i to jest jedyne uczciwe wyjście**.
     *
     * Demon podaje postęp osobno dla każdej warstwy i nie mówi, ile ich zostało,
     * więc suma bajtów nie istnieje jako liczba, którą dałoby się pokazać. Pasek
     * idzie w trybie „postęp nieznany" (krok 23), a zdaniem jest ostatni wiersz
     * rozmowy — czyli to, co naprawdę wiadomo.
     */
    private function progressOf(): WorkProgress
    {
        return new WorkProgress(
            $this->work->isWorking(),
            $this->work->note(),
            0,
            null,
            $this->text('push.stage'),
        );
    }

    private function finish(): OverlayOutcome
    {
        $reference = $this->work->target();
        $target = $reference === null ? '' : $reference->value;

        if ($this->work->stage() === PushStage::Done) {
            return OverlayOutcome::close(Message::info($this->text('push.done', ['tag' => $target])));
        }

        return OverlayOutcome::close(Message::error($this->translator->translate(
            $this->work->problemKey() ?? 'module.' . DockerSettings::ID . '.push.failed',
            $this->work->problemParameters(),
        )));
    }

    private function cancel(): Message
    {
        $this->work->stop();

        return Message::warning($this->text('push.cancelled'));
    }

    /** @param array<string, string|int|float> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate('module.' . DockerSettings::ID . '.' . $key, $parameters);
    }
}
