<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Config;

use LightManager\Application\Port\StateDocumentPort;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Dokument stanu w pliku `~/.light-manager/state.json` (krok 59, D103).
 *
 * Jeden plik z sekcjami po właścicielach — to, co do kroku 59 leżało w trzech
 * plikach modułów (`audio.json`, `ssh.json`, `docker.json`), leży odtąd
 * w sekcjach jednego dokumentu, a czwarta sekcja (`k8s`) rodzi się już tutaj.
 * Stary plik czyta się **tu**, gdy sekcji w dokumencie jeszcze nie ma: jego
 * treść jest dokładnie treścią sekcji, więc migracja to odczyt pliku o nazwie
 * sekcji — bez przepisywania i bez wiedzy o tym, co w środku. Na dysku stary
 * plik zostaje nietknięty; sekcją dokumentu staje się przy pierwszym zapisie
 * któregokolwiek właściciela.
 *
 * **Odczyt jest jeden na proces** — ten sam warunek, który w pliku dźwięku
 * pozwolił playliście i mapie efektów mieszkać razem: sekcje czytają się
 * niezależnie, w kolejności, której nikt nie ustala, a zapis jednej nie ma
 * prawa skasować drugiej. Z tego samego powodu dokument trzymamy w pamięci
 * w całości: klucze i sekcje, których ta wersja nie zna, przeżywają zapis
 * nietknięte.
 *
 * **Żadna ścieżka nie rzuca** (zasada portu). Dokument nie do przeczytania
 * daje sekcje `null` — zdanie do użytkownika składa właściciel sekcji — a
 * pierwszy zapis nadpisuje plik, którego i tak nikt nie zrozumiał (ta sama
 * reguła, co w usługach stanu sprzed tego kroku; plik jest teraz wspólny, więc
 * właściciele mówią o nieczytelności **zanim** użytkownik cokolwiek dopisze).
 */
final class StateDocumentService extends AbstractSingleton implements StateDocumentPort
{
    private const FILE = 'state.json';

    private const TEMPORARY_PREFIX = '.state-';

    /** @var array<string, mixed> ostatnio wczytany dokument */
    private array $document = [];

    private bool $documentRead = false;

    private bool $readable = true;

    /**
     * Sekcje, których stary plik modułu okazał się nieczytelny — odpowiedź
     * pamiętana, żeby drugi odczyt nie dotykał dysku.
     *
     * @var array<string, true>
     */
    private array $unreadableLegacy = [];

    public function section(string $name): ?array
    {
        $document = $this->document();

        if ($document === null) {
            return null;
        }

        if (array_key_exists($name, $document)) {
            $section = $document[$name];

            if (!is_array($section)) {
                return [];
            }

            /** @var array<string, mixed> $section */
            return $section;
        }

        return $this->adoptLegacy($name);
    }

    public function hasSection(string $name): bool
    {
        $document = $this->document();

        if ($document !== null && array_key_exists($name, $document)) {
            return true;
        }

        return is_file($this->legacyLocation($name));
    }

    public function saveSection(string $name, array $data): void
    {
        $this->document();
        $this->document[$name] = $data;
        // Od pierwszego zapisu prawdą jest dokument w pamięci: plik bez sensu
        // został właśnie nadpisany, więc „nieczytelny" przestało być prawdą.
        $this->readable = true;
        $this->write();
    }

    public function location(): string
    {
        return StateFile::directory() . DIRECTORY_SEPARATOR . self::FILE;
    }

    /**
     * Dokument z dysku, przeczytany raz; `null` znaczy „nie da się go
     * przeczytać".
     *
     * Nieudany odczyt zostawia dokument **pusty, ale przeczytany**: kolejne
     * pytanie nie dotyka dysku, a pierwszy zapis nadpisze plik, którego i tak
     * nikt nie zrozumiał.
     *
     * @return array<string, mixed>|null
     */
    private function document(): ?array
    {
        if ($this->documentRead) {
            return $this->readable ? $this->document : null;
        }

        $this->documentRead = true;
        $path = $this->location();

        if (!is_file($path)) {
            return $this->document;
        }

        $raw = @file_get_contents($path);
        /** @var mixed $decoded */
        $decoded = $raw === false ? null : json_decode($raw, true);

        if (!is_array($decoded)) {
            $this->readable = false;

            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $this->document = $decoded;
    }

    /**
     * Sekcja ze starego pliku modułu (`<sekcja>.json`) — bez zapisu.
     *
     * Treść wchodzi do dokumentu w pamięci, więc pierwszy zapis
     * **któregokolwiek** właściciela utrwali ją w `state.json` — migracja
     * domyka się sama, bez osobnego kroku. Plik nieczytelny wraca `null`em
     * i jest pamiętany, żeby pytanie padało raz.
     *
     * @return array<string, mixed>|null
     */
    private function adoptLegacy(string $name): ?array
    {
        if (isset($this->unreadableLegacy[$name])) {
            return null;
        }

        $path = $this->legacyLocation($name);

        if (!is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        /** @var mixed $decoded */
        $decoded = $raw === false ? null : json_decode($raw, true);

        if (!is_array($decoded)) {
            $this->unreadableLegacy[$name] = true;

            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $this->document[$name] = $decoded;
    }

    private function legacyLocation(string $name): string
    {
        return StateFile::directory() . DIRECTORY_SEPARATOR . $name . '.json';
    }

    private function write(): void
    {
        $content = json_encode($this->document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($content === false) {
            return;
        }

        StateFile::write(StateFile::directory(), self::FILE, self::TEMPORARY_PREFIX, $content);
    }
}
