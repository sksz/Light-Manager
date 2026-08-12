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
use LightManager\Domain\ValueObject\Message;
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
 * (D56): po „tak” wykonuje się ono i **oddaje komunikat**, który okno pakuje
 * w `OverlayOutcome::close()`. Kontrakt okna nakładanego nie urósł przez to
 * ani o pole — to ten sam wzorzec, którym `Button` działa od kroku 18:
 * czynność przychodzi z zewnątrz, a komponent nie wie, co uruchamia.
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

    /** Obwódka, tytuł, pytanie, odstęp, przyciski, obwódka. */
    private const CHROME_ROWS = 6;

    /** Odstęp między przyciskami, w kolumnach. */
    private const BUTTON_GAP = 2;

    /** Ognisko: `false` znaczy „nie”, czyli stan początkowy. */
    private bool $confirmed = false;

    /** Komunikat oddany przez domknięcie — żyje tylko do końca obsługi klawisza. */
    private ?Message $pending = null;

    /**
     * @param string                $questionKey klucz katalogu, nie napis
     * @param array<string, string> $parameters  dane do podstawienia w pytaniu
     * @param Closure(): ?Message   $onConfirm   czynność po „tak”; oddaje komunikat do paska stanu
     * @param bool                  $dangerous   czy czynność jest nieodwracalna — wtedy okno
     *                                           maluje się rolą `Danger` zamiast akcentu
     */
    public function __construct(
        private readonly string $questionKey,
        private readonly array $parameters,
        private readonly Closure $onConfirm,
        private readonly TranslatorPort $translator,
        private readonly bool $dangerous = false,
    ) {
    }

    public function id(): string
    {
        return 'confirm';
    }

    /** Okno staje pośrodku, w rozmiarze mieszczącym pytanie i oba przyciski. */
    public function bounds(int $rows, int $columns): Rect
    {
        $width = max(
            mb_strlen($this->title()),
            mb_strlen($this->question()),
            $this->widthOf(true) + self::BUTTON_GAP + $this->widthOf(false),
        ) + 2 * self::PADDING_COLUMNS;

        $height = min(self::CHROME_ROWS, max(1, $rows - self::MARGIN_ROWS));
        $width = min($width, max(1, $columns - self::MARGIN_COLUMNS));

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
            [$this->question()],
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
            return OverlayOutcome::close();
        }

        if ($key->key !== Key::Enter) {
            // Wszystko inne należy do klawiszy globalnych albo do nikogo.
            return OverlayOutcome::ignored();
        }

        $this->pending = null;

        foreach ($this->buttons() as $button) {
            if ($button->handle($key)) {
                break;
            }
        }

        $message = $this->pending;
        $this->pending = null;

        return OverlayOutcome::close($message);
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
     * @return array{Button, Button}
     */
    private function buttons(): array
    {
        return [
            new Button(
                $this->label(true),
                function (): void {
                    $this->pending = ($this->onConfirm)();
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

    private function question(): string
    {
        return $this->translator->translate($this->questionKey, $this->parameters);
    }
}
