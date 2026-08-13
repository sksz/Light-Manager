<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Presentation;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\UseCase\ChangeModuleSettingUseCase;
use LightManager\Domain\ValueObject\Message;
use LightManager\Domain\ValueObject\Preview;
use LightManager\Module\FileInfo\Application\Dto\ChecksumState;
use LightManager\Module\FileInfo\Application\Dto\DiskUsageState;
use LightManager\Module\FileInfo\Application\Dto\EntryDescription;
use LightManager\Module\FileInfo\Application\Dto\EntryKind;
use LightManager\Module\FileInfo\Application\Dto\TextAnchor;
use LightManager\Module\FileInfo\Application\Dto\TextWindow;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Module\FileInfo\Application\Port\ChecksumPort;
use LightManager\Module\FileInfo\Application\UseCase\InspectSelectedEntryUseCase;
use LightManager\Module\FileInfo\Application\UseCase\MeasureDiskUsageUseCase;
use LightManager\Module\FileInfo\Application\UseCase\PreviewEntryUseCase;
use LightManager\Module\FileInfo\Application\UseCase\PreviewTextUseCase;
use LightManager\Presentation\Cli\LoopState;

/**
 * Opisywany wpis i praca, która nad nim trwa.
 *
 * Klasa powstała z tego samego powodu, co `BrowserState` w kroku 21: **stan
 * przeżywający klatkę ma jedno miejsce**, a ekran zostaje przy rysowaniu
 * i klawiszach. Bez niej ekran robiłby cztery rzeczy naraz — rysował, obsługiwał
 * klawisze, składał opis i prowadził liczenie sumy kontrolnej — a test tego
 * ostatniego musiałby przechodzić przez rysowanie klatki.
 *
 * **Suma kontrolna liczy się po kawałku na klatkę** i to ta klasa pilnuje trzech
 * rzeczy, bez których byłaby wyciekiem, a nie funkcją:
 *
 * 1. zmiana zaznaczenia **przerywa** liczenie poprzedniego pliku,
 * 2. zamknięcie ekranu (`reset()`) też,
 * 3. liczenie w ogóle nie zaczyna się dla pliku większego od limitu — i mówi
 *    dlaczego, zamiast milczeć.
 *
 * Od kroku 26 dochodzi **druga praca na tych samych zasadach**: zajętość katalogu
 * liczona poleceniem `du` w procesie tłowym. Trzy punkty powyżej obowiązują ją co
 * do joty, a stawka jest wyższa o rząd wielkości — zapomniane przerwanie zostawia
 * po sobie nie otwarty uchwyt do pliku, tylko **działający proces**. Dlatego
 * uchwyt pracy trzyma ta klasa, a nie przypadek użycia: właścicielem jest ten,
 * kto wie, kiedy zaznaczenie się zmienia i kiedy ekran się zamyka.
 *
 * Obie prace nie mogą trwać naraz i nie jest to przypadek: sumę liczymy wyłącznie
 * dla zwykłych plików, zajętość wyłącznie dla katalogów. Pasek postępu ma więc
 * zawsze najwyżej jednego nadawcę.
 */
final class FileInfoState
{
    /**
     * Ile bajtów czytamy w jednej klatce.
     *
     * Cztery mebibajty to około 3 ms na typowym dysku — mniej niż jedna dziesiąta
     * budżetu taktu, a przy tym 120 MB/s, więc suma pliku stumegabajtowego
     * kończy się w niecałą sekundę. Liczba jest kompromisem między „nie zatnij
     * klatki” a „nie licz do wieczora” i jedno i drugie ma znaczenie.
     */
    private const CHUNK_BYTES = 4 * 1024 * 1024;

    /**
     * Ile znaków jednego wiersza wolno przeczytać, żeby zmierzyć jego długość.
     *
     * Potrzebne wyłącznie przy `End`: żeby stanąć na końcu pliku będącego jedną
     * długą linią, trzeba wiedzieć, ile ta linia ma linijek. Liczba jest równa
     * sufitowi jednego odczytu w usłudze — powyżej niego i tak nic nie przyjdzie,
     * więc skok na koniec wiersza dłuższego niż ćwierć mebibajta zatrzyma się
     * tam, dokąd sięga odczyt. To ta sama granica, którą usługa ma od kroku 29,
     * i tak samo jak tam jest bezpiecznikiem, nie regulatorem.
     */
    private const TAIL_CHARACTERS = 256 * 1024;

    /** Ścieżka, dla której policzono opis; `null` — jeszcze niczego nie liczono. */
    private ?string $path = null;

    private ?EntryDescription $description = null;

    /** Ostatni kontekst sesji — potrzebny górnemu pasowi klatki i podglądowi. */
    private ModuleContext $context;

    /** Uchwyt trwającego pomiaru zajętości; `null` — nic nie zamówiono. */
    private ?BackgroundHandle $diskUsageHandle = null;

    private DiskUsageState $diskUsage;

    /**
     * Miejsce w pliku, od którego zaczyna się podgląd tekstu — zawsze **początek
     * wiersza**.
     *
     * Kotwica nie stoi nigdy w środku wiersza i to jest rozstrzygnięcie, które
     * upraszcza całą resztę: przewijanie liczy się w linijkach panelu, a linijka
     * bywa środkiem zawiniętego wiersza — ale zamiast przesuwać kotwicę o pół
     * wiersza (co przy UTF-16 znaczyłoby mapować znaki na bajty), zostaje ona na
     * początku wiersza, a ile jego linijek pominąć, mówi `$textRowSkip`.
     */
    private TextAnchor $textAnchor;

    /**
     * Ile **linijek panelu** pominąć na początku pierwszego wiersza okna.
     *
     * Zero znaczy „okno zaczyna się od pełnego wiersza”. Wartość większa powstaje
     * wyłącznie przy zawijaniu: wtedy jeden wiersz pliku zajmuje kilka linijek,
     * a przewinięcie o linijkę potrafi zatrzymać się w jego środku.
     */
    private int $textRowSkip = 0;

    /**
     * Przewinięcie zamówione klawiszem, jeszcze nierozliczone — **w linijkach
     * panelu**, nie w wierszach pliku i nie w panelach.
     *
     * Zamówienie **czeka na rysowanie** i to jest jedyne miejsce, w którym można
     * je rozliczyć uczciwie: linijka to tyle znaków, ile mieści panel, a rozmiar
     * okna zmienia się między klatkami (reguła 11f) i nie wolno go pamiętać. Ten
     * sam rozdział na „zamówienie” i „rozliczenie przy geometrii” robi
     * `ScrollWindow` od kroku 18 — `scrollBy()` zapisuje, `clamp()` rozstrzyga.
     *
     * Jednostką była do 2026-08-12 **strona**, a przewijanie szło po wierszach
     * pliku: przy zawijaniu `PgDn` przeskakiwał tyle wierszy, ile panel ma
     * linijek, choć wierszy widać było mniej — i gubił treść. Linijka panelu jest
     * jedyną jednostką, w której „przewiń o tyle, ile widać” znaczy to samo dla
     * czytającego plik i dla patrzącego na ekran.
     */
    private int $textScroll = 0;

    /** Zamówienie przewinięcia o całe panele — zamieniane na linijki przy rozliczeniu. */
    private int $textPanels = 0;

    /** Zamówienie powrotu na początek pliku — rozliczane tak samo jak przewinięcie. */
    private bool $textRewind = false;

    /** Zamówienie skoku na koniec pliku. */
    private bool $textToEnd = false;

    /**
     * Czy numer wiersza kotwicy jest znany.
     *
     * Skok na koniec pliku (`End`) sadza kotwicę **po bajcie**, a numeru wiersza
     * z bajtu wyczytać się nie da — poznanie go kosztowałoby przejście przez cały
     * plik, czyli dokładnie to, czego ten moduł nie robi (D58). Numery znikają
     * więc do najbliższego `Home`, zamiast kłamać.
     */
    private bool $textNumbered = true;

    public function __construct(
        private readonly InspectSelectedEntryUseCase $inspect,
        private readonly PreviewEntryUseCase $previews,
        private readonly ChecksumPort $checksums,
        private readonly MeasureDiskUsageUseCase $diskUsageJob,
        private readonly SettingsPort $settings,
        private readonly PreviewTextUseCase $texts,
        private readonly ChangeModuleSettingUseCase $changeSetting,
        private readonly LoopState $loop,
    ) {
        $this->context = new ModuleContext();
        $this->diskUsage = DiskUsageState::idle();
        $this->textAnchor = new TextAnchor();
    }

    public function context(): ModuleContext
    {
        return $this->context;
    }

    public function description(): ?EntryDescription
    {
        return $this->description;
    }

    public function checksum(): ChecksumState
    {
        return $this->checksums->state();
    }

    public function diskUsage(): DiskUsageState
    {
        return $this->diskUsage;
    }

    /**
     * Miniatura opisywanego pliku albo `null`.
     *
     * Liczy się **leniwie**, przy pytaniu, bo pyta o nią prawy panel podziału,
     * a ten powstaje tylko w dostatecznie szerokim oknie.
     */
    public function preview(): ?Preview
    {
        $description = $this->description;

        return $description === null
            ? null
            : $this->previews->execute($this->path, $description->sizeInBytes);
    }

    /**
     * Treść pliku tekstowego widoczna w panelu o zadanej geometrii.
     *
     * Liczy się **leniwie i z pełną znajomością prostokąta**, bo bez niego nie
     * wiadomo, ile czytać — a czytamy dokładnie tyle, ile pokażemy (krok 29).
     * Stąd też bierze się rozliczenie zamówionego przewinięcia: dopiero tutaj
     * wiadomo, czym jest „panel w dół”.
     *
     * `null` znaczy „podglądu tekstu tu nie ma” i zostawia dawny napis
     * o jego braku; okno z powodem odmowy mówi, dlaczego treści nie widać.
     */
    public function textWindow(int $rows, int $columns): ?TextWindow
    {
        $description = $this->description;
        $path = $this->path;

        if ($description === null || $path === null || $rows < 1 || $columns < 1) {
            return null;
        }

        $this->settleTextScroll($path, $description, $rows, $columns);

        $window = $this->read(
            $path,
            $description,
            $this->textAnchor,
            $rows,
            $this->charactersFor($rows, $columns),
        );

        return $window === null ? null : $this->fromRow($window, $columns);
    }

    /**
     * Ile znaków jednego wiersza pliku panel jest w stanie pokazać — i tyle
     * dokładnie czytamy, bo reszta byłaby czytaniem dla nikogo.
     *
     * Z zawijaniem jest to cały prostokąt **plus pominięte linijki**: gdy okno
     * zaczyna się w środku zawiniętego wiersza, trzeba przeczytać także tę jego
     * część, której nie widać — inaczej reszty nie byłoby z czego wyciąć. Bez
     * zawijania wystarczy jedna linijka, bo dalsza część wiersza i tak nie ma
     * gdzie się pokazać.
     */
    private function charactersFor(int $rows, int $columns): int
    {
        return $this->textWrap() ? ($this->textRowSkip + $rows) * $columns : $columns;
    }

    private function read(
        string $path,
        EntryDescription $description,
        TextAnchor $anchor,
        int $lines,
        int $characters,
    ): ?TextWindow {
        return $this->texts->execute(
            $path,
            $description->kind,
            $description->content,
            $anchor,
            $lines,
            $characters,
        );
    }

    /** Ile linijek panelu zajmuje wiersz pliku po zawinięciu. */
    private function rowsOf(string $line, int $columns): int
    {
        if (!$this->textWrap() || $columns < 1) {
            return 1;
        }

        return max(1, (int) ceil(mb_strlen($line) / $columns));
    }

    /**
     * Okno przycięte do pierwszej **widocznej linijki**.
     *
     * Pominięte linijki odcina się z pierwszego wiersza znakami, a nie osobnym
     * polem komponentu: `TextView` zawija to, co dostanie, więc wiersz podany bez
     * początku zawija się dokładnie od tego miejsca, od którego ma być widoczny.
     * Rdzeń nie musi przez to wiedzieć nic o pomijaniu linijek.
     */
    private function fromRow(TextWindow $window, int $columns): TextWindow
    {
        if ($this->textRowSkip < 1 || $window->lines === [] || !$this->textWrap()) {
            return $window;
        }

        $lines = $window->lines;
        $lines[0] = mb_substr($lines[0], $this->textRowSkip * $columns);

        return TextWindow::of($lines, $window->starts, $window->anchor, $window->next, $window->fileBytes);
    }

    /**
     * Czy podgląd zawija wiersze.
     *
     * Do poprawki z 2026-08-12 była to **prywatna flaga w pamięci**, przełączana
     * wyłącznie `Alt`+`Z` i ginąca wraz z uruchomieniem. Dziś jest to pozycja
     * ustawień modułu, czytana co klatkę — tak samo jak numery wierszy obok.
     */
    public function textWrap(): bool
    {
        return FileInfoSettings::textWrap($this->settings->current());
    }

    /** Numer pierwszego widocznego wiersza albo `null`, gdy numerów nie pokazujemy. */
    public function textFirstNumber(): ?int
    {
        if (!$this->textNumbered || !FileInfoSettings::lineNumbers($this->settings->current())) {
            return null;
        }

        return $this->textAnchor->line;
    }

    /**
     * Przełączenie zawijania — **tą samą drogą, co pozycja w zakładce ustawień**.
     *
     * Obie drogi kończą w jednym kluczu pliku konfiguracyjnego, wzorem klawisza
     * `.` w przeglądarce (D40). Zmiana idzie przez stan pętli, a nie tylko przez
     * port: ekran ustawień czyta ustawienia **stamtąd**, więc zapis pomijający go
     * dawałby zakładkę pokazującą starą wartość — i cofającą tę nową przy
     * najbliższej zmianie czegokolwiek obok.
     */
    public function toggleTextWrap(): ?Message
    {
        [$settings, $message] = $this->changeSetting->shift(
            $this->loop->settings(),
            FileInfoSettings::ID,
            FileInfoSettings::textWrapDeclaration(),
            1,
        );

        $this->loop->applySettings($settings);

        return $message;
    }

    /** Zamówienie przewinięcia podglądu o `$rows` linijek panelu; ujemne — w górę. */
    public function scrollTextRows(int $rows): void
    {
        $this->textScroll += $rows;
    }

    /**
     * Zamówienie przewinięcia o `$panels` paneli.
     *
     * Osobno od linijek, bo panel ma tyle linijek, ile wierszy ma prostokąt —
     * a tego nie wie ani klawisz, ani ekran. Zamiana na linijki dzieje się przy
     * rozliczeniu, tam gdzie geometria jest już znana.
     */
    public function scrollTextPanels(int $panels): void
    {
        $this->textPanels += $panels;
    }

    public function rewindText(): void
    {
        $this->textRewind = true;
        $this->textToEnd = false;
        $this->textPanels = 0;
        $this->textScroll = 0;
    }

    /** Skok na koniec pliku — rozliczany przy geometrii, jak każde przewinięcie. */
    public function forwardTextToEnd(): void
    {
        $this->textToEnd = true;
        $this->textRewind = false;
        $this->textPanels = 0;
        $this->textScroll = 0;
    }

    /**
     * Rozliczenie **jednego** panelu na klatkę.
     *
     * Jeden, a nie wszystkie zaległe, i to jest wzorzec z D46 zastosowany do
     * przewijania: praca przypadająca na klatkę ma być ograniczona z góry.
     * Przytrzymany `PgDn` przewija trzydzieści paneli na sekundę, więc nikt tej
     * granicy nie poczuje, a plik bez ani jednego znaku nowej linii nie zatrzyma
     * klatki serią odczytów.
     */
    private function settleTextScroll(string $path, EntryDescription $description, int $rows, int $columns): void
    {
        if ($this->textRewind) {
            $this->textRewind = false;
            $this->textAnchor = new TextAnchor();
            $this->textRowSkip = 0;
            $this->textNumbered = true;

            return;
        }

        // Panele zamieniają się na linijki dopiero tutaj — dopiero tutaj wiadomo,
        // ile linijek ma panel.
        $this->textScroll += $this->textPanels * $rows;
        $this->textPanels = 0;

        if ($this->textToEnd) {
            $this->textToEnd = false;
            $this->textScroll = 0;
            $this->jumpTextToEnd($path, $description, $rows, $columns);

            return;
        }

        if ($this->textScroll === 0) {
            return;
        }

        // Jeden panel na klatkę — granica z D46 przeniesiona na nową jednostkę.
        // Przytrzymany `PgDn` przewija trzydzieści paneli na sekundę, więc nikt
        // jej nie poczuje, a plik bez ani jednego znaku nowej linii nie zatrzyma
        // klatki serią odczytów.
        $step = max(-$rows, min($rows, $this->textScroll));
        $this->textScroll -= $step;

        $moved = $step > 0
            ? $this->scrollTextDown($path, $description, $step, $rows, $columns)
            : $this->scrollTextUp($path, $description, -$step, $rows, $columns);

        // Kraniec pliku zatrzymuje przewijanie **i kasuje zaległość**: bez tego
        // przytrzymany `PgDn` zostawiałby na dole długu, który odegrałby się przy
        // pierwszym przewinięciu w górę.
        if (!$moved) {
            $this->textScroll = 0;
        }
    }

    /**
     * Przewinięcie w dół o `$step` linijek panelu.
     *
     * Rachunek idzie po **wczytanych wierszach**, a nie po pliku: każdy z nich
     * zajmuje tyle linijek, na ile się zawinie, a szukana linijka wypada w którymś
     * z nich — wtedy kotwica staje na początku **tego** wiersza, a reszta drogi
     * zostaje w `$textRowSkip`. Wierszy czytamy `$step + 1`, bo tyle najwyżej
     * może objąć `$step` linijek: każdy wiersz to co najmniej jedna linijka.
     */
    private function scrollTextDown(
        string $path,
        EntryDescription $description,
        int $step,
        int $rows,
        int $columns,
    ): bool {
        $window = $this->read(
            $path,
            $description,
            $this->textAnchor,
            $step + 1,
            $this->charactersFor($rows, $columns),
        );

        if ($window === null || $window->lines === []) {
            return false;
        }

        $remaining = $this->textRowSkip + $step;

        foreach ($window->lines as $index => $line) {
            $height = $this->rowsOf($line, $columns);

            if ($remaining < $height) {
                return $this->putTextAnchor($window, $index, $remaining);
            }

            $remaining -= $height;
        }

        // Wiersze się skończyły, czyli skończył się plik: stajemy na ostatnim,
        // zamiast wyjeżdżać w pustkę pod jego końcem.
        $last = count($window->lines) - 1;

        return $this->putTextAnchor($window, $last, $this->rowsOf($window->lines[$last], $columns) - 1);
    }

    /**
     * Przewinięcie w górę o `$step` linijek panelu.
     *
     * Linijki pominięte w pierwszym wierszu oddajemy bez sięgania do pliku; gdy
     * się skończą, trzeba wejść **nad** kotwicę, a tam prowadzi tylko jedna droga:
     * cofnąć się o tyle wierszy, ile brakuje linijek (każdy wiersz to co najmniej
     * jedna, więc to zawsze dość daleko), przeczytać je i odliczyć linijki od
     * końca. Dwa odczyty i ani jednego zgadywania.
     */
    private function scrollTextUp(
        string $path,
        EntryDescription $description,
        int $step,
        int $rows,
        int $columns,
    ): bool {
        if ($this->textRowSkip >= $step) {
            $this->textRowSkip -= $step;

            return true;
        }

        $characters = $this->charactersFor($rows, $columns);
        $needed = $step - $this->textRowSkip;
        $back = $this->texts->previous($path, $this->textAnchor, $needed, $characters);

        if ($back->byte >= $this->textAnchor->byte) {
            $moved = $this->textRowSkip > 0;
            $this->textRowSkip = 0;

            return $moved;
        }

        $window = $this->read($path, $description, $back, $needed, $characters);

        if ($window === null) {
            return false;
        }

        $heights = [];
        $above = 0;

        foreach ($window->lines as $index => $line) {
            // Wiersze zza kotwicy do rachunku nie należą: `previous()` potrafi
            // cofnąć się o mniej, niż poproszono, a odczyt oddaje wtedy także to,
            // co leży już **pod** oknem.
            if (($window->starts[$index] ?? PHP_INT_MAX) >= $this->textAnchor->byte) {
                break;
            }

            $heights[] = $this->rowsOf($line, $columns);
            $above += $heights[count($heights) - 1];
        }

        if ($heights === []) {
            return false;
        }

        $target = max(0, $above - $needed);

        foreach ($heights as $index => $height) {
            if ($target < $height) {
                return $this->putTextAnchor($window, $index, $target);
            }

            $target -= $height;
        }

        return $this->putTextAnchor($window, 0, 0);
    }

    /**
     * Koniec pliku: kotwica o panel przed nim, a nadmiar linijek dopycha zwykłe
     * przewijanie w dół.
     *
     * Numer wiersza **przestaje być znany** i to jest cena skoku po bajcie —
     * jedyną alternatywą byłoby przejście przez cały plik, czyli to, czego ten
     * moduł nie robi (D58).
     */
    private function jumpTextToEnd(string $path, EntryDescription $description, int $rows, int $columns): void
    {
        $characters = $this->charactersFor($rows, $columns);
        $current = $this->read($path, $description, $this->textAnchor, $rows, $characters);

        if ($current === null || $current->fileBytes <= 0) {
            return;
        }

        $anchor = new TextAnchor($current->fileBytes);
        $needed = $rows;

        // Cofamy się **wiersz po wierszu**, aż uzbiera się panel linijek. Pętla
        // ma z góry tyle obrotów, ile panel ma linijek, bo każdy wiersz to co
        // najmniej jedna — a plik o jednym wierszu kończy ją w pierwszym.
        for ($step = 0; $step < $rows; ++$step) {
            $back = $this->texts->previous($path, $anchor, 1, $characters);

            if ($back->byte >= $anchor->byte) {
                break;
            }

            $height = $this->heightOf($path, $description, $back, $rows, $columns, $characters);
            $anchor = $back;

            if ($height >= $needed) {
                $this->textAnchor = $back;
                $this->textRowSkip = $height - $needed;
                $this->textNumbered = false;

                return;
            }

            $needed -= $height;
        }

        // Plik krótszy od panelu: jego koniec widać z początku, a numer wiersza
        // jest wtedy znany, bo nigdzie nie skoczyliśmy.
        $this->textAnchor = $anchor;
        $this->textRowSkip = 0;
        $this->textNumbered = $anchor->byte === 0;
    }

    /**
     * Ile linijek zajmuje wiersz zaczynający się od tej kotwicy.
     *
     * Mierzy się go **budżetem panelu**, a dopiero wiersz, który cały panel
     * wypełnia, drugi raz — budżetem pełnym. Bez tego rozdziału skok na koniec
     * czytałby ćwierć mebibajta na każdy wiersz z osobna; bez drugiego pomiaru
     * plik będący jedną długą linią kończyłby się tam, gdzie się zaczyna, bo
     * wiersz przycięty budżetem wygląda dokładnie na wysoki na panel.
     */
    private function heightOf(
        string $path,
        EntryDescription $description,
        TextAnchor $anchor,
        int $rows,
        int $columns,
        int $characters,
    ): int {
        $window = $this->read($path, $description, $anchor, 1, $characters);

        if ($window === null || $window->lines === []) {
            return 1;
        }

        $height = $this->rowsOf($window->lines[0], $columns);

        if ($height < $rows) {
            return $height;
        }

        $full = $this->read($path, $description, $anchor, 1, self::TAIL_CHARACTERS);

        return $full === null || $full->lines === [] ? $height : $this->rowsOf($full->lines[0], $columns);
    }

    /** Kotwica na wierszu okna wraz z pominiętymi linijkami; `false` — nic się nie ruszyło. */
    private function putTextAnchor(TextWindow $window, int $line, int $skip): bool
    {
        $anchor = $window->anchorOf($line);

        if ($anchor === null) {
            return false;
        }

        $skip = max(0, $skip);
        $moved = !$anchor->equals($this->textAnchor) || $skip !== $this->textRowSkip;

        $this->textAnchor = $anchor;
        $this->textRowSkip = $skip;

        return $moved;
    }

    /**
     * Nowy kontekst sesji. Opis liczy się **wyłącznie przy zmianie ścieżki** —
     * kontekst przychodzi co klatkę, a za opisem stoi proces potomny (`file`).
     */
    public function useContext(ModuleContext $context): void
    {
        $this->context = $context;
        $path = $context->selectionPath();

        if ($path === $this->path && $this->path !== null) {
            return;
        }

        // Zmiana zaznaczenia przerywa obie prace nad poprzednim wpisem. Bez tego
        // przewinięcie listy zostawiałoby za sobą otwarte uchwyty do plików,
        // z których każdy nadal byłby czytany po kawałku w każdej klatce — a od
        // kroku 26 także **działające procesy** `du`, po jednym na każdy katalog,
        // przez który kursor przeszedł.
        $this->checksums->stop();
        $this->stopDiskUsage();
        $this->rewindTextPreview();
        $this->path = $path;
        $this->description = $this->inspect->execute($context);
    }

    /**
     * Kawałek pracy przypadający na tę klatkę. Gdy nic nie trwa — nie robi nic.
     *
     * Dwa rodzaje pracy i dwa różne czasowniki, choć wołane w tym samym miejscu:
     * sumę kontrolną **posuwamy** (czytamy kolejne bajty), a pomiar zajętości
     * tylko **doglądamy** — pracę wykonuje potomek, a my sprawdzamy, czy już
     * skończył, i opróżniamy jego potoki.
     */
    public function advance(): void
    {
        if ($this->checksums->state()->isRunning()) {
            $this->checksums->advance(self::CHUNK_BYTES);
        }

        if ($this->diskUsageHandle !== null && $this->diskUsage->isRunning()) {
            $this->diskUsage = $this->diskUsageJob->read($this->diskUsageHandle);
        }
    }

    /**
     * Klawisz „policz sumę kontrolną”.
     *
     * Odmowy są trzy i każda mówi **dlaczego**: wyłączone w ustawieniach, wpis
     * nie jest zwykłym plikiem, plik przekracza limit rozmiaru. Milczące
     * nierozpoczęcie pracy byłoby najgorszą z możliwych odpowiedzi — użytkownik
     * nacisnął klawisz i ma prawo wiedzieć, co się stało.
     *
     * @return string|null klucz katalogu z powodem odmowy; `null` — praca ruszyła
     */
    public function startChecksum(): ?string
    {
        $settings = $this->settings->current();
        $description = $this->description;

        if (!FileInfoSettings::checksum($settings)) {
            return 'module.file-info.checksum.disabled';
        }

        if ($description === null || $description->kind !== EntryKind::File) {
            return 'module.file-info.checksum.notAFile';
        }

        if ($description->sizeInBytes > FileInfoSettings::checksumLimitBytes($settings)) {
            return 'module.file-info.checksum.tooLarge';
        }

        $path = $this->path;

        if ($path === null) {
            return 'module.file-info.checksum.notAFile';
        }

        $this->checksums->begin($path);

        return null;
    }

    /**
     * Klawisz „policz zajętość na dysku”.
     *
     * Odmowy są dwie i obie mówią dlaczego — jak przy sumie kontrolnej. Limitu
     * rozmiaru wśród nich nie ma i nie ma go z czego wziąć: przed policzeniem nie
     * wiadomo, jak duże jest drzewo, a to właśnie jest pytanie, które zadajemy.
     * Rolę hamulca gra tu limit **czasu**, nie rozmiaru.
     *
     * Wpis niebędący katalogiem odpada, bo dla zwykłego pliku odpowiedź stoi już
     * w sekcji „Rozmiar”: bloki i-węzła razy 512 to dokładnie zajętość na dysku,
     * policzona z `lstat` bez uruchamiania czegokolwiek. Proces potomny po liczbę,
     * którą użytkownik ma na ekranie, byłby kosztem bez treści.
     *
     * @return string|null klucz katalogu z powodem odmowy; `null` — praca ruszyła
     */
    public function startDiskUsage(): ?string
    {
        $description = $this->description;
        $path = $this->path;

        if (!FileInfoSettings::diskUsage($this->settings->current())) {
            return 'module.file-info.diskUsage.disabled';
        }

        if ($path === null || $description === null || $description->kind !== EntryKind::Directory) {
            return 'module.file-info.diskUsage.notADirectory';
        }

        $this->stopDiskUsage();
        $this->diskUsageHandle = $this->diskUsageJob->begin(
            $path,
            FileInfoSettings::backgroundTimeout($this->settings->current()),
        );
        $this->diskUsage = DiskUsageState::running();

        return null;
    }

    /**
     * Sprzątanie: przerywa obie prace i zapomina opis.
     *
     * Wołane przy każdym otwarciu ekranu, a nie tylko przy zamknięciu — powód
     * jest ten sam, co przed krokiem 25: zmiana ustawień (limit czasu, argumenty,
     * format czasu) ma być widoczna od razu, a nie dopiero po przejściu na inny
     * plik.
     */
    public function reset(): void
    {
        $this->checksums->stop();
        $this->stopDiskUsage();
        $this->rewindTextPreview();
        $this->path = null;
        $this->description = null;
    }

    /**
     * Podgląd wraca na początek pliku — przy zmianie zaznaczenia i przy
     * zamknięciu ekranu.
     *
     * To ta sama zasada, którą `ScrollWindow::useContext()` stosuje do listy od
     * kroku 18: wejście na inny wpis nie ma powodu zaczynać się w połowie
     * poprzedniego. Sposób oglądania (zawijanie) zostaje — zmienia się plik,
     * a nie to, jak użytkownik chce patrzeć.
     */
    private function rewindTextPreview(): void
    {
        $this->textAnchor = new TextAnchor();
        $this->textRowSkip = 0;
        $this->textScroll = 0;
        $this->textPanels = 0;
        $this->textRewind = false;
        $this->textToEnd = false;
        $this->textNumbered = true;
    }

    /**
     * Przerywa pomiar i zapomina uchwyt.
     *
     * Wołane z trzech miejsc — zmiana zaznaczenia, `reset()` i nowe zamówienie —
     * bo w każdym z nich niedopilnowanie zostawiłoby po sobie proces, o którym
     * nikt już nie pamięta. Wolno je wołać, gdy nic nie trwa.
     */
    private function stopDiskUsage(): void
    {
        if ($this->diskUsageHandle !== null) {
            $this->diskUsageJob->stop($this->diskUsageHandle);
            $this->diskUsageHandle = null;
        }

        $this->diskUsage = DiskUsageState::idle();
    }
}
