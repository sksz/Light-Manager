<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Presentation\Ui\FocusableInterface;
use LightManager\Presentation\Ui\KeyBinding;

/**
 * Pole wpisywania danych: napis, karetka i edycja w obrębie jednego wiersza.
 *
 * Pierwszy komponent aplikacji przyjmujący **dowolny znak**, a przez to pierwszy
 * prawdziwy sprawdzian kontraktu z kroku 18. Kontrakt się obronił:
 * `handle(KeyPress): bool` wystarcza, bo „zużyłem klawisz” znaczy dla pola
 * dokładnie to samo, co dla przycisku — z tą różnicą, że pole zużywa niemal
 * wszystko, a przepuszcza garść klawiszy sterujących.
 *
 * **Karetka to nie kursor.** Kursorem (`Cursor`) krok 18 nazwał ognisko wędrujące
 * między komponentami; karetka jest miejscem wpisywania **wewnątrz** jednego
 * komponentu. Rysuje się tym samym paskiem, co zaznaczenie w liście
 * (`Highlight`), bo aplikacja ma mówić o wyróżnieniu jednym językiem — i dzięki
 * temu nie potrzebuje nowego prymitywu (słownik prymitywów jest zamknięty).
 *
 * **Znak z `Ctrl` nie jest treścią.** `Ctrl+D` ma trafić do tego, kto na nim
 * powiesił skrót, a nie do pola jako bajt sterujący — i to jest jedyny powód,
 * dla którego `Ctrl` powstał w tym samym kroku co pole (D39, P17).
 */
final class TextInput implements FocusableInterface
{
    /** Co ile sekund karetka zmienia stan: pół sekundy świeci, pół gaśnie. */
    private const BLINK_SECONDS = 0.5;

    private string $value = '';

    /** Położenie karetki liczone w **znakach**, od 0 do długości napisu. */
    private int $caret = 0;

    private bool $caretVisible = true;

    public function __construct(
        private readonly string $prompt = '> ',
    ) {
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    /** Wstawia napis i stawia karetkę na jego końcu — tak wraca wpis z historii. */
    public function useValue(string $value): void
    {
        $this->value = $value;
        $this->caret = mb_strlen($value);
    }

    public function clear(): void
    {
        $this->useValue('');
    }

    /**
     * Stan karetki w tej chwili. Zegar przychodzi z zewnątrz, bo komponent
     * powstaje raz i nie ma powodu znać `microtime()`; wie za to, **jak** długo
     * trwa mrugnięcie, bo karetka należy do niego.
     */
    public function useTime(float $now): void
    {
        $this->caretVisible = (int) ($now / self::BLINK_SECONDS) % 2 === 0;
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $line = $bounds->line(0);
        $promptWidth = mb_strlen($this->prompt);
        $width = $line->columns - $promptWidth;

        if ($width < 1) {
            return [];
        }

        $offset = $this->windowOffset($width);
        $visible = mb_substr($this->value, $offset, $width);
        $primitives = [new TextRun($line->row, $line->column, $this->prompt, Role::Muted)];

        if ($visible !== '') {
            $primitives[] = new TextRun($line->row, $line->column + $promptWidth, $visible, Role::Text);
        }

        if (!$this->caretVisible) {
            return $primitives;
        }

        $column = $line->column + $promptWidth + ($this->caret - $offset);

        foreach (Highlight::under(new Rect($line->row, $column, 1, 1)) as $primitive) {
            $primitives[] = $primitive;
        }

        $under = mb_substr($this->value, $this->caret, 1);

        if ($under !== '') {
            $primitives[] = new TextRun($line->row, $column, $under, Role::SelectionText);
        }

        return $primitives;
    }

    public function bindings(): array
    {
        return [
            KeyBinding::of([Key::ArrowLeft, Key::ArrowRight, Key::Home, Key::End], 'command.key.caret'),
            KeyBinding::of([Key::Backspace, Key::Delete], 'command.key.erase'),
        ];
    }

    public function handle(KeyPress $key): bool
    {
        return match (true) {
            $key->key === Key::ArrowLeft => $this->moveCaret(-1),
            $key->key === Key::ArrowRight => $this->moveCaret(1),
            $key->key === Key::Home => $this->putCaret(0),
            $key->key === Key::End => $this->putCaret(mb_strlen($this->value)),
            $key->key === Key::Backspace => $this->eraseBefore(),
            $key->key === Key::Delete => $this->eraseUnder(),
            // Litera z modyfikatorem nie jest treścią: `Ctrl`+litera od kroku 19,
            // `Alt`+litera od kroku 29. Obie niosą w `raw` samą literę, więc bez
            // tego warunku skrót wpisywałby się do pola jak zwykły znak.
            $key->key === Key::Character && !$key->ctrl && !$key->alt => $this->insert($key->raw),
            default => false,
        };
    }

    /**
     * Ile znaków napisu chowa się na lewo od okna. Karetka zostaje widoczna
     * zawsze — w polu na jeden wiersz nie ma innego sposobu, by pokazać koniec
     * długiej ścieżki i nie zgubić miejsca, w którym się pisze.
     */
    private function windowOffset(int $width): int
    {
        if ($this->caret < $width) {
            return 0;
        }

        return $this->caret - $width + 1;
    }

    private function insert(string $character): bool
    {
        $this->value = mb_substr($this->value, 0, $this->caret)
            . $character
            . mb_substr($this->value, $this->caret);
        ++$this->caret;

        return true;
    }

    private function eraseBefore(): bool
    {
        if ($this->caret === 0) {
            return true;
        }

        $this->value = mb_substr($this->value, 0, $this->caret - 1) . mb_substr($this->value, $this->caret);
        --$this->caret;

        return true;
    }

    private function eraseUnder(): bool
    {
        $this->value = mb_substr($this->value, 0, $this->caret) . mb_substr($this->value, $this->caret + 1);

        return true;
    }

    private function moveCaret(int $delta): bool
    {
        return $this->putCaret($this->caret + $delta);
    }

    private function putCaret(int $position): bool
    {
        $this->caret = max(0, min($position, mb_strlen($this->value)));

        return true;
    }
}
