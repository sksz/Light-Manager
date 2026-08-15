<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation;

use LightManager\Application\Dto\WorkProgress;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Ssh\Application\SessionStage;
use LightManager\Module\Ssh\Application\SshSession;
use LightManager\Module\Ssh\Application\SshSettings;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Presentation\Ui\Overlay\ConfirmOverlay;
use LightManager\Presentation\Ui\Overlay\ProgressOverlay;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * Łańcuch okien, którym prowadzi się połączenie (krok 48).
 *
 * **Osobna klasa, bo wejścia są dwa**: `Enter` w spisie hostów i komenda
 * `ssh.connect <nazwa>`. Reguła 11n mówi o tym wprost — czynność mająca dwa
 * wejścia mieszka w jednym miejscu, bo dwie implementacje rozjeżdżają się przy
 * pierwszej poprawce. Tutaj rozjechałyby się szczególnie brzydko: jedna
 * pamiętałaby zapytać o odcisk, druga nie.
 *
 * **Łańcuch jest najdłuższy w całej aplikacji** i to jest jedyna trudność tej
 * klasy. Stos okien ma **jedno piętro** (D75), więc każde ogniwo ustępuje
 * następnemu przez `OverlayOutcome::replace()`, a nie otwiera go nad sobą:
 *
 * ```
 * [hasło] → postęp (odcisk) → pytanie o odcisk → postęp (mistrz) → zdanie
 * ```
 *
 * Pierwsze ogniwo odpada przy uwierzytelnieniu bez hasła, dwa środkowe — przy
 * ho­ście, którego `~/.ssh/known_hosts` już zna. Najkrótsza droga to **jedno**
 * okno postępu, a o tym, którędy się poszło, rozstrzyga **stan portu**, nie ta
 * klasa: tylko port wie, co odpowiedział plik znanych hostów.
 *
 * Klasa nie trzyma żadnego stanu między oknami. Wszystko, co trzeba pamiętać
 * między ogniwami — profil, hasło, odciski — trzyma port, bo to on prowadzi
 * pracę; okna są tu tylko po to, żeby o tym stanie opowiedzieć i wziąć zgodę.
 */
final class ConnectFlow
{
    public function __construct(
        private readonly SshSession $session,
        private readonly TranslatorPort $translator,
    ) {
    }

    /**
     * Pierwsze ogniwo łańcucha: pytanie o hasło albo od razu praca.
     *
     * Zwraca okno, a nie skutek, bo wołający są dwaj i **inaczej je otwierają** —
     * ekran przez `ScreenOutcome::opens()`, komenda przez
     * `OverlayOutcome::replace()`, bo ustępuje własnego okna komend.
     */
    public function begin(HostProfile $profile): OverlayInterface
    {
        if ($this->session->needsPassword($profile)) {
            return $this->passwordPrompt($profile);
        }

        return $this->connecting($profile, null);
    }

    /** Okno hasła — **jedyny w aplikacji użytkownik maskowanego pola** (D87 nr 4). */
    private function passwordPrompt(HostProfile $profile): PromptOverlay
    {
        return new PromptOverlay(
            $this->key('prompt.password'),
            ['host' => $profile->label()],
            '',
            fn (string $password): OverlayOutcome => OverlayOutcome::replace(
                $this->connecting($profile, $password),
            ),
            $this->translator,
            $this->key('prompt.password.field'),
            masked: true,
        );
    }

    /** Zaczyna pracę i oddaje okno, które ją poprowadzi. */
    private function connecting(HostProfile $profile, ?string $password): ProgressOverlay
    {
        $this->session->connect($profile, $password);

        return $this->progress($profile);
    }

    private function progress(HostProfile $profile): ProgressOverlay
    {
        return new ProgressOverlay(
            $this->key('progress.connecting'),
            ['host' => $profile->label()],
            $this->workProgress(),
            fn (): WorkProgress => $this->workProgress(),
            fn (): OverlayOutcome => $this->finished($profile),
            fn (): Message => $this->cancelled($profile),
            $this->translator,
        );
    }

    /**
     * Stan pracy dla okna postępu — **bez ułamka, i to jest celowe**.
     *
     * Uścisk dłoni nie ma postępu do pokazania: nie wiadomo, ile go zostało,
     * dopóki się nie skończy. `ProgressBar` ma na to tryb „postęp nieznany"
     * dowieziony w kroku 26 dla `du` — i to jest jego drugi prawdziwy
     * użytkownik. Pasek udający wiedzę byłby paskiem kłamiącym.
     */
    private function workProgress(): WorkProgress
    {
        $state = $this->session->state();

        return new WorkProgress(
            running: $state->isWorking(),
            current: $this->text($state->stage->labelKey()),
        );
    }

    /** Co po skończonej pracy — trzy wyjścia, bo tyle ma stan portu. */
    private function finished(HostProfile $profile): OverlayOutcome
    {
        $state = $this->session->state();

        if ($state->stage === SessionStage::AwaitingApproval) {
            return OverlayOutcome::replace($this->fingerprintQuestion($profile));
        }

        if ($state->isConnected()) {
            return OverlayOutcome::close(Message::info(
                $this->text($this->key('message.connected'), ['host' => $profile->label()]),
            ));
        }

        return OverlayOutcome::close(Message::error(
            $this->text($state->problemKey ?? $this->key('problem.failed'), ['host' => $profile->label()]),
        ));
    }

    /**
     * Pytanie o nieznany odcisk — **oknem groźnym**, tym samym, którym usuwa się
     * trwale.
     *
     * Wariant `dangerous` nie jest przesadą: zgoda na klucz, którego się nie zna,
     * jest jedyną czynnością w tym module, której skutek wychodzi **poza
     * aplikację** — po niej klient dopisuje wiersz do `~/.ssh/known_hosts`, czyli
     * zmienia to, komu ufa cały system użytkownika, a nie ten jeden program.
     *
     * Odciski idą do pytania **wszystkie**, które serwer podał, bo użytkownik ma
     * porównać ten, który zna, a nie ten, który wybraliśmy za niego.
     */
    private function fingerprintQuestion(HostProfile $profile): ConfirmOverlay
    {
        $lines = [];

        foreach ($this->session->state()->fingerprints as $fingerprint) {
            $lines[] = $fingerprint->describe();
        }

        return new ConfirmOverlay(
            $this->key('confirm.fingerprint'),
            ['host' => $profile->label(), 'fingerprint' => implode('   ', $lines)],
            function () use ($profile): OverlayOutcome {
                $this->session->approve();

                return OverlayOutcome::replace($this->progress($profile));
            },
            $this->translator,
            dangerous: true,
            onRefuse: function (): void {
                // Odmowa zostawia port bez pracy: inaczej etap „czekam na
                // człowieka" trwałby, choć nikt już nie zamierza odpowiedzieć.
                $this->session->disconnect();
            },
        );
    }

    private function cancelled(HostProfile $profile): Message
    {
        $this->session->disconnect();

        return Message::warning(
            $this->text($this->key('message.cancelled'), ['host' => $profile->label()]),
        );
    }

    private function key(string $suffix): string
    {
        return 'module.' . SshSettings::ID . '.' . $suffix;
    }

    /** @param array<string, string|int|float> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate($key, $parameters);
    }
}
