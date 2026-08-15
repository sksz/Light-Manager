<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Overlay;

use Closure;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Presentation\Ui\Component\Button;
use LightManager\Presentation\Ui\Component\Dialog;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * Pytanie, na które trzeba odpowiedzieć, zanim coś się stanie (krok 28).
 *
 * Drugie okno nakładane w projekcie i pierwsze, które **czegoś chce od
 * wołającego**. Decyzja wraca stąd domknięciem podanym przy tworzeniu okna
 * (D56): po „tak” wykonuje się ono i oddaje **skutek okna**. Kontrakt okna
 * nakładanego nie urósł przez to ani o pole — to ten sam wzorzec, którym
 * `Button` działa od kroku 18: czynność przychodzi z zewnątrz, a komponent nie
 * wie, co uruchamia.
 *
 * **Krok 41 zmienił typ tego domknięcia z `?Message` na `OverlayOutcome`** i to
 * jedna zmiana z jednego powodu: pytanie stoi odtąd **w środku łańcucha okien**.
 * Zgoda na usunięcie katalogu zaczyna pracę, która trwa dłużej niż klatka, więc
 * po pytaniu ma stanąć okno postępu — a okno, którego domknięcie umie oddać
 * wyłącznie zdanie, nie ma jak o tym powiedzieć. Czynność decyduje teraz
 * o wszystkim naraz: co powiedzieć i co pokazać dalej (`OverlayOutcome::close()`
 * albo `replace()`), dokładnie jak `RunsWork::advance()`.
 *
 * Drugie domknięcie — **po odmowie** — jest opcjonalne i służy sprzątaniu: pytanie
 * może stać po pracy, która już coś policzyła, a policzona lista wpisów do
 * usunięcia nie ma prawa przeżyć „nie” (krok 41).
 *
 * **Krok 43 dokłada liczbę do form mnogich** i to jest cała zmiana, jakiej rdzeń
 * wymagał od zaznaczenia wielokrotnego. Plan tamtego kroku spodziewał się roboty
 * większej — „katalog napisów tego dziś nie umie” — ale umiał od kroku 15
 * (`TranslatorPort::plural()`, `PluralRule::Slavic`); brakowało wyłącznie drogi,
 * którą pytanie miało o formę poprosić. Pytanie o zbiór odmienia się bowiem przez
 * liczbę w każdym języku słowiańskim: „usunąć 2 wpisy” i „usunąć 5 wpisów” to nie
 * jest ten sam napis z podstawioną cyfrą.
 *
 * **Domyślną odpowiedzią jest „nie”** i to nie jest kosmetyka: okno staje
 * przed rzeczą nieodwracalną, więc użytkownik przyzwyczajony do
 * przytrzymywania `Enter`a ma trafić w odmowę, nie w zgodę. Z tego samego
 * powodu `Esc` znaczy dokładnie tyle samo, co „nie”: milczenie przed czynnością
 * nieodwracalną jest odmową, a nie brakiem odpowiedzi.
 *
 * Klawisze globalne okno **przepuszcza**, jak każde inne (reguła kroku 19).
 * `F10` w trakcie pytania kończy aplikację bez wykonania czynności — czyli
 * bezpiecznie; okno, z którego nie da się wyjść inaczej niż odpowiadając,
 * byłoby gorsze od pytania, które można zignorować.
 */
final class ConfirmOverlay implements OverlayInterface
{
    private const MARGIN_ROWS = 2;

    private const MARGIN_COLUMNS = 4;

    private const PADDING_COLUMNS = 2;

    /** Obwódka, tytuł, **jeden** wiersz pytania, odstęp, przyciski, obwódka. */
    private const CHROME_ROWS = 6;

    /**
     * Górna granica liczby wierszy pytania (krok 48).
     *
     * Okno rosnące bez końca przestałoby być oknem — a pytanie, które nie mieści
     * się w sześciu wierszach, jest pytaniem źle napisanym, nie oknem za małym.
     * Nadmiar ucina `Dialog`, tak jak ucinał całe pytanie przed tym krokiem.
     */
    private const MAX_QUESTION_ROWS = 6;

    /** Odstęp między przyciskami, w kolumnach. */
    private const BUTTON_GAP = 2;

    /**
     * Górna granica szerokości — **dług kroku 41 spłacony w kroku 42**, bo to on
     * dowiózł pierwszego prawdziwego odbiorcę.
     *
     * `PromptOverlay` dostał ją po obejrzeniu okna w prawdziwym terminalu: tytuł
     * z pełną ścieżką rozdmuchiwał okno na całą szerokość klatki. Pytanie ma ten
     * sam rachunek i tę samą usterkę — dziś już z odbiorcą, bo nazwa wpisu wchodzi
     * do pytania przed usunięciem, a nazwy plików bywają dłuższe od okna.
     *
     * **Zdanie o ucinaniu jest od kroku 48 odwołane, a wraz z nim jego
     * uzasadnienie.** Stało tu, że zawijanie znaczyłoby okno o wysokości
     * zależnej od treści, i że nazwa ucięta w pytaniu widoczna jest piętro niżej,
     * pod kursorem listy. Pierwsze zostało zapłacone — okno rośnie o tyle
     * wierszy, ile trzeba, i jest to dziesięć linii kodu. Drugie okazało się
     * prawdziwe **tylko dla nazw wpisów**: pytanie o zaufanie nieznanemu kluczowi
     * hosta niesie odcisk SHA256, którego nie widać nigdzie indziej, a odcisk
     * ucięty w połowie nie jest odciskiem — jest pytaniem bez treści, którą ma
     * się porównać. Szerokość zostaje ta sama; zmienia się wyłącznie to, co się
     * dzieje z nadmiarem.
     */
    private const MAX_COLUMNS = 64;

    /** Ognisko: `false` znaczy „nie”, czyli stan początkowy. */
    private bool $confirmed = false;

    /**
     * @param string                   $questionKey klucz katalogu, nie napis
     * @param array<string, string>    $parameters  dane do podstawienia w pytaniu
     * @param Closure(): OverlayOutcome $onConfirm  czynność po „tak”; oddaje skutek okna
     * @param bool                     $dangerous   czy czynność jest nieodwracalna — wtedy okno
     *                                              maluje się rolą `Danger` zamiast akcentu
     * @param ?Closure(): void         $onRefuse    sprzątanie po „nie” i po `Esc`
     * @param ?int                     $count       liczba, dla której dobiera się forma
     *                                              mnoga pytania; `null` — pytanie bez liczby
     */
    public function __construct(
        private readonly string $questionKey,
        private readonly array $parameters,
        private readonly Closure $onConfirm,
        private readonly TranslatorPort $translator,
        private readonly bool $dangerous = false,
        private readonly ?Closure $onRefuse = null,
        private readonly ?int $count = null,
    ) {
    }

    public function id(): string
    {
        return 'confirm';
    }

    /** Okno staje pośrodku, w rozmiarze mieszczącym pytanie i oba przyciski. */
    public function bounds(int $rows, int $columns): Rect
    {
        $width = min(max(
            mb_strlen($this->title()),
            mb_strlen($this->question()),
            $this->widthOf(true) + self::BUTTON_GAP + $this->widthOf(false),
        ), self::MAX_COLUMNS) + 2 * self::PADDING_COLUMNS;

        $width = min($width, max(1, $columns - self::MARGIN_COLUMNS));

        // Wysokość liczy się **po** szerokości i z niej: dopiero ona mówi, na ile
        // wierszy rozpadnie się pytanie. Odwrotna kolejność dawałaby okno wysokie
        // na jeden wiersz treści niezależnie od tego, ile jej jest.
        $wanted = self::CHROME_ROWS - 1 + count($this->questionLines($width - 2 * self::PADDING_COLUMNS));
        $height = min($wanted, max(1, $rows - self::MARGIN_ROWS));

        return new Rect(
            max(0, intdiv($rows - $height, 2)),
            max(0, intdiv($columns - $width, 2)),
            $height,
            $width,
        );
    }

    /**
     * Oprawa z pytaniem rysuje się `Dialog`iem, przyciski stają w wierszu tuż
     * nad dolną obwódką. Komponent nie powstaje tu ani jeden — powstaje sposób
     * ich użycia.
     */
    public function draw(Rect $bounds): array
    {
        if ($bounds->rows < 2 || $bounds->columns < 2) {
            return [];
        }

        $primitives = (new Dialog(
            $this->title(),
            $this->questionLines($bounds->columns - 2 * self::PADDING_COLUMNS),
            $this->dangerous ? Role::Danger : Role::Accent,
            $this->dangerous ? Role::Danger : Role::Border,
        ))->draw($bounds);

        foreach ($this->drawButtons($bounds) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    public function bindings(): array
    {
        return [
            KeyBinding::of([Key::ArrowLeft, Key::ArrowRight, Key::Tab], 'confirm.key.move'),
            KeyBinding::of([Key::Enter], 'confirm.key.answer'),
            KeyBinding::of([Key::Escape], 'confirm.key.refuse'),
        ];
    }

    /**
     * Odpowiedź albo przepuszczenie klawisza — trzeciej możliwości nie ma.
     *
     * `Enter` idzie do przycisku pod ogniskiem i to **on** wykonuje czynność
     * (albo jej nie wykonuje, jeśli ognisko stoi na „nie”). Okno zna wyłącznie
     * komunikat, który z tego został.
     */
    public function handle(KeyPress $key): OverlayOutcome
    {
        if ($key->key === Key::ArrowLeft || $key->key === Key::ArrowRight || $key->key === Key::Tab) {
            $this->confirmed = !$this->confirmed;

            return OverlayOutcome::stay();
        }

        if ($key->key === Key::Escape) {
            return $this->refused();
        }

        if ($key->key !== Key::Enter) {
            // Wszystko inne należy do klawiszy globalnych albo do nikogo.
            return OverlayOutcome::ignored();
        }

        $outcome = null;

        foreach ($this->buttons($outcome) as $button) {
            if ($button->handle($key)) {
                break;
            }
        }

        // Brak skutku znaczy „ognisko stało na «nie»” — przycisk odmowy niczego
        // nie wykonuje, a sprzątanie po odmowie należy do wołającego.
        return $outcome ?? $this->refused();
    }

    /** Odmowa: sprzątnięcie po tym, co pytanie zastało, i zamknięcie okna. */
    private function refused(): OverlayOutcome
    {
        if ($this->onRefuse !== null) {
            ($this->onRefuse)();
        }

        return OverlayOutcome::close();
    }

    /**
     * Pytanie rozłożone na wiersze mieszczące się w podanej szerokości (krok 48).
     *
     * Łamie **po słowach**, a nie po znakach — odwrotnie niż `TextView` (11i)
     * i z odwrotnego powodu: tam treścią jest plik, którego wierszy nie wolno
     * przeinaczyć, tutaj zdanie do przeczytania przez człowieka. Słowo dłuższe od
     * wiersza dzieli się mimo to twardo, bo takim słowem jest właśnie odcisk
     * klucza — czyli dokładnie ta treść, dla której to zawijanie powstało.
     *
     * @return list<string> zawsze co najmniej jeden wiersz, także pusty
     */
    private function questionLines(int $width): array
    {
        $question = $this->question();

        if ($width < 1) {
            return [$question];
        }

        $lines = [];
        $current = '';

        foreach (explode(' ', $question) as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;

            if (mb_strlen($candidate) <= $width) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                $current = '';
            }

            // Słowo dłuższe od wiersza (odcisk klucza) dzieli się twardo —
            // zostawione w całości i tak zostałoby ucięte przez `Dialog`.
            while (mb_strlen($word) > $width) {
                $lines[] = mb_substr($word, 0, $width);
                $word = mb_substr($word, $width);
            }

            $current = $word;
        }

        if ($current !== '' || $lines === []) {
            $lines[] = $current;
        }

        return array_slice($lines, 0, self::MAX_QUESTION_ROWS);
    }

    /**
     * Para przycisków wyśrodkowana w wierszu nad dolną obwódką.
     *
     * Każdy dostaje prostokąt **szerokości własnej etykiety** z oddechem, a nie
     * połowę okna: `Button` maluje pasek ogniska na całym prostokącie, więc
     * przycisk rozciągnięty na pół okna wyglądałby jak błąd rysowania, a nie
     * jak odpowiedź do wybrania (zobaczone na zrzucie przy weryfikacji kroku).
     *
     * @return list<Primitive>
     */
    private function drawButtons(Rect $bounds): array
    {
        $row = $bounds->bottom() - 1;

        if ($row <= $bounds->row + 1) {
            return [];
        }

        $inner = $bounds->inset(0, self::PADDING_COLUMNS);
        $line = $inner->line($row - $bounds->row);
        $widths = [$this->widthOf(true), $this->widthOf(false)];
        $total = $widths[0] + self::BUTTON_GAP + $widths[1];

        if ($total > $inner->columns) {
            return [];
        }

        $primitives = [];
        $offset = intdiv($inner->columns - $total, 2);

        foreach ($this->buttons() as $index => $button) {
            foreach ($button->draw($line->columnsFrom($offset, $widths[$index])) as $primitive) {
                $primitives[] = $primitive;
            }

            $offset += $widths[$index] + self::BUTTON_GAP;
        }

        return $primitives;
    }

    /** Szerokość przycisku: etykieta plus po kolumnie oddechu z każdej strony. */
    private function widthOf(bool $yes): int
    {
        return mb_strlen($this->label($yes)) + 2;
    }

    /**
     * Para przycisków w kolejności „tak”, „nie” — powstająca na nowo przy
     * każdym pytaniu, bo komponent jest bezstanowy, a ognisko żyje w oknie.
     *
     * @param ?OverlayOutcome $outcome miejsce na skutek czynności — przez referencję,
     *                                 bo przycisk oddaje `void` i innej drogi nie ma
     *
     * @return array{Button, Button}
     */
    private function buttons(?OverlayOutcome &$outcome = null): array
    {
        return [
            new Button(
                $this->label(true),
                function () use (&$outcome): void {
                    $outcome = ($this->onConfirm)();
                },
                'confirm.key.answer',
                $this->confirmed,
            ),
            new Button(
                $this->label(false),
                static function (): void {
                },
                'confirm.key.refuse',
                !$this->confirmed,
            ),
        ];
    }

    private function label(bool $yes): string
    {
        return $this->translator->translate($yes ? 'confirm.yes' : 'confirm.no');
    }

    private function title(): string
    {
        return $this->translator->translate($this->dangerous ? 'confirm.title.dangerous' : 'confirm.title');
    }

    /**
     * Pytanie w formie odpowiedniej dla liczby, gdy wołający ją podał.
     *
     * Rozgałęzienie zamiast jednej drogi przez `plural()`, bo `plural()` wymaga
     * liczby, a większość pytań jej nie ma i mieć nie musi — „Usunąć »raport.pdf«
     * bezpowrotnie?” nie odmienia się przez nic.
     */
    private function question(): string
    {
        if ($this->count === null) {
            return $this->translator->translate($this->questionKey, $this->parameters);
        }

        return $this->translator->plural($this->questionKey, $this->count, $this->parameters);
    }
}
