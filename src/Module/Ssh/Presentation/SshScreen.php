<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ContextOrigin;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Ssh\Application\RemoteBrowser;
use LightManager\Module\Ssh\Application\SshSession;
use LightManager\Module\Ssh\Application\SshSettings;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntry;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\DeclaresFocus;
use LightManager\Presentation\Ui\FocusHint;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Module\ReadsContext;
use LightManager\Presentation\Ui\Resettable;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScreenZone;

/**
 * Ekran modułu sesji zdalnej — **jeden ekran w dwóch postaciach** (krok 49).
 *
 * Rozstrzygnięcie użytkownika ze startu kroku, spośród trzech postawionych:
 * spis hostów **ustępuje miejsca** zdalnemu katalogowi po połączeniu i wraca po
 * rozłączeniu. Odrzucono podział ekranu (`Split` z kroku 24 dałby tabeli
 * o czterech kolumnach połowę szerokości) oraz kontrakt oddający wiele ekranów
 * (to zmiana rdzenia, a moduł kosztuje jedną linię w `Bootstrapie` — reguła 15).
 *
 * **Postać zmienia takt, a nie klawisz**, i to jest cała treść tej klasy.
 * Połączenie kończy się w procesie potomnym, więc chwili, w której „jest sesja”,
 * nie zna żaden klawisz — zna ją dopiero `poll()`. Ekran pyta o nią co takt
 * i przełącza się sam; użytkownik dostaje `F2` wyłącznie po to, żeby **zajrzeć**
 * do spisu przy żywej sesji.
 *
 * **Kontekst sesji publikuje się stąd** i wyłącznie stąd. Ścieżka zdalna idzie
 * do rdzenia z pochodzeniem `Remote` (krok 49, zmiana `ModuleContext`) —
 * inaczej moduł opisu pliku odczytałby ją `lstat`em i pokazał **lokalny** plik
 * o tej samej nazwie. Postać „spis hostów” kontekstu **nie publikuje wcale**:
 * host nie jest miejscem w drzewie plików i nie ma czego o nim powiedzieć
 * odbiorcy, który czeka na ścieżkę.
 */
final class SshScreen implements ScreenInterface, DeclaresFocus, Resettable, ReadsContext
{
    public const ID = 'ssh';

    private bool $showsHosts = true;

    /**
     * Czy ekran był widoczny w poprzedniej klatce — **jedyny sygnał, jakim
     * dysponuje**, i warunek publikacji kontekstu.
     *
     * `ScreenStack` mówi ekranowi, że go otwarto (`reset()`), ale **nie mówi
     * nikomu, że go zasłonięto** — a takt modułu chodzi niezależnie od tego, na
     * co użytkownik patrzy. Bez tego warunku lista przyjęta w tle nadpisywałaby
     * kontekst komuś, kto stoi w przeglądarce, i moduł opisu pliku pokazałby
     * zdalny wpis zamiast lokalnego. Czyli dokładnie to kłamstwo, dla którego
     * pochodzenie kontekstu w ogóle powstało — tyle że w drugą stronę.
     *
     * Zapis w `draw()` **nie jest zmianą stanu aplikacji**: notuje, że ekran był
     * na ekranie, i nic poza tym.
     */
    private bool $drawn = false;
    public function __construct(
        private readonly SshSession $session,
        private readonly RemoteBrowser $browser,
        private readonly HostsScreen $hosts,
        private readonly RemoteScreen $remote,
        private readonly LoopState $state,
        private readonly LocalPlace $local,
    ) {
    }

    /**
     * Kontekst przychodzi tu **czytany**, a nie tylko publikowany (krok 50).
     *
     * Ekran zdalny jest jedynym miejscem, które kontekst i publikuje, i czyta —
     * i nie jest to sprzeczność, tylko jedyna droga do drugiej strony przesyłu.
     * `FrameComposer` podaje kontekst **przed** rysowaniem, więc w pierwszej
     * klatce po przełączeniu przychodzi jeszcze ten opublikowany przez
     * przeglądarkę; `LocalPlace` przyjmuje wyłącznie kontekst z tej maszyny, więc
     * własny, zdalny mija go bez śladu. Bez tego zatrzasku „pobierz do katalogu,
     * w którym stoi przeglądarka" nie miałoby czego zapytać — kontekst jest
     * jeden, a ekran zdalny właśnie go nadpisał (D89 nr 8).
     */
    public function useContext(ModuleContext $context): void
    {
        $this->local->remember($context);
    }

    public function id(): string
    {
        // Tożsamość jest **jedna, mimo dwóch postaci**: `ScreenStack` liczy po
        // niej ekrany, a dwie tożsamości znaczyłyby dwa wpisy na stosie dla
        // czegoś, co użytkownik widzi jako jedno miejsce.
        return self::ID;
    }

    public function labelKey(): string
    {
        return $this->showsRemote()
            ? 'module.' . SshSettings::ID . '.screen.remote'
            : $this->hosts->labelKey();
    }

    public function header(): ScreenZone
    {
        if (!$this->showsRemote()) {
            return $this->hosts->header();
        }

        return new ScreenZone(
            $this->labelKey(),
            new Label($this->remote->header()),
        );
    }

    public function draw(Rect $bounds): array
    {
        $this->drawn = true;

        return $this->showsRemote() ? $this->remote->draw($bounds) : $this->hosts->draw($bounds);
    }

    /**
     * Wiązania widocznej postaci **plus przełącznik postaci**, gdy jest co
     * przełączać.
     *
     * Przełącznik dokłada się tutaj, a nie w którejkolwiek z postaci, bo należy
     * do ekranu jako całości — i musi być widoczny w stopce z obu stron, inaczej
     * użytkownik, który zajrzał do spisu hostów, nie miałby jak wrócić do
     * katalogu.
     */
    public function bindings(): array
    {
        $bindings = $this->showsRemote() ? $this->remote->bindings() : $this->hosts->bindings();

        if (!$this->browser->hasListing()) {
            return $bindings;
        }

        $bindings[] = KeyBinding::of(
            [Key::F3],
            'module.' . SshSettings::ID . '.remote.key.hosts',
            'module.' . SshSettings::ID . '.remote.key.hosts.short',
        );

        return $bindings;
    }

    public function focus(): FocusHint
    {
        return $this->showsRemote() ? $this->remote->focus() : $this->hosts->focus();
    }

    public function reset(): void
    {
        $this->drawn = true;
        $this->hosts->reset();
    }

    /**
     * `F3` przerzuca postać **w obie strony**, ale wyłącznie przy żywej sesji.
     *
     * Bez sesji nie ma czego pokazać, więc klawisz nie robi nic — i jest to
     * lepsze niż pusty panel z zaproszeniem, bo spis hostów **jest** tym
     * zaproszeniem.
     *
     * `F3`, a nie `F2`, bo **`F2` należy do rdzenia** (ekran ustawień) — klawisz
     * globalny nigdy nie dochodzi do ekranu. Sprawdzone przebiegiem, który
     * zamiast spisu hostów otworzył ustawienia.
     */
    public function handle(KeyPress $key): ScreenOutcome
    {
        if ($key->key === Key::F3 && $this->browser->hasListing()) {
            $this->showsHosts = !$this->showsHosts;

            return ScreenOutcome::stay();
        }

        $outcome = $this->showsRemote() ? $this->remote->handle($key) : $this->hosts->handle($key);
        $this->publish();

        return $outcome;
    }

    /**
     * Takt modułu — **posunięcie obu prac i rozstrzygnięcie, co widać**.
     *
     * Kolejność jest tu istotna i **taka, a nie odwrotna**: najpierw sesja (bo
     * to ona mówi, czy host odpowiedział), potem rozstrzygnięcie, co widać (bo
     * to ono zamawia odczyt na świeżo zestawionej sesji), potem posunięcie
     * odczytu, a na końcu kontekst — bo publikuje to, co z całej trójki wyszło.
     *
     * Odwrotna kolejność (odczyt przed rozstrzygnięciem) działała tak samo, ale
     * **o klatkę później**: zamówienie złożone po posunięciu pracy czekałoby na
     * następny takt, żeby ktokolwiek je zauważył.
     */
    public function tick(): void
    {
        $this->session->tick();
        $this->settle();
        $this->browser->tick();

        if ($this->drawn) {
            $this->publish();
        }

        $this->drawn = false;
    }

    /**
     * Uzgadnia postać ze stanem sesji.
     *
     * Trzy przejścia i każde jest **skutkiem czegoś, co stało się poza
     * ekranem**: sesja stanęła (otwórz katalog), sesja zniknęła (zamknij
     * i wróć do spisu), lista przyszła po raz pierwszy (pokaż ją).
     */
    private function settle(): void
    {
        $connected = $this->session->state()->isConnected();
        $host = $this->session->state()->host;

        if ($connected && $host !== null && $this->browser->host()?->equals($host) !== true) {
            $this->browser->open($host);
            $this->showsHosts = false;

            return;
        }

        if (!$connected && $this->browser->host() !== null) {
            // Rozłączenie — także to niechciane — wraca do spisu hostów
            // (rozstrzygnięcie użytkownika ze startu kroku). Powód zerwania
            // mówi pasek stanu, więc lista zostawiona na ekranie nie miałaby
            // czego dodać poza wrażeniem, że wciąż działa.
            $this->browser->close();
            $this->showsHosts = true;
        }
    }

    private function showsRemote(): bool
    {
        return !$this->showsHosts && $this->browser->hasListing();
    }

    /**
     * Publikuje kontekst sesji — **wyłącznie z postaci zdalnej**.
     *
     * Atrybuty jadą razem ze ścieżką, bo odbiorca nie ma jak ich dobrać sam:
     * `lstat` opisałby plik lokalny o tej samej nazwie, a sieć nie pada
     * w rysowaniu klatki. To jest ta część zmiany rdzenia, którą reguła 13
     * nazywa odbiorcą wchodzącym razem z mechanizmem.
     */
    private function publish(): void
    {
        if (!$this->showsRemote()) {
            return;
        }

        $entry = $this->browser->selected();

        $this->state->publishContext(new ModuleContext(
            $this->browser->path()->value ?? '',
            $entry?->name,
            self::kindOf($entry),
            origin: ContextOrigin::Remote,
            originLabel: $this->browser->host()?->label() ?? '',
            selectionBytes: $entry?->isDirectory() === false ? $entry->sizeInBytes : null,
            selectionModifiedAt: $entry?->modifiedAt,
            selectionPermissions: $entry?->permissions,
        ));
    }

    /**
     * Rodzaj wpisu w postaci, którą zna rdzeń — czyli **trzy przypadki na
     * cztery**.
     *
     * Dowiązanie idzie jako plik, a nie katalog, i jest to zgodne z tym, co
     * pokazuje lista: dokąd prowadzi, wie dopiero serwer.
     */
    private static function kindOf(?RemoteEntry $entry): ContextEntryKind
    {
        if ($entry === null) {
            return ContextEntryKind::None;
        }

        return $entry->isDirectory() ? ContextEntryKind::Directory : ContextEntryKind::File;
    }
}
