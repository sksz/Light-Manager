<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Overlay;

use Closure;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Rect;
use LightManager\Domain\ValueObject\Message;
use LightManager\Presentation\Ui\Component\Dialog;
use LightManager\Presentation\Ui\Component\TextInput;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\NeedsTime;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * Okno o jedno słowo: napis wpisany przez użytkownika (krok 41).
 *
 * Trzecie okno rdzenia po oknie komend i oknie potwierdzenia, i **żadnego
 * komponentu nie powołuje**: to `Dialog` z kroku 18 i `TextInput` z kroku 19,
 * a nowy jest wyłącznie sposób ich użycia — dokładnie jak w kroku 28.
 *
 * Wynik wraca **domknięciem** (D56): czynność przychodzi w konstruktorze
 * i oddaje `OverlayOutcome`. Kontrakt okna nie rośnie ani o pole, a okno nie wie,
 * co uruchamia — może więc stać w rdzeniu, choć jedynym dzisiejszym wołającym
 * jest przeglądarka plików. Kroki 42 (nazwa zastępcza przy kolizji) i 44 (nazwa
 * przy przywracaniu z kosza) poprosiły o to samo okno i to one przesądziły o jego
 * miejscu (D75, rozstrzygnięcie 8).
 *
 * **Domknięcie oddaje `OverlayOutcome`, a nie `?Message`, od kroku 42** — tą samą
 * drogą i z tego samego powodu, którym krok 41 przeprowadził `ConfirmOverlay`:
 * okno stoi odtąd **w środku** łańcucha okien i musi umieć powiedzieć, co ma
 * stanąć po nim. Ścieżka wpisana w to okno zaczyna pracę dłuższą od klatki, a ta
 * pokazuje się oknem postępu — więc „zamknij i otwórz” musi stać się naraz
 * (`OverlayOutcome::replace()`; stos ma jedno piętro).
 *
 * **Napis jest treścią, nie ścieżką.** Okno przyjmuje dowolne znaki, także
 * ukośnik, i nie ocenia ich w ogóle: poprawność nazwy zna ten, kto wie, czym jest
 * nazwa wpisu — czyli moduł (D75, rozstrzygnięcie 2). Okno, które by ją
 * sprawdzało, byłoby rdzeniem uczącym się, czym jest plik.
 *
 * `Enter` zatwierdza, `Esc` odmawia (P3). `Enter` na pustym polu **nie robi
 * nic**: nie ma czego zatwierdzić, a wywołanie czynności z pustym napisem
 * przerzucałoby na wołającego pilnowanie rzeczy widocznej tutaj.
 */
final class PromptOverlay implements OverlayInterface, NeedsTime
{
    private const MARGIN_ROWS = 2;

    private const MARGIN_COLUMNS = 4;

    private const PADDING_COLUMNS = 2;

    /** Obwódka, tytuł, wiersz wpisywania, obwódka. */
    private const CHROME_ROWS = 4;

    /** Szerokość, na jaką okno się otwiera, gdy tytuł i treść są krótsze. */
    private const ROOM_COLUMNS = 36;

    /**
     * Górna granica szerokości — **zobaczona w prawdziwym terminalu**, nie
     * wyliczona z góry (krok 41).
     *
     * Bez niej sam tytuł dyktował rozmiar okna: „Nowy katalog w /tmp/…” z długą
     * ścieżką rozdmuchiwał je na całą szerokość klatki, a okno pytające o jedno
     * słowo wyglądało jak drugi panel. Tytuł dłuższy od tej granicy ucina
     * `Dialog`, tak jak ucina każdy inny.
     */
    private const MAX_COLUMNS = 64;

    private readonly TextInput $input;

    /**
     * @param string                      $titleKey   klucz katalogu, nie napis
     * @param array<string, string>       $parameters dane do podstawienia w tytule
     * @param string                            $initial  treść początkowa — nazwa bieżąca albo pustka
     * @param Closure(string): OverlayOutcome   $onAccept czynność po `Enter`; oddaje skutek okna
     * @param bool                              $masked   czy treść ma być ukryta (krok 48).
     *                                                    Pytanie o hasło do zdalnego hosta jest
     *                                                    pierwszym i jedynym dzisiejszym powodem;
     *                                                    okno **nie zmienia się** poza tym niczym,
     *                                                    bo hasło jest napisem jak każdy inny
     */
    public function __construct(
        private readonly string $titleKey,
        private readonly array $parameters,
        string $initial,
        private readonly Closure $onAccept,
        private readonly TranslatorPort $translator,
        string $promptKey = 'prompt.name',
        bool $masked = false,
    ) {
        $this->input = new TextInput($translator->translate($promptKey), $masked);
        $this->input->useValue($initial);
    }

    public function id(): string
    {
        return 'prompt';
    }

    /** Zegar dla karetki — okno przekazuje go dalej, bo to pole nią mruga. */
    public function useTime(float $now): void
    {
        $this->input->useTime($now);
    }

    /** Okno staje pośrodku, jak pytanie: wpisywanie nazwy dotyczy wpisu, nie listy. */
    public function bounds(int $rows, int $columns): Rect
    {
        $width = min(max(mb_strlen($this->title()), self::ROOM_COLUMNS), self::MAX_COLUMNS)
            + 2 * self::PADDING_COLUMNS;
        $height = min(self::CHROME_ROWS, max(1, $rows - self::MARGIN_ROWS));
        $width = min($width, max(1, $columns - self::MARGIN_COLUMNS));

        return new Rect(
            max(0, intdiv($rows - $height, 2)),
            max(0, intdiv($columns - $width, 2)),
            $height,
            $width,
        );
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->rows < 2 || $bounds->columns < 2) {
            return [];
        }

        // `Dialog` bez wierszy treści rysuje samą oprawę z tytułem — wiersz
        // wpisywania kładziemy sami, bo pole jest komponentem, a nie napisem.
        $primitives = (new Dialog($this->title(), []))->draw($bounds);
        $row = $bounds->row + 2;

        if ($row >= $bounds->bottom()) {
            return $primitives;
        }

        $line = $bounds->inset(0, self::PADDING_COLUMNS)->line(2);

        foreach ($this->input->draw($line) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    public function bindings(): array
    {
        return [
            KeyBinding::of([Key::Enter], 'prompt.key.accept', 'prompt.key.accept.short'),
            KeyBinding::of([Key::Escape], 'prompt.key.cancel', 'prompt.key.cancel.short'),
            ...$this->input->bindings(),
        ];
    }

    public function handle(KeyPress $key): OverlayOutcome
    {
        if ($key->key === Key::Escape) {
            return OverlayOutcome::close();
        }

        if ($key->key === Key::Enter) {
            if ($this->input->isEmpty()) {
                return OverlayOutcome::stay();
            }

            return ($this->onAccept)($this->input->value());
        }

        if (!$this->input->handle($key)) {
            // Klawisz do pola nie należy — niech spróbują go klawisze globalne.
            return OverlayOutcome::ignored();
        }

        return OverlayOutcome::stay();
    }

    private function title(): string
    {
        return $this->translator->translate($this->titleKey, $this->parameters);
    }
}
