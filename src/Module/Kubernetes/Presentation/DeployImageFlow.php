<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandRegistry;
use LightManager\Application\Dto\WorkProgress;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Query\QueryRegistry;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Kubernetes\Application\ClusterActions;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Domain\ValueObject\NamespaceName;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceRef;
use LightManager\Module\Kubernetes\Presentation\Overlay\PickItem;
use LightManager\Module\Kubernetes\Presentation\Overlay\PickOverlay;
use LightManager\Module\Kubernetes\Presentation\Query\DeploymentsQuery;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\Overlay\ProgressOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `k8s.deploy-image` — **pierwszy odbiorca mechanizmu kwerend w kodzie**
 * (krok 54, cel kroku).
 *
 * Czynność odpowiada na pytanie, dla którego cała Faza XVIII ma trzy kroki
 * zamiast dwóch (D85): *jak moduł ma poprosić o coś, czego sam nie umie, nie
 * wiedząc, kto to zrobi.* Odpowiedź sprowadza się do trzech napisów i ani jednego
 * typu — `docker.images`, `docker.build`, `docker.push`. Reguła 15 zostaje
 * nietknięta: ten moduł sięga wyłącznie do **rdzenia**, a rdzeń trzyma rejestry,
 * do których wpisał się ktoś inny.
 *
 * ```
 * obrazy → [budowa → czekanie] → wypchnięcie → wdrożenia → podmiana
 * ```
 *
 * **Zdanie, które trzyma tę klasę: komenda robi, zdarzenie ogłasza, kwerenda mówi
 * co wyszło.** Budowa trwa minutami, więc nie czeka się na nią w klatce —
 * czekanie jest oknem pracy, które **pyta kwerendą raz na takt** i zamyka się,
 * gdy tamta odpowie „skończone".
 *
 * **`Esc` porzuca czekanie, a nie budowę** i to jest cała różnica, dla której
 * krok 54 przeniósł posuwanie budowy do taktu modułu Dockera (D94 nr 5). Praca
 * należy do tamtego modułu i trwa u niego dalej; my przestajemy patrzeć.
 *
 * **Wyłączony moduł Dockera nie wywraca niczego.** `QueryRegistry::ask()` oddaje
 * wtedy wynik z powodem, a `CommandRegistry::find()` — `null`; jedno i drugie
 * kończy się **zdaniem dla użytkownika**, bo brak odpowiedzi jest zwykłym stanem
 * (15g).
 */
final class DeployImageFlow
{
    /** Nazwy cudzych źródeł i cudzych czynności — **napisy, nigdy typy** (15g). */
    private const IMAGES_QUERY = 'docker.images';

    private const PUSH_QUERY = 'docker.push';

    private const BUILD_COMMAND = 'docker.build';

    private const PUSH_COMMAND = 'docker.push';

    /** Identyfikator pozycji „zbuduj nowy" — nie może zderzyć się z nazwą obrazu. */
    private const BUILD_NEW = "\0build-new";

    /** Chwila, od której liczy się czekanie; `null` — nie czekamy na nic. */
    private ?float $waitingSince = null;

    public function __construct(
        private readonly QueryRegistry $queries,
        private readonly CommandRegistry $commands,
        private readonly KubernetesQueries $reader,
        private readonly ClusterActions $actions,
        private readonly TranslatorPort $translator,
        private readonly SettingsPort $settings,
    ) {
    }

    /**
     * Etap pierwszy: obrazy znane modułowi Dockera.
     *
     * Pytanie idzie **nazwą kwerendy**, więc odpowiada ten, kto się pod nią
     * podpisał — a przy module wyłączonym nie odpowiada nikt i wtedy pada zdanie,
     * nie wyjątek. To jest dokładnie ten przypadek, którego żąda kryterium
     * ukończenia kroku: *wyłączony moduł Dockera nie wywraca niczego.*
     */
    public function begin(): OverlayOutcome
    {
        $result = $this->queries->ask(self::IMAGES_QUERY);

        if ($result->hasProblem()) {
            return OverlayOutcome::close(Message::warning($this->text('deploy.noDocker')));
        }

        $items = [new PickItem(self::BUILD_NEW, $this->text('deploy.buildNew'))];
        $loaded = false;

        foreach ($result->rows() as $row) {
            $loaded = $loaded || ($row['loaded'] ?? false) === true;
            $tag = is_string($row['tag'] ?? null) ? $row['tag'] : '';

            // Obraz **osierocony** nie wchodzi na listę: skrótu treści nie da się
            // ani wypchnąć do rejestru, ani wpisać we wdrożenie, więc pozycja
            // obiecywałaby czynność, która skończy się odmową.
            if ($tag === '') {
                continue;
            }

            $items[] = new PickItem($tag, $tag, is_string($row['size'] ?? null) ? $row['size'] : '');
        }

        // **Lista pusta, bo nikt jeszcze nie pytał, to nie to samo, co brak
        // obrazów** — i użytkownik nie odróżni tego z samej pustki. Moduł Dockera
        // pyta demona dopiero wtedy, gdy ktoś patrzy na jego listę (D90 nr 7),
        // więc czynność uruchomiona przed otwarciem tamtego ekranu zastałaby samo
        // „zbuduj nowy" i wyglądałoby to jak maszyna bez ani jednego obrazu.
        if (!$loaded) {
            return OverlayOutcome::close(Message::warning($this->text('deploy.imagesNotRead')));
        }

        return OverlayOutcome::replace(new PickOverlay(
            'module.' . KubernetesSettings::ID . '.deploy.pickImage',
            [],
            $items,
            fn (PickItem $item): OverlayOutcome => $item->id === self::BUILD_NEW
                ? $this->build()
                : $this->push($item->id),
            fn (): OverlayOutcome => OverlayOutcome::close(),
            $this->translator,
        ));
    }

    /**
     * Etap drugi: budowa — **oddana cudzej komendzie w całości**.
     *
     * Nie składamy tu ani katalogu, ani nazwy obrazu: łańcuch okien budowy należy
     * do modułu Dockera i pyta o jedno i drugie po swojemu (11n). Nasza czynność
     * kończy się w tym miejscu i **wraca dopiero czekaniem**, które użytkownik
     * uruchamia ponownie — bo dwa okna naraz nie mają gdzie stanąć.
     */
    private function build(): OverlayOutcome
    {
        $command = $this->commands->find(self::BUILD_COMMAND);

        if ($command === null) {
            return OverlayOutcome::close(Message::warning($this->text('deploy.noDocker')));
        }

        // O okno pyta się **zdolności komendy**, a nie rejestru — tak samo, jak
        // robią to obaj wołający komend w rdzeniu (okno komend i menu, krok 47).
        // Rejestr komend pozostaje przez to nietknięty, dokładnie jak rejestr
        // kwerend.
        $overlay = $command instanceof OpensOverlay ? $command->overlayFor(new CommandInput()) : null;

        if ($overlay !== null) {
            return $overlay;
        }

        $command->execute(new CommandInput());

        return OverlayOutcome::close(Message::info($this->text('deploy.building')));
    }

    /**
     * Etap trzeci i czwarty: wypchnięcie obrazu do rejestru wraz z czekaniem.
     *
     * Wypchnięcie jest **konieczne, a nie ozdobne** (D94 nr 1): obraz zbudowany na
     * demonie hosta nie jest widoczny dla klastra, bo minikube prowadzi własnego
     * demona wewnątrz kontenera. Bez tego etapu ostatni krok kończyłby się podem
     * w stanie `ImagePullBackOff` — czyli funkcją wyglądającą jak usterka.
     */
    private function push(string $tag): OverlayOutcome
    {
        $command = $this->commands->find(self::PUSH_COMMAND);

        if ($command === null) {
            return OverlayOutcome::close(Message::warning($this->text('deploy.noDocker')));
        }

        $command->execute(new CommandInput([ 'image' => $tag]));
        $this->waitingSince = null;

        return OverlayOutcome::replace($this->waiting(self::PUSH_QUERY, $tag));
    }

    /**
     * Okno czekania na cudzą pracę — **pyta kwerendą raz na takt**.
     *
     * `RunsWork` z kroku 41 pasuje tu bez najmniejszej zmiany, choć powstało dla
     * pracy własnej: pętla pyta okno raz na takt, a okno zamyka się samo, kiedy
     * praca się skończy. Różnica jest wyłącznie w tym, **skąd** okno wie o pracy
     * — stąd zdanie „kwerenda mówi co wyszło".
     */
    private function waiting(string $query, string $tag): ProgressOverlay
    {
        return new ProgressOverlay(
            'module.' . KubernetesSettings::ID . '.deploy.waiting',
            ['tag' => $tag],
            $this->progressOf($query),
            fn (): WorkProgress => $this->progressOf($query),
            fn (WorkProgress $progress): OverlayOutcome => $this->afterWait($query, $tag),
            fn (WorkProgress $progress): Message => Message::warning($this->text('deploy.abandoned')),
            $this->translator,
        );
    }

    /**
     * Postęp cudzej pracy przepisany na to, co rozumie okno rdzenia.
     *
     * **Limit czasu liczy się tutaj i nie przerywa cudzej pracy** — kończy
     * wyłącznie czekanie, bo praca należy do tamtego modułu (D94 nr 5).
     */
    private function progressOf(string $query): WorkProgress
    {
        $row = $this->queries->ask($query)->first() ?? [];
        $working = ($row['working'] ?? false) === true;

        if ($working && $this->waitingSince === null) {
            $this->waitingSince = microtime(true);
        }

        if ($working && $this->expired()) {
            $working = false;
        }

        $note = is_string($row['note'] ?? null) ? $row['note'] : '';

        return new WorkProgress(
            $working,
            $note === '' ? $this->text('deploy.stage') : $note,
            0,
            null,
            $this->text('deploy.stage'),
        );
    }

    private function expired(): bool
    {
        $since = $this->waitingSince;

        if ($since === null) {
            return false;
        }

        return microtime(true) - $since >= KubernetesSettings::buildWaitFrom($this->settings->current());
    }

    /**
     * Koniec czekania: albo idziemy do wdrożeń, albo mówimy, co poszło nie tak.
     *
     * Odpowiedź czytamy **jeszcze raz i po zakończeniu**, bo dopiero wtedy niesie
     * powód niepowodzenia — okno pytało w trakcie o to, czy praca trwa.
     */
    private function afterWait(string $query, string $tag): OverlayOutcome
    {
        $row = $this->queries->ask($query)->first() ?? [];
        $expired = $this->expired();
        $this->waitingSince = null;

        if ($expired) {
            return OverlayOutcome::close(Message::warning($this->text('deploy.timedOut')));
        }

        // **Cudzego klucza nie tłumaczymy** — jego parametrów nie znamy i znać
        // nie mamy prawa (poprawka z klatki: `translate()` na kluczu
        // `module.docker.push.rejected` wypisywał surowe `{reason}`). Zdanie
        // składamy z **własnego** klucza i powodu podanego przez tamten moduł
        // jako dana.
        $failed = ($row['problem'] ?? '') !== ''
            || (($row['done'] ?? false) !== true && ($row['stage'] ?? '') !== 'done');

        if ($failed) {
            $reason = is_string($row['reason'] ?? null) ? $row['reason'] : '';

            return OverlayOutcome::close(Message::error($reason === ''
                ? $this->text('deploy.pushFailed')
                : $this->text('deploy.pushRefused', ['reason' => $reason])));
        }

        return $this->pickDeployment($this->targetOf($row, $tag));
    }

    /**
     * Nazwa, którą wpiszemy we wdrożenie — **ta z rejestru, nie ta lokalna**.
     *
     * Wypchnięcie zmienia nazwę obrazu (`lm/proba:1` → `ghcr.io/sksz/lm/proba:1`),
     * a klaster pobierze wyłącznie tę drugą. Bierzemy ją z odpowiedzi kwerendy,
     * bo to tamten moduł wie, pod czym obraz naprawdę poszedł.
     *
     * @param array<string, string|int|bool> $row
     */
    private function targetOf(array $row, string $fallback): string
    {
        $target = is_string($row['target'] ?? null) ? $row['target'] : '';

        return $target === '' ? $fallback : $target;
    }

    /** Etap piąty: wdrożenia wraz z kontenerami — wybór i podmiana. */
    private function pickDeployment(string $image): OverlayOutcome
    {
        $view = $this->reader->deployments();
        $items = [];

        foreach ($view->rows as $row) {
            foreach ($row->images as $container => $current) {
                $items[] = new PickItem(
                    $row->name . "\0" . $container . "\0" . ($row->namespace ?? ''),
                    $row->name . ' · ' . $container,
                    $current,
                );
            }
        }

        if ($items === []) {
            return OverlayOutcome::close(Message::warning($this->text('deploy.noDeployments')));
        }

        return OverlayOutcome::replace(new PickOverlay(
            'module.' . KubernetesSettings::ID . '.deploy.pickDeployment',
            ['tag' => $image],
            $items,
            fn (PickItem $item): OverlayOutcome => $this->apply($item->id, $image),
            fn (): OverlayOutcome => OverlayOutcome::close(Message::warning($this->text('deploy.abandoned'))),
            $this->translator,
        ));
    }

    /** Podmiana obrazu — czynność klastra, więc idzie przez `ClusterActions`. */
    private function apply(string $id, string $image): OverlayOutcome
    {
        [$deployment, $container, $namespace] = array_pad(explode("\0", $id), 3, '');
        $kind = $this->reader->findKind(DeploymentsQuery::KIND_ADDRESS);

        if ($kind === null) {
            return OverlayOutcome::close(Message::error($this->text('deploy.noDeployments')));
        }

        $this->actions->setImage(
            ResourceRef::of($kind, $namespace === '' ? null : NamespaceName::of($namespace), $deployment),
            $container,
            $image,
        );

        return OverlayOutcome::close(Message::info($this->text('deploy.applying', [
            'deployment' => $deployment,
            'tag' => $image,
        ])));
    }

    /** @param array<string, string|int|float> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate('module.' . KubernetesSettings::ID . '.' . $key, $parameters);
    }
}
