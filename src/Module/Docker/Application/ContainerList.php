<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Module\Docker\Application\Port\DockerApiPort;
use LightManager\Module\Docker\Application\Port\DockerCatalogPort;
use LightManager\Module\Docker\Domain\ValueObject\Container;

/**
 * Lista kontenerów wraz z czynnościami na nich (krok 51).
 *
 * Klasa jest odpowiednikiem `RemoteBrowser` z kroku 49: trzyma to, co widać,
 * i prowadzi pracę, która to zmienia — a rysowaniem i mówieniem do użytkownika
 * nie zajmuje się wcale.
 *
 * **Odświeżanie idzie z zegara, ale tylko wtedy, gdy ekran widać** (D90 nr 7).
 * Rozstrzygnięcie ma powód wymierny: pytanie po gnieździe unixowym jest
 * nieblokujące i tanie, a lista kłamiąca o stanie kontenera jest gorsza od
 * dwunastu pytań na minutę. Warunku widoczności ta klasa **nie zna i znać nie
 * może** — wie o nim ekran, więc to on woła `tick()` z prawdą o tym, czy jest
 * na wierzchu.
 *
 * **Czynność nie czeka na własny skutek.** Po odpowiedzi demona lista odświeża
 * się sama, bo stan po `stop` zna dopiero demon: kontener bywa zatrzymywany
 * przez dziesięć sekund, a `Exited` pojawia się dopiero na końcu.
 */
final class ContainerList
{
    /** Co ile sekund pytać demona, gdy ekran jest widoczny (D90 nr 7). */
    private const REFRESH_SECONDS = 5.0;

    /** @var list<Container> */
    private array $containers = [];

    private ?DockerCall $listing = null;

    private ?DockerCall $action = null;

    private ?DockerAction $pendingAction = null;

    private string $pendingSubject = '';

    private ?ActionOutcome $outcome = null;

    private ?string $problemKey = null;

    private float $refreshedAt = 0.0;

    private int $cursor = 0;

    /** Zawężenie do projektu compose; `null` — pokazujemy wszystko. */
    private ?string $project = null;

    private bool $loaded = false;

    /** Ile razy zmieniła się odpowiedź — patrz `revision()`. */
    private int $revision = 0;

    public function __construct(
        private readonly DockerApiPort $api,
        private readonly DockerCatalogPort $catalog,
    ) {
    }

    /** Zamawia świeżą listę. Wołanie w trakcie trwającego pytania nie robi nic. */
    public function refresh(): void
    {
        if ($this->listing !== null) {
            return;
        }

        $this->listing = $this->api->get('/containers/json?all=1');
    }

    /**
     * Posunięcie o takt: odbiera odpowiedzi i pilnuje zegara odświeżania.
     *
     * @param bool $visible czy ekran modułu jest na wierzchu — zegar chodzi
     *                      wyłącznie wtedy (D90 nr 7)
     */
    public function tick(float $now, bool $visible): void
    {
        $this->collectListing();
        $this->collectAction();

        if (!$visible) {
            return;
        }

        if (!$this->loaded || $now - $this->refreshedAt >= self::REFRESH_SECONDS) {
            $this->refreshedAt = $now;
            $this->refresh();
        }
    }

    /** Czynność na wskazanym kontenerze; druga w trakcie pierwszej nie rusza. */
    public function begin(DockerAction $action, Container $container): void
    {
        if ($this->action !== null) {
            return;
        }

        $this->pendingAction = $action;
        $this->pendingSubject = $container->name;
        $this->action = $action->method() === 'DELETE'
            ? $this->api->delete($action->pathFor($container->id->value))
            : $this->api->post($action->pathFor($container->id->value));
    }

    /** Czy czynność właśnie trwa — ekran pokazuje to zdaniem w nagłówku. */
    public function isWorking(): bool
    {
        return $this->action !== null;
    }

    /** Wynik ostatniej czynności — **zabierany, nie oglądany**. */
    public function takeOutcome(): ?ActionOutcome
    {
        $outcome = $this->outcome;
        $this->outcome = null;

        return $outcome;
    }

    /** Powód, dla którego lista jest pusta; `null` — jest w porządku. */
    public function problemKey(): ?string
    {
        return $this->problemKey;
    }

    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /**
     * Kontenery do pokazania — z zawężeniem do projektu, gdy jest zamówione.
     *
     * @return list<Container>
     */
    public function entries(): array
    {
        $project = $this->project;

        if ($project === null) {
            return $this->containers;
        }

        return array_values(array_filter(
            $this->containers,
            static fn (Container $container): bool => $container->belongsTo($project),
        ));
    }

    /**
     * Wszystkie kontenery, bez zawężenia — dla tego, kto liczy projekty.
     *
     * @return list<Container>
     */
    public function all(): array
    {
        return $this->containers;
    }

    public function selected(): ?Container
    {
        return $this->entries()[$this->cursor] ?? null;
    }

    public function cursor(): int
    {
        return $this->cursor;
    }

    public function moveBy(int $delta): void
    {
        $count = count($this->entries());

        if ($count === 0) {
            $this->cursor = 0;

            return;
        }

        $this->cursor = max(0, min($count - 1, $this->cursor + $delta));
    }

    public function moveTo(int $position): void
    {
        $moved = max(0, min(max(0, count($this->entries()) - 1), $position));

        if ($moved !== $this->cursor) {
            ++$this->revision;
        }

        $this->cursor = $moved;
    }

    /**
     * Licznik zmian odpowiedzi — **znacznik pokolenia dla kwerendy
     * `docker.containers`** (krok 54).
     *
     * Bije w trzech miejscach: przyjęcie listy od demona, przestawienie kursora
     * i zmiana zawężenia do projektu. To ostatnie **jest zmianą odpowiedzi**, bo
     * kwerenda oddaje to, co widać w panelu — a nie wszystko, co demon zna.
     */
    public function revision(): int
    {
        return $this->revision;
    }

    /** Zawężenie do projektu compose; `null` zdejmuje je. */
    public function narrowTo(?string $project): void
    {
        if ($project !== $this->project) {
            ++$this->revision;
        }

        $this->project = $project;
        $this->cursor = 0;
    }

    public function project(): ?string
    {
        return $this->project;
    }

    /**
     * Nazwy projektów compose obecne na liście — źródło zawężenia.
     *
     * @return list<string>
     */
    public function projects(): array
    {
        $names = [];

        foreach ($this->containers as $container) {
            if ($container->composeProject !== null) {
                $names[$container->composeProject] = true;
            }
        }

        $sorted = array_keys($names);
        sort($sorted);

        return $sorted;
    }

    /** Przerywa wszystko, co trwa — wołane przy zamykaniu modułu i przy `reset()`. */
    public function stop(): void
    {
        if ($this->listing !== null) {
            $this->api->stop($this->listing);
            $this->listing = null;
        }

        if ($this->action !== null) {
            $this->api->stop($this->action);
            $this->action = null;
            $this->pendingAction = null;
        }
    }

    /**
     * Zapomina wszystko, co przyszło — bo przyszło od **innego demona**
     * (krok 58, przełączenie środowiska).
     *
     * Przerwanie pytań w locie jest tu częścią poprawności, nie porządkiem:
     * odpowiedź zamówiona przed przełączeniem przyszłaby od poprzedniego demona
     * i wskrzesiłaby listę, którą właśnie unieważniono. Kryterium kroku mówi
     * wprost — żaden wiersz z poprzedniego demona nie przeżywa zmiany.
     */
    public function forget(): void
    {
        $this->stop();
        $this->containers = [];
        $this->outcome = null;
        $this->problemKey = null;
        $this->loaded = false;
        $this->refreshedAt = 0.0;
        $this->cursor = 0;
        $this->project = null;
        ++$this->revision;
    }

    /**
     * Odbiera odpowiedź na pytanie o listę.
     *
     * Kursor **przeżywa odświeżenie tak, jak potrafi**: zostaje na tej samej
     * pozycji, a gdy lista się skróciła — dochodzi do jej końca. Trzymanie
     * kursora na identyfikatorze byłoby wierniejsze, ale kontener usunięty
     * i tak zabiera swój wiersz, a pozycja jest tym, czego oko szuka po
     * odświeżeniu.
     */
    private function collectListing(): void
    {
        if ($this->listing === null) {
            return;
        }

        $result = $this->api->poll($this->listing);

        if ($result->isRunning()) {
            return;
        }

        $call = $this->listing;
        $this->listing = null;
        $this->api->stop($call);

        if (!$result->isDone()) {
            $this->problemKey = $result->problemKey ?? 'module.docker.daemon.unreachable';

            return;
        }

        if (!$result->isSuccessful()) {
            $this->problemKey = 'module.docker.daemon.refused';

            return;
        }

        $this->containers = $this->catalog->containers($result->body);
        $this->problemKey = null;
        $this->loaded = true;
        ++$this->revision;
        $this->moveTo($this->cursor);
    }

    /** Odbiera odpowiedź na czynność i zamawia świeżą listę. */
    private function collectAction(): void
    {
        $action = $this->pendingAction;

        if ($this->action === null || $action === null) {
            return;
        }

        $result = $this->api->poll($this->action);

        if ($result->isRunning()) {
            return;
        }

        $call = $this->action;
        $this->action = null;
        $this->pendingAction = null;
        $this->api->stop($call);

        $this->outcome = $this->outcomeOf($action, $this->pendingSubject, $result);

        // Świeża lista po każdej czynności — także po nieudanej: demon mógł
        // zdążyć zmienić stan, zanim się poddał.
        $this->refresh();
    }

    private function outcomeOf(DockerAction $action, string $subject, DockerResult $result): ActionOutcome
    {
        if (!$result->isDone()) {
            return ActionOutcome::failure(
                $action,
                $subject,
                $result->problemKey ?? 'module.docker.daemon.unreachable',
                $result->problemParameters,
            );
        }

        if ($action->accepts($result->status ?? 0)) {
            return ActionOutcome::success($action, $subject);
        }

        return ActionOutcome::failure($action, $subject, 'module.docker.action.rejected', [
            'reason' => $this->catalog->problem($result->body) ?? (string) ($result->status ?? 0),
        ]);
    }
}
