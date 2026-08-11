<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Bar;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Primitive\Weight;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Wypełniany pasek z napisem w środku — jeden sposób pokazywania, że coś trwa.
 *
 * Dwa tryby i **żaden nie udaje drugiego**:
 *
 * - **postęp znany** (`fraction` podany) — wypełnienie rośnie od lewej, a pasek
 *   dokłada do napisu liczbę procent;
 * - **postęp nieznany** (`null`) — wypełnienie o stałej szerokości wędruje tam
 *   i z powrotem, a liczby nie ma w ogóle, bo nie ma jej skąd wziąć. Pierwszym
 *   takim przypadkiem jest `du`: polecenie nie mówi, ile drzewa już przeszło,
 *   a licznik oparty na czasie kłamałby tym bardziej, im większe drzewo.
 *
 * Bezstanowy jak każdy komponent: powstaje co klatkę z wartości podanych
 * z zewnątrz. Czas też przychodzi z zewnątrz — `microtime()` w środku zamieniłby
 * wędrujące wypełnienie w coś, czego nie da się sprawdzić bez czekania, a tak
 * test podaje własną chwilę i ogląda pasek w dowolnym miejscu cyklu. Zegar niesie
 * do ekranu `NeedsTime`, tą samą drogą, którą od kroku 19 chodzi do karetki
 * w polu tekstowym.
 *
 * Napis wchodzi **do środka** paska, a nie obok: pasek na całą szerokość panelu
 * z pustym środkiem marnowałby wiersz, którego w wąskim oknie nie ma. Tam, gdzie
 * napis przechodzi przez wypełnienie, zmienia rolę — inaczej litery w akcencie
 * na akcencie zniknęłyby w połowie słowa.
 */
final class ProgressBar implements ComponentInterface
{
    /** Ile trwa przejście wędrującego wypełnienia w jedną stronę. */
    private const SWEEP_SECONDS = 1.2;

    /** Jaką część toru zajmuje wędrujące wypełnienie przy postępie nieznanym. */
    private const SWEEP_RATIO = 0.25;

    public function __construct(
        /** 0.0–1.0; `null` — postępu nie da się policzyć. Wartość spoza zakresu jest przycinana. */
        private readonly ?float $fraction,
        /** Napis w środku paska — **już przetłumaczony**; liczbę procent pasek dokłada sam. */
        private readonly string $text = '',
        /** Czas bieżącej klatki; używany wyłącznie przez tryb postępu nieznanego. */
        private readonly float $now = 0.0,
        /**
         * Tłumacz do zapisu liczby procent.
         *
         * Wolno go pominąć i wtedy liczba idzie w postaci surowej — dokładnie
         * dla jednego wołającego, którym jest `ScenarioFactory`. Treść mierzonej
         * klatki nie przechodzi przez katalog napisów z rozmysłem (D33): długość
         * napisu w znakach wchodzi do wyniku pomiaru, więc wzorzec nie może
         * zależeć od wybranego języka.
         */
        private readonly ?TranslatorPort $translator = null,
    ) {
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $fill = $this->fill($bounds);
        $primitives = [new Bar($bounds, Role::Surface, Weight::Fill)];

        if ($fill !== null) {
            $primitives[] = new Bar($fill, Role::Accent, Weight::Fill);
        }

        foreach ($this->caption($bounds, $fill) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    /**
     * Wypełnienie: przy postępie znanym prostokąt od lewej krawędzi, przy
     * nieznanym — odcinek stałej szerokości w wyliczonym miejscu. `null` znaczy
     * „nic do narysowania”, czyli dokładnie zero procent.
     */
    private function fill(Rect $bounds): ?Rect
    {
        $progress = self::clamp($this->fraction);

        if ($progress === null) {
            return $this->sweep($bounds);
        }

        $columns = (int) round($progress * $bounds->columns);

        return $columns < 1 ? null : new Rect($bounds->row, $bounds->column, $bounds->rows, $columns);
    }

    /**
     * Odcinek wędrujący tam i z powrotem — trójkątna fala, a nie skok z prawej
     * krawędzi na lewą: pasek zawracający w miejscu czyta się jako ruch, pasek
     * przeskakujący — jako zacięcie.
     */
    private function sweep(Rect $bounds): Rect
    {
        $width = max(1, (int) round($bounds->columns * self::SWEEP_RATIO));
        $travel = $bounds->columns - $width;

        if ($travel < 1) {
            return $bounds;
        }

        $cycle = fmod(max(0.0, $this->now), 2 * self::SWEEP_SECONDS) / self::SWEEP_SECONDS;
        $position = $cycle <= 1.0 ? $cycle : 2.0 - $cycle;

        return new Rect($bounds->row, $bounds->column + (int) round($position * $travel), $bounds->rows, $width);
    }

    /**
     * Napis wyśrodkowany w środkowym wierszu, pocięty na odcinki wedle tego,
     * które znaki leżą na wypełnieniu.
     *
     * Cięcie idzie po kolumnach, bo wypełnienie zajmuje zawsze pełną wysokość
     * paska — pytanie „czy ten znak leży na wypełnieniu” sprowadza się do
     * porównania numeru kolumny.
     *
     * @return list<Primitive>
     */
    private function caption(Rect $bounds, ?Rect $fill): array
    {
        $text = Label::fit($this->label(), $bounds->columns);

        if ($text === '') {
            return [];
        }

        $length = mb_strlen($text);
        $row = $bounds->row + intdiv($bounds->rows - 1, 2);
        $column = $bounds->column + intdiv(max(0, $bounds->columns - $length), 2);

        if ($fill === null) {
            return [new TextRun($row, $column, $text, Role::Text)];
        }

        // Wypełnienie jest jednym odcinkiem, więc napis rozpada się na co
        // najwyżej trzy części: przed nim, na nim i za nim. Granice liczone
        // w znakach napisu, a nie w kolumnach panelu — to one wchodzą potem do
        // `mb_substr()`.
        $start = max(0, min($length, $fill->column - $column));
        $end = max(0, min($length, $fill->right() + 1 - $column));

        $parts = [
            [0, $start, Role::Text],
            [$start, $end - $start, Role::Background],
            [$end, $length - $end, Role::Text],
        ];
        $primitives = [];

        foreach ($parts as [$offset, $width, $role]) {
            if ($width > 0) {
                $primitives[] = new TextRun($row, $column + $offset, mb_substr($text, $offset, $width), $role);
            }
        }

        return $primitives;
    }

    /**
     * Treść napisu wraz z liczbą procent — ta ostatnia wyłącznie przy postępie
     * znanym, bo tryb nieznany nie ma prawa pokazać liczby.
     */
    private function label(): string
    {
        $progress = self::clamp($this->fraction);

        if ($progress === null) {
            return $this->text;
        }

        $percent = (int) round($progress * 100);
        $value = $this->translator?->number($percent) ?? (string) $percent;
        $suffix = $this->translator?->translate('format.percent', ['value' => $value]) ?? $value . '%';

        return $this->text === '' ? $suffix : $this->text . ' ' . $suffix;
    }

    /**
     * Ułamek sprowadzony do zakresu 0.0–1.0. Wartość, która liczbą nie jest,
     * znaczy to samo, co jej brak: postępu nie da się policzyć.
     */
    private static function clamp(?float $fraction): ?float
    {
        if ($fraction === null || is_nan($fraction)) {
            return null;
        }

        return max(0.0, min(1.0, $fraction));
    }
}
