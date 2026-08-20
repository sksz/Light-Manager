<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

use Closure;
use LightManager\Module\Docker\Application\Port\DockerCatalogPort;
use LightManager\Module\Docker\Application\Port\RegistryPort;
use LightManager\Module\Docker\Application\Registry\RegistryCall;
use LightManager\Module\Docker\Application\Registry\RegistryContentView;
use LightManager\Module\Docker\Application\Registry\RegistryEndpoint;
use LightManager\Module\Docker\Application\Registry\RegistryMode;
use LightManager\Module\Docker\Application\Registry\RegistryStage;
use LightManager\Module\Docker\Domain\ValueObject\ImageRegistry;

/**
 * Zawartość rejestru oglądana **kawałek po kawałku, raz na takt** (krok 61,
 * etap 2).
 *
 * Rodzeństwo `Environments` i `Registries`, ale prowadzi coś, czego tamte nie
 * mają: **rozmowę z siecią**. Stąd jedyna reguła, której nie wolno tu naruszyć —
 * `tick()` **posuwa** rozmowę i **odczytuje** jej stan, a nigdy na nią nie
 * czeka. Reguła nadrzędna Fazy XVII: żadne wywołanie sieciowe nie pada
 * w rysowaniu klatki.
 *
 * **Zaczyna się na żądanie, nie przy wejściu w widok.** Katalog dużego rejestru
 * to setki nazw i cudzy serwer po drugiej stronie; pytanie zadawane samo, przy
 * każdym przełączeniu postaci ekranu, byłoby ruchem sieciowym, którego nikt nie
 * zamówił — ta sama zasada, którą krok 48 zapisał dla odświeżania sesji
 * (`F5`, nigdy co kilka sekund).
 *
 * **`404` na katalogu przełącza tryb, zamiast mówić o błędzie.** Rejestr bez
 * `/v2/_catalog` jest zwykłym rejestrem, a nie zepsutym — widok przechodzi
 * wtedy w „podaj nazwę obrazu”.
 */
final class RegistryBrowse
{
    private ?RegistryCall $call = null;

    private RegistryStage $stage = RegistryStage::Idle;

    private RegistryMode $mode = RegistryMode::Catalog;

    /** @var list<string> */
    private array $rows = [];

    private string $image = '';

    private ?string $problemKey = null;

    private ?ImageRegistry $registry = null;

    private int $revision = 0;

    public function __construct(
        private readonly RegistryPort $registryApi,
        private readonly DockerCatalogPort $reader,
    ) {
    }

    /**
     * Który rejestr oglądamy.
     *
     * Zmiana **unieważnia wszystko**, co przyszło od poprzedniego — tą samą
     * regułą, którą przełączenie środowiska unieważnia listy, logi i budowę
     * (krok 58). Treść jednego rejestru pokazana pod nazwą drugiego byłaby
     * kłamstwem, którego nie widać.
     *
     * @param Closure(): string $token skąd wziąć token — **wołane wyłącznie
     *                                 wtedy, gdy rejestr naprawdę się zmienił**
     */
    public function useRegistry(?ImageRegistry $registry, Closure $token): void
    {
        if ($registry !== null && $this->registry !== null && $registry->id === $this->registry->id) {
            return;
        }

        $this->registry = $registry;
        $this->forget();

        if ($registry !== null) {
            $this->registryApi->useRegistry(new RegistryEndpoint(
                $registry->address,
                $registry->user,
                // **Token bierze się dopiero tutaj i to jest cały powód, dla
                // którego przychodzi domknięciem, a nie napisem.** Wołający
                // pyta o niego w takcie, czyli trzydzieści razy na sekundę;
                // podany wprost byłby odczytem materiału uwierzytelnienia
                // powtarzanym bez końca i odrzucanym przy każdym takcie poza
                // tym jednym, w którym rejestr się zmienił. Ta sama pułapka,
                // którą krok 59 zapłacił, pytając o wersję serwera co klatkę.
                ($token)(),
                $registry->insecure,
            ));
        }
    }

    /** Spis repozytoriów — **na żądanie**, nie przy wejściu w widok. */
    public function openCatalog(): void
    {
        if ($this->registry === null) {
            return;
        }

        $this->stop();
        $this->mode = RegistryMode::Catalog;
        $this->rows = [];
        $this->problemKey = null;
        $this->image = '';
        $this->call = $this->registryApi->catalog();
        $this->stage = RegistryStage::Asking;
        ++$this->revision;
    }

    /** Etykiety jednego obrazu. */
    public function openTags(string $image): void
    {
        if ($this->registry === null || $image === '') {
            return;
        }

        $this->stop();
        $this->mode = RegistryMode::Tags;
        $this->rows = [];
        $this->problemKey = null;
        $this->image = $image;
        $this->call = $this->registryApi->tags($image);
        $this->stage = RegistryStage::Asking;
        ++$this->revision;
    }

    /**
     * Posunięcie rozmowy o takt — **pompowanie plus odczyt stanu**.
     *
     * Pompowanie należy do taktu, a nie do widoku: rozmowa ma iść dalej także
     * wtedy, gdy użytkownik przełączył się na kontenery, bo inaczej stanęłaby
     * w miejscu przy pierwszej zmianie postaci ekranu (lekcja kroku 54).
     */
    public function tick(): void
    {
        $this->registryApi->pump();

        if ($this->call === null) {
            return;
        }

        $result = $this->registryApi->poll($this->call);

        if ($result->isWorking()) {
            $this->stage = $result->stage;

            return;
        }

        if ($result->stage === RegistryStage::Idle) {
            return;
        }

        $this->settle($result->stage, $result->isMissing(), $result->isSuccessful(), $result->body, $result->problemKey);
    }

    public function revision(): int
    {
        return $this->revision;
    }

    public function view(): RegistryContentView
    {
        return RegistryContentView::of(
            $this->stage,
            $this->mode,
            $this->rows,
            $this->image,
            $this->registry === null ? '' : ($this->registry->name === '' ? $this->registry->address : $this->registry->name),
            $this->problemKey,
        );
    }

    public function mode(): RegistryMode
    {
        return $this->mode;
    }

    /** Zapomina wszystko, co pokazane — przy zmianie rejestru i przy `reset()`. */
    public function forget(): void
    {
        $this->stop();
        $this->stage = RegistryStage::Idle;
        $this->mode = RegistryMode::Catalog;
        $this->rows = [];
        $this->image = '';
        $this->problemKey = null;
        ++$this->revision;
    }

    private function settle(
        RegistryStage $stage,
        bool $missing,
        bool $successful,
        string $body,
        ?string $problemKey,
    ): void {
        $this->call = null;
        ++$this->revision;

        if ($missing && $this->mode === RegistryMode::Catalog) {
            // **Rejestr bez katalogu nie jest zepsuty** — trzecia trudność kroku.
            $this->mode = RegistryMode::NeedsName;
            $this->stage = RegistryStage::Done;
            $this->rows = [];

            return;
        }

        if ($stage === RegistryStage::Failed || !$successful) {
            $this->stage = RegistryStage::Failed;
            $this->problemKey = $problemKey ?? 'module.docker.registry.unreachable';
            $this->rows = [];

            return;
        }

        $this->stage = RegistryStage::Done;
        $this->rows = $this->mode === RegistryMode::Catalog
            ? $this->reader->repositories($body)
            : $this->reader->registryTags($body);
    }

    private function stop(): void
    {
        if ($this->call !== null) {
            $this->registryApi->stop($this->call);
            $this->call = null;
        }
    }
}
