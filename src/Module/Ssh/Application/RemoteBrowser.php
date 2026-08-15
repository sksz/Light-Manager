<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application;

use LightManager\Module\Ssh\Application\Port\HostBookPort;
use LightManager\Module\Ssh\Application\Port\RemoteDirectoryPort;
use LightManager\Module\Ssh\Domain\Aggregate\RemoteDirectory;
use LightManager\Module\Ssh\Domain\Exception\InvalidRemotePathException;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntry;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteNameFilter;
use LightManager\Module\Ssh\Domain\ValueObject\RemotePath;

/**
 * Chodzenie po zdalnym katalogu — **jedno na moduł** (krok 49).
 *
 * Odpowiednik `SshSession` dla drugiej postaci ekranu i powstał z tego samego
 * powodu: kursor, filtr, katalog i praca w toku to cztery rzeczy, które muszą
 * zgadzać się ze sobą co klatkę. Ekran, takt i klawisze dostają **ten sam**
 * obiekt.
 *
 * **Kursor mieszka tutaj, a nie w agregacie**, i jest to jedyna rzecz, którą ta
 * klasa robi inaczej niż przeglądarka. Powód jest w naturze odczytu: `Directory`
 * przeglądarki powstaje w tej samej chwili, w której użytkownik wchodzi do
 * katalogu, więc może nieść zaznaczenie od urodzenia. `RemoteDirectory` przychodzi
 * **po kilkuset milisekundach**, a kursor musi istnieć wcześniej — lista rysuje
 * się już w chwili, gdy odczyt trwa. To ta sama reguła, którą krok 18 zastosował
 * do `ScrollWindow`: stan przeżywający klatkę leży **obok** tego, co rysuje.
 *
 * **Nazwa do zaznaczenia po odczycie** (`$select`) jest drugą konsekwencją tego
 * samego faktu. Powrót wyżej ma postawić kursor na katalogu, z którego się
 * wyszło — ale w chwili naciśnięcia `Backspace` listy katalogu wyżej jeszcze
 * nie ma, więc pozycji nie da się wyliczyć. Zapisuje się więc **nazwę**
 * i rozstrzyga ją dopiero wtedy, gdy lista przyjdzie.
 */
final class RemoteBrowser
{
    /** Host, po którym chodzimy; `null` — nie ma sesji albo jeszcze nic nie otwarto. */
    private ?HostProfile $host = null;

    private ?RemoteDirectory $directory = null;

    private int $cursor = 0;

    private RemoteNameFilter $filter;

    private bool $includeHidden;

    /**
     * Nazwa, na której ma stanąć kursor, gdy przyjdzie lista; `null` — na
     * pierwszym wpisie.
     */
    private ?string $select = null;

    public function __construct(
        private readonly RemoteDirectoryPort $directories,
        private readonly HostBookPort $storage,
        bool $includeHidden = false,
    ) {
        $this->filter = RemoteNameFilter::none();
        $this->includeHidden = $includeHidden;
    }

    public function state(): RemoteListingState
    {
        return $this->directories->state();
    }

    public function host(): ?HostProfile
    {
        return $this->host;
    }

    public function directory(): ?RemoteDirectory
    {
        return $this->directory;
    }

    public function filter(): RemoteNameFilter
    {
        return $this->filter;
    }

    public function showsHidden(): bool
    {
        return $this->includeHidden;
    }

    /** Czy jest co pokazać — lista przyszła choć raz i nie została porzucona. */
    public function hasListing(): bool
    {
        return $this->directory !== null;
    }

    /**
     * Otwiera katalog na hoście, z którym właśnie stanęła sesja.
     *
     * Katalog jest **zapamiętany z poprzedniej sesji**, a gdy go nie ma —
     * startowy z profilu, a gdy i tego nie ma — ten, który wskaże serwer.
     * Trzy stopnie, i wszystkie trzy kosztują to samo: jedno wywołanie.
     */
    public function open(HostProfile $host): void
    {
        $this->host = $host;
        $this->directory = null;
        $this->cursor = 0;
        $this->select = null;
        $this->filter = RemoteNameFilter::none();
        $this->directories->begin($host, $this->startingPath($host), $this->includeHidden);
    }

    /** Zamyka oglądanie: przerywa pracę i zapomina listę (rozłączenie, zerwanie sesji). */
    public function close(): void
    {
        $this->directories->stop();
        $this->host = null;
        $this->directory = null;
        $this->cursor = 0;
        $this->select = null;
        $this->filter = RemoteNameFilter::none();
    }

    /**
     * Wchodzi w podświetlony wpis — o ile jest w co wchodzić.
     *
     * Dowiązanie **próbuje się otworzyć**, choć nie wiadomo, dokąd prowadzi
     * (rozstrzygnięcie użytkownika ze startu kroku): rozstrzygnięcie kosztowałoby
     * osobny obieg na każde dowiązanie w katalogu, a nieudana próba kosztuje
     * jedno zdanie w pasku stanu.
     *
     * @return bool czy praca ruszyła
     */
    public function enter(): bool
    {
        $entry = $this->selected();
        $host = $this->host;
        $path = $this->path();

        if ($entry === null || $host === null || $path === null || !$entry->type->mayBeEntered()) {
            return false;
        }

        $this->select = null;
        $this->cursor = 0;
        $this->filter = RemoteNameFilter::none();
        $this->directories->begin($host, $path->child($entry->name), $this->includeHidden);

        return true;
    }

    /**
     * Wraca do katalogu wyżej, stawiając kursor na tym, z którego wyszliśmy.
     *
     * @return bool czy praca ruszyła; `false` w korzeniu, bo wyżej nic nie ma
     */
    public function goUp(): bool
    {
        $host = $this->host;
        $path = $this->path();

        if ($host === null || $path === null || $path->isRoot()) {
            return false;
        }

        $this->select = $path->name();
        $this->cursor = 0;
        $this->filter = RemoteNameFilter::none();
        $this->directories->begin($host, $path->parent(), $this->includeHidden);

        return true;
    }

    /** `F5`: czyta ten sam katalog na nowo, zostawiając kursor tam, gdzie stał. */
    public function refresh(): bool
    {
        $host = $this->host;
        $path = $this->path();

        if ($host === null || $path === null) {
            return false;
        }

        $this->select = $this->selected()?->name;
        $this->directories->begin($host, $path, $this->includeHidden);

        return true;
    }

    /**
     * `Ctrl`+`H`: przełącza wpisy ukryte — **i czyta katalog na nowo**.
     *
     * Ponowny odczyt nie jest tu lenistwem, tylko koniecznością: serwer bez
     * `ls -a` wpisów zaczynających się kropką **w ogóle nie przysyła**, więc nie
     * ma czego odfiltrować. To jest różnica wobec przeglądarki, w której ta sama
     * czynność kosztuje jedno przejście po tablicy.
     */
    public function toggleHidden(): bool
    {
        $this->includeHidden = !$this->includeHidden;

        return $this->refresh();
    }

    public function useHidden(bool $includeHidden): void
    {
        $this->includeHidden = $includeHidden;
    }

    public function useFilter(RemoteNameFilter $filter): void
    {
        $this->filter = $filter;
        $this->cursor = 0;
    }

    public function clearFilter(): void
    {
        $this->useFilter(RemoteNameFilter::none());
    }

    /** Ścieżka oglądanego katalogu albo tego, który właśnie się czyta. */
    public function path(): ?RemotePath
    {
        return $this->directory->path ?? $this->state()->path;
    }

    /**
     * Wpisy do pokazania — po filtrze.
     *
     * Filtr działa na tym, co przyszło, więc kosztuje jedno przejście po
     * tablicy; wpisy ukryte kosztują obieg do serwera i dlatego nie ma ich tutaj.
     *
     * @return list<RemoteEntry>
     */
    public function entries(): array
    {
        $directory = $this->directory;

        if ($directory === null) {
            return [];
        }

        if ($this->filter->isEmpty()) {
            return $directory->entries;
        }

        return array_values(array_filter(
            $directory->entries,
            fn (RemoteEntry $entry): bool => $this->filter->matches($entry->name),
        ));
    }

    public function count(): int
    {
        return count($this->entries());
    }

    public function cursor(): int
    {
        return $this->clamped($this->cursor);
    }

    public function selected(): ?RemoteEntry
    {
        return $this->entries()[$this->cursor()] ?? null;
    }

    public function moveCursor(int $delta): void
    {
        $this->cursor = $this->clamped($this->cursor() + $delta);
    }

    public function putCursor(int $index): void
    {
        $this->cursor = $this->clamped($index);
    }

    /**
     * Takt: posuwa odczyt i **przyjmuje listę, gdy przyjdzie**.
     *
     * Przyjęcie jest tutaj, a nie w ekranie, i to jest ta sama reguła, co
     * w kroku 41 (11d): praca posuwa się w pętli, nie w rysowaniu. Ekran, który
     * przyjmowałby listę w `draw()`, zmieniałby stan w miejscu, które ma
     * wyłącznie oglądać — a przy dwóch panelach rysowanych w jednej klatce
     * robiłby to dwa razy.
     */
    public function tick(): void
    {
        $this->directories->advance();

        $state = $this->state();

        if (!$state->isReady() || $state->directory === null) {
            return;
        }

        $directory = $state->directory;

        if ($this->directory === $directory) {
            return;
        }

        $this->directory = $directory;
        $this->cursor = $this->positionOf($directory);
        $this->select = null;
        $this->remember($directory);
    }

    /**
     * Gdzie postawić kursor po przyjęciu listy.
     *
     * Nazwa zapisana przy wyjściu wyżej ma pierwszeństwo; gdy jej nie ma albo
     * wpis zniknął w międzyczasie, kursor staje na początku. Zniknął — bo katalog
     * na cudzej maszynie zmienia się bez naszego udziału i to jest tam stan
     * zwykły, nie wyjątkowy.
     */
    private function positionOf(RemoteDirectory $directory): int
    {
        if ($this->select === null) {
            return 0;
        }

        $index = $directory->indexOf($this->select);

        return $index ?? 0;
    }

    /**
     * Zapamiętuje katalog w pliku stanu modułu — **pod nazwą wpisu książki**.
     *
     * Pod nazwą, a nie pod adresem, bo to nazwa jest tożsamością wpisu (krok 48):
     * dwa wpisy o tym samym adresie i różnych loginach są dwoma miejscami
     * i mają prawo pamiętać różne katalogi.
     */
    private function remember(RemoteDirectory $directory): void
    {
        $host = $this->host;

        if ($host !== null) {
            $this->storage->rememberDirectory($host->name, $directory->path->value);
        }
    }

    /**
     * Katalog, od którego zaczyna się oglądanie: zapamiętany, z profilu albo
     * `null` — czyli „niech rozstrzygnie serwer”.
     *
     * Ścieżka **względna zostaje odrzucona po cichu** i jest to świadome:
     * `remoteDirectory` w pliku stanu prowadzi użytkownik, a wartość, której nie
     * da się użyć, ma skończyć się katalogiem domowym, a nie komunikatem
     * o błędzie pliku, którego nikt nie pamięta.
     */
    private function startingPath(HostProfile $host): ?RemotePath
    {
        foreach ([$this->storage->lastDirectory($host->name), $host->remoteDirectory] as $candidate) {
            if ($candidate === null || !str_starts_with($candidate, RemotePath::SEPARATOR)) {
                continue;
            }

            try {
                return RemotePath::of($candidate);
            } catch (InvalidRemotePathException) {
                continue;
            }
        }

        return null;
    }

    private function clamped(int $index): int
    {
        $count = $this->count();

        return $count === 0 ? 0 : max(0, min($index, $count - 1));
    }
}
