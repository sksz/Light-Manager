<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Application;

use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Module\Audio\Application\Port\AudioPort;
use LightManager\Module\Audio\Application\Port\PlaylistPort;
use LightManager\Module\Audio\Application\Port\TrackFilesPort;

/**
 * Playlista, która gra dalej sama (krok 45).
 *
 * Jedyny użytkownik taktu modułu (`Application\Module\NeedsTick`) i powód, dla
 * którego ten takt w ogóle powstał: playlista, która nie wie, że utwór się
 * skończył, nie jest playlistą, tylko listą ścieżek. Ekran modułu wystarczyłby
 * dopóty, dopóki się na niego patrzy — a cała rzecz polega na graniu wtedy, gdy
 * użytkownik dawno wrócił do przeglądarki.
 *
 * **Takt jest tani i to widać w `tick()`**: dopóki playlista nie prowadzi gry,
 * kończy się na jednym porównaniu pola; kiedy prowadzi — na drugim porównaniu
 * i jednym pytaniu silnika o to, czy jeszcze gra. Wejścia-wyjścia w takcie nie
 * ma **ani jednego**; pojawia się dopiero w zdarzeniu, które takt zauważa
 * (wczytanie następnego pliku), a to zdarza się raz na utwór, nie raz na klatkę.
 *
 * **Karencja po starcie** jest tu z powodu, którego nie widać z kodu silnika:
 * `play()` wraca, zanim wątek miksujący zdąży odnotować granie, więc takt tuż po
 * starcie zobaczyłby `isPlaying() === false` i uznał świeżo puszczony utwór za
 * skończony — playlista przeskoczyłaby całą listę w ułamku sekundy. Karencję
 * liczymy **czasem klatki podanym z zewnątrz**, nigdy `microtime()` w środku
 * (reguła 11b), i to jest zarazem jedyny użytkownik argumentu `$now` w kontrakcie
 * taktu.
 *
 * Playlista wczytuje się **leniwie**: dopóki nikt o nią nie zapyta, plik na dysku
 * pozostaje nietknięty. Uruchomienie z wyłączonym autostartem nie kosztuje przez
 * to ani jednego odczytu.
 */
final class PlaylistPlayer
{
    /**
     * Ile sekund po starcie takt nie wierzy silnikowi, że nic nie gra.
     *
     * Pół sekundy to piętnaście klatek — dużo więcej, niż potrzebuje wątek audio,
     * a wciąż na tyle mało, że utwór, którego naprawdę nie da się zagrać, oddaje
     * miejsce następnemu, zanim użytkownik zdąży to zauważyć.
     */
    private const START_GRACE_SECONDS = 0.5;

    private ?Playlist $playlist = null;

    /**
     * Czy to playlista prowadzi grę.
     *
     * `false` znaczy „nie pilnuj końca utworu” i obejmuje dwa różne stany naraz:
     * ciszę i **pauzę**. Pauza jest tu ważniejsza: silnik zatrzymany klawiszem
     * także odpowiada „nie gram”, a playlista, która by tego nie odróżniała,
     * przeskakiwałaby do następnego utworu za każdym razem, gdy ktoś wciśnie
     * pauzę.
     */
    private bool $leading = false;

    /** Chwila pierwszego taktu po starcie utworu; `null` — takt jeszcze nie przyszedł. */
    private ?float $playingSince = null;

    private bool $autostartChecked = false;

    /** Zdanie o kłopocie z plikiem playlisty — pokazuje je okno modułu, zamiast pustej listy. */
    private ?string $problem = null;

    public function __construct(
        private readonly AudioPort $audio,
        private readonly PlaylistPort $storage,
        private readonly TrackFilesPort $files,
        private readonly SettingsPort $settings,
        private readonly TranslatorPort $translator,
    ) {
    }

    /**
     * Playlista — wczytana przy pierwszym pytaniu, wraz z migracją i sprawdzeniem
     * dostępności plików.
     */
    public function playlist(): Playlist
    {
        if ($this->playlist !== null) {
            return $this->playlist;
        }

        $loaded = $this->storage->load();
        $playlist = $loaded->playlist;

        if ($loaded->problemKey !== null) {
            $this->problem = $this->translator->translate(
                $loaded->problemKey,
                ['path' => $this->storage->location()],
            );
        }

        if ($loaded->fresh && $playlist->isEmpty()) {
            // Migracja z kroku 36: utwór wskazany kluczem `track` wchodzi na
            // playlistę raz, przy pierwszym uruchomieniu po zmianie. Zapisujemy
            // ją od razu, bo inaczej wróciłby po każdym opróżnieniu listy.
            $playlist->add(PlaylistEntry::of(AudioSettings::legacyTrack($this->settings->current())));
            $this->storage->save($playlist);
        }

        $playlist->refresh($this->files->exists(...));

        return $this->playlist = $playlist;
    }

    /**
     * Zdanie o kłopocie z plikiem playlisty albo `null`, gdy wszystko się udało.
     *
     * Pytanie **nie zabiera** odpowiedzi: kłopot z plikiem trwa, dopóki plik jest
     * uszkodzony, a okno modułu ma go pokazywać za każdym otwarciem — inaczej
     * użytkownik, który akurat patrzył gdzie indziej, nie dowiedziałby się nigdy,
     * dlaczego jego playlista jest pusta.
     */
    public function problem(): ?string
    {
        return $this->problem;
    }

    public function isAvailable(): bool
    {
        return $this->audio->isAvailable();
    }

    public function isPlaying(): bool
    {
        return $this->audio->isPlaying();
    }

    public function mode(): PlaybackMode
    {
        return AudioSettings::mode($this->settings->current());
    }

    /** Pozycja grana teraz albo `null`, gdy playlista nie prowadzi żadnej. */
    public function nowPlaying(): ?PlaylistEntry
    {
        $index = $this->playlist()->playing();

        return $index === null ? null : $this->playlist()->at($index);
    }

    /**
     * Takt modułu — **raz na klatkę, niezależnie od tego, co widać**.
     *
     * Trzy wyjścia i wszystkie tanie: playlista nie prowadzi gry, takt po
     * starcie utworu jest pierwszy, karencja jeszcze trwa. Czwarte pytanie — do
     * silnika — pada wyłącznie wtedy, gdy naprawdę gramy. Autostart sprawdza się
     * raz, przy pierwszym uderzeniu, i **nie zabiera mu reszty**: uruchomienie
     * z autostartem ma zacząć grać w tej samej klatce, w której o tym rozstrzyga.
     */
    public function tick(float $now): void
    {
        if (!$this->autostartChecked) {
            $this->autostartChecked = true;
            $this->autostart();
        }

        if (!$this->leading) {
            return;
        }

        if ($this->playingSince === null) {
            $this->playingSince = $now;

            return;
        }

        if ($now - $this->playingSince < self::START_GRACE_SECONDS) {
            return;
        }

        if ($this->audio->isPlaying()) {
            return;
        }

        $this->advance();
    }

    /**
     * Gra wskazaną pozycję — `Enter` w oknie modułu.
     *
     * @return string|null zdanie o kłopocie albo `null`, gdy gra
     */
    public function play(int $index): ?string
    {
        return $this->start($index);
    }

    /** Pauza. Wolno wołać zawsze — także wtedy, gdy nic nie gra. */
    public function pause(): void
    {
        $this->audio->stop();
        $this->leading = false;
    }

    /**
     * Wznawia to, co grało, a gdy nie grało nic — pierwszą pozycję, którą da się
     * zagrać.
     */
    public function resume(): ?string
    {
        $playlist = $this->playlist();
        $index = $playlist->playing() ?? $playlist->firstPlayable();

        if ($index === null) {
            // Powód prawdziwy wyprzedza ogólny: „playlista jest pusta” jest
            // odpowiedzią niepełną, gdy jest pusta dlatego, że jej pliku nie dało
            // się przeczytać.
            return $this->problem
                ?? $this->text($playlist->isEmpty() ? 'playlist.empty' : 'playlist.nothingPlayable');
        }

        return $this->start($index);
    }

    /**
     * Dopisuje utwór na koniec playlisty i zapisuje ją.
     *
     * @return string|null zdanie o kłopocie albo `null`, gdy pozycja weszła
     */
    public function add(string $path): ?string
    {
        $path = trim($path);

        if ($path === '') {
            return $this->text('track.empty');
        }

        $playlist = $this->playlist();
        $playlist->add(PlaylistEntry::of($path, !$this->files->exists($path)));
        $this->storage->save($playlist);

        return null;
    }

    /** Usuwa pozycję i zapisuje playlistę; utwór grający **nie milknie**. */
    public function remove(int $index): bool
    {
        $playlist = $this->playlist();

        if (!$playlist->removeAt($index)) {
            return false;
        }

        $this->storage->save($playlist);

        return true;
    }

    /** Przestawia pozycję o jedno miejsce i oddaje jej nowy numer. */
    public function move(int $index, int $direction): int
    {
        $playlist = $this->playlist();
        $moved = $playlist->swap($index, $direction);

        if ($moved !== $index) {
            $this->storage->save($playlist);
        }

        return $moved;
    }

    /** Ponowne sprawdzenie, których plików nie ma — przy otwarciu okna modułu. */
    public function refresh(): void
    {
        $this->playlist()->refresh($this->files->exists(...));
    }

    /**
     * Muzyka od startu aplikacji — jeśli użytkownik o to poprosił (D82 nr 7).
     *
     * Pytanie o ustawienie pada **przed** sięgnięciem po playlistę i to jest cała
     * oszczędność: uruchomienie z wyłączonym autostartem nie czyta pliku
     * playlisty w ogóle. Brak rozszerzenia też kończy sprawę wcześniej — nie ma
     * powodu wczytywać listy, której nie ma czym zagrać.
     */
    private function autostart(): void
    {
        if (!AudioSettings::autostarts($this->settings->current()) || !$this->audio->isAvailable()) {
            return;
        }

        $index = $this->playlist()->firstPlayable();

        if ($index !== null) {
            $this->start($index);
        }
    }

    /** Co po skończonym utworze — rozstrzyga tryb odtwarzania. */
    private function advance(): void
    {
        $playlist = $this->playlist();
        $next = $playlist->nextAfter($playlist->playing(), $this->mode());

        if ($next === null) {
            // Cisza zapada **wraz z kursorem**: pozycja wskazywana po skończonym
            // utworze znaczyłaby „gram to”, a nic już nie gra. Pauza kursora nie
            // rusza i to jest cała różnica między nią a końcem playlisty —
            // wznowienie po pauzie ma wrócić tam, gdzie stanęło.
            $playlist->usePlaying(null);
            $this->leading = false;
            $this->playingSince = null;

            return;
        }

        $this->start($next);
    }

    /**
     * Puszcza pozycję o podanym numerze.
     *
     * Nieudany start **oznacza pozycję jako brakującą** i przerywa prowadzenie:
     * plik mógł zniknąć między jednym utworem a drugim, a lista, która pokazuje
     * go jako obecny, kłamie. Przeliczenie dostępności jest tu wejściem-wyjściem
     * i dlatego stoi w drodze nieudanej, a nie w takcie.
     */
    private function start(int $index): ?string
    {
        $playlist = $this->playlist();
        $entry = $playlist->at($index);

        if ($entry === null) {
            return $this->text('playlist.empty');
        }

        $mode = $this->mode();
        $problem = $this->audio->play(
            $entry->path,
            AudioSettings::volume($this->settings->current()),
            $mode->repeatsInEngine(),
        );

        if ($problem !== null) {
            $playlist->refresh($this->files->exists(...));
            $this->leading = false;
            $this->playingSince = null;

            return $problem;
        }

        $playlist->usePlaying($index);
        $this->leading = true;
        $this->playingSince = null;

        return null;
    }

    /** @param array<string, string> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate('module.' . AudioSettings::ID . '.' . $key, $parameters);
    }
}
