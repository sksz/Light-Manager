<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Infrastructure;

use LightManager\Application\Port\StateDocumentPort;
use LightManager\Infrastructure\Config\StateDocumentService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Docker\Application\Port\DockerStatePort;

/**
 * Stan modułu Dockera — sekcja `docker` dokumentu stanu (krok 58; od kroku 59
 * w `~/.light-manager/state.json`, D103).
 *
 * Usłudze została **treść sekcji**: klucze `environments` i `currentEnvironment`
 * oraz zamiana wiersza na wpis środowiska i z powrotem. Mechanizm — plik, zapis
 * tymczasowy z `rename()`, przetrwanie nieznanych kluczy, migracja ze starego
 * `docker.json` — mieszka od kroku 59 za rdzeniowym `StateDocumentPort` (wynik
 * przeglądu 15e). Krok 60 dopisze książkę rejestrów **kluczami tej samej
 * sekcji**, nie drugą sekcją.
 *
 * **Żadna ścieżka nie rzuca** (zasada portu). Wiersz nie do przyjęcia wypada,
 * a sekcja zostaje — jeden zepsuty wpis nie odbiera użytkownikowi całej książki.
 */
final class DockerStateService extends AbstractSingleton implements DockerStatePort
{
    private const SECTION = 'docker';

    /** Klucz książki w sekcji; obok stanie klucz rejestrów z kroku 61. */
    private const ENVIRONMENTS_KEY = 'environments';

    /** Nazwa środowiska bieżącego — wybór przeżywa uruchomienie. */
    private const CURRENT_KEY = 'currentEnvironment';

    /** Znacznik przeniesienia starego spisu do książki adresowej (krok 60). */
    private const MIGRATED_KEY = 'migrated';

    private const NAME_KEY = 'name';

    private const KIND_KEY = 'kind';

    private const SOCKET_KEY = 'socket';

    private const TARGET_KEY = 'target';

    private const PORT_KEY = 'port';

    private const CERT_KEY = 'cert';

    private const KEY_KEY = 'key';

    private const CA_KEY = 'ca';

    private ?StateDocumentPort $documents = null;

    /**
     * Ostatnio wczytana sekcja — po to, żeby zapis nie skasował kluczy,
     * których ta wersja nie zna.
     *
     * @var array<string, mixed>|null
     */
    private ?array $section = null;

    private bool $sectionRead = false;

    /** Podstawienie dokumentu stanu — **wyłącznie dla testów** (szew jak w `KubectlService`). */
    public function useSeam(StateDocumentPort $documents): void
    {
        $this->documents = $documents;
        $this->section = null;
        $this->sectionRead = false;
    }

    public function current(): string
    {
        $current = ($this->section() ?? [])[self::CURRENT_KEY] ?? '';

        return is_string($current) ? $current : '';
    }

    public function makeCurrent(string $value): void
    {
        $section = $this->section() ?? [];

        if (($section[self::CURRENT_KEY] ?? null) === $value) {
            return;
        }

        $section[self::CURRENT_KEY] = $value;
        $this->section = $section;
        $this->documents()->saveSection(self::SECTION, $section);
    }

    /**
     * Stary spis środowisk — **czytany, nigdy niekasowany** (krok 60).
     *
     * Wiersze wychodzą stąd jako tablice napisów i liczb, a nie jako wpisy:
     * przenosi je do książki **komendami** ten, kto je tu zostawił. Usługa nie
     * musi przez to znać ani `DockerEnvironment`, ani książki — i nie zna.
     */
    public function legacyEnvironments(): array
    {
        $stored = ($this->section() ?? [])[self::ENVIRONMENTS_KEY] ?? null;

        if (!is_array($stored)) {
            return [];
        }

        $environments = [];

        foreach ($stored as $item) {
            if (!is_array($item)) {
                continue;
            }

            $environment = self::legacyEnvironmentFrom($item);

            if ($environment !== null) {
                $environments[] = $environment;
            }
        }

        return $environments;
    }

    public function isMigrated(): bool
    {
        return (($this->section() ?? [])[self::MIGRATED_KEY] ?? false) === true;
    }

    public function markMigrated(): void
    {
        $section = $this->section() ?? [];
        $section[self::MIGRATED_KEY] = true;
        $this->section = $section;
        $this->documents()->saveSection(self::SECTION, $section);
    }

    /**
     * @param array<mixed> $item
     *
     * @return array<string, string|int>|null
     */
    private static function legacyEnvironmentFrom(array $item): ?array
    {
        $name = $item[self::NAME_KEY] ?? null;
        $kind = $item[self::KIND_KEY] ?? null;

        if (!is_string($name) || $name === '' || !is_string($kind) || $kind === '') {
            return null;
        }

        $legacy = [self::NAME_KEY => $name, self::KIND_KEY => $kind];

        foreach ([self::SOCKET_KEY, self::TARGET_KEY, self::PORT_KEY, self::CERT_KEY, self::KEY_KEY, self::CA_KEY] as $key) {
            $value = $item[$key] ?? null;

            if ((is_string($value) && $value !== '') || is_int($value)) {
                $legacy[$key] = $value;
            }
        }

        return $legacy;
    }

    public function location(): string
    {
        return $this->documents()->location();
    }

    /**
     * Sekcja z dokumentu stanu, przeczytana raz; `null` znaczy „nie da się jej
     * przeczytać".
     *
     * @return array<string, mixed>|null
     */
    private function section(): ?array
    {
        if (!$this->sectionRead) {
            $this->sectionRead = true;
            $this->section = $this->documents()->section(self::SECTION);
        }

        return $this->section;
    }

    private function documents(): StateDocumentPort
    {
        return $this->documents ?? StateDocumentService::getInstance();
    }
}
