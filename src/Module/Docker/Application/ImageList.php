<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use LightManager\Module\Docker\Application\Port\DockerApiPort;
use LightManager\Module\Docker\Application\Port\DockerCatalogPort;
use LightManager\Module\Docker\Domain\ValueObject\Image;

/**
 * Lista obrazów wraz z jedyną czynnością, jaką na nich prowadzimy (krok 51).
 *
 * Bliźniacza wobec `ContainerList` i **świadomie osobna**, a nie sparametryzowana
 * wspólną klasą: różnią się rodzajem wpisu, zestawem czynności (tu jest jedna
 * i jest nieodwracalna) i zegarem odświeżania. Wspólna klasa z parametrem
 * „czego dotyczy” kazałaby każdemu wołającemu pytać, w którym jest trybie —
 * a zysk kończyłby się na trzydziestu linijkach obsługi kursora.
 *
 * **Obrazy odświeżają się rzadziej niż kontenery** i to nie jest oszczędność bez
 * powodu: stan kontenera zmienia się sam (proces w środku pada, restart
 * dochodzi do skutku), a lista obrazów zmienia się wyłącznie wtedy, gdy ktoś coś
 * zbuduje albo usunie — czyli po czynności, po której i tak odświeżamy ją wprost.
 */
final class ImageList
{
    /** Co ile sekund pytać demona o obrazy, gdy ekran jest widoczny. */
    private const REFRESH_SECONDS = 30.0;

    /** @var list<Image> */
    private array $images = [];

    private ?DockerCall $listing = null;

    private ?DockerCall $action = null;

    private string $pendingSubject = '';

    private ?ActionOutcome $outcome = null;

    private ?string $problemKey = null;

    private float $refreshedAt = 0.0;

    private int $cursor = 0;

    private bool $loaded = false;

    /**
     * Licznik zmian odpowiedzi — **znacznik pokolenia dla kwerendy
     * `docker.images`** (krok 54).
     *
     * Bije w dwóch miejscach i oba są tutaj: przyjęcie listy od demona oraz
     * przestawienie kursora. Dlaczego kursor: wiersz kwerendy niesie `selected`,
     * więc przesunięcie o jeden **zmienia odpowiedź** — licznik, który by tego
     * nie zauważył, kazałby oknu kwerend pokazywać zaznaczenie sprzed ruchu.
     */
    private int $revision = 0;

    public function __construct(
        private readonly DockerApiPort $api,
        private readonly DockerCatalogPort $catalog,
    ) {
    }

    public function refresh(): void
    {
        if ($this->listing !== null) {
            return;
        }

        $this->listing = $this->api->get('/images/json');
    }

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

    /**
     * Usunięcie obrazu — **po nazwie, gdy ją ma**.
     *
     * Powód stoi przy `Image::removalRef()`: usunięcie po identyfikatorze obrazu
     * o dwóch etykietach demon odrzuca, bo nie wie, którą z nich mamy stracić.
     */
    public function remove(Image $image): void
    {
        if ($this->action !== null) {
            return;
        }

        $this->pendingSubject = $image->label();
        $this->action = $this->api->delete(DockerAction::RemoveImage->pathFor($image->removalRef()->value));
    }

    public function isWorking(): bool
    {
        return $this->action !== null;
    }

    public function takeOutcome(): ?ActionOutcome
    {
        $outcome = $this->outcome;
        $this->outcome = null;

        return $outcome;
    }

    public function problemKey(): ?string
    {
        return $this->problemKey;
    }

    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /** @return list<Image> */
    public function entries(): array
    {
        return $this->images;
    }

    public function selected(): ?Image
    {
        return $this->images[$this->cursor] ?? null;
    }

    public function cursor(): int
    {
        return $this->cursor;
    }

    /** Patrz opis pola: ten sam numer znaczy tę samą odpowiedź kwerendy. */
    public function revision(): int
    {
        return $this->revision;
    }

    public function moveBy(int $delta): void
    {
        $this->moveTo($this->cursor + $delta);
    }

    public function moveTo(int $position): void
    {
        $moved = max(0, min(max(0, count($this->images) - 1), $position));

        if ($moved !== $this->cursor) {
            ++$this->revision;
        }

        $this->cursor = $moved;
    }

    public function stop(): void
    {
        if ($this->listing !== null) {
            $this->api->stop($this->listing);
            $this->listing = null;
        }

        if ($this->action !== null) {
            $this->api->stop($this->action);
            $this->action = null;
        }
    }

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

        $this->images = $this->catalog->images($result->body);
        $this->problemKey = null;
        $this->loaded = true;
        ++$this->revision;
        $this->moveTo($this->cursor);
    }

    private function collectAction(): void
    {
        if ($this->action === null) {
            return;
        }

        $result = $this->api->poll($this->action);

        if ($result->isRunning()) {
            return;
        }

        $call = $this->action;
        $this->action = null;
        $this->api->stop($call);

        $this->outcome = $this->outcomeOf($result);
        $this->refresh();
    }

    private function outcomeOf(DockerResult $result): ActionOutcome
    {
        $action = DockerAction::RemoveImage;

        if (!$result->isDone()) {
            return ActionOutcome::failure(
                $action,
                $this->pendingSubject,
                $result->problemKey ?? 'module.docker.daemon.unreachable',
            );
        }

        if ($action->accepts($result->status ?? 0)) {
            return ActionOutcome::success($action, $this->pendingSubject);
        }

        // Najczęstsza odmowa demona przy obrazie to `409`: korzysta z niego
        // kontener. Zdanie demona mówi który — i dlatego idzie do użytkownika
        // w całości, zamiast zostać zastąpione naszym „nie udało się”.
        return ActionOutcome::failure($action, $this->pendingSubject, 'module.docker.action.rejected', [
            'reason' => $this->catalog->problem($result->body) ?? (string) ($result->status ?? 0),
        ]);
    }
}
