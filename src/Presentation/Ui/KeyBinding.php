<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;

/**
 * Wiązanie klawisza z czynnością — jedno źródło dla trzech rzeczy naraz:
 * rozpoznania naciśnięcia, podpowiedzi w pasku stanu i spisu w oknie pomocy.
 *
 * Do kroku 18 te trzy rzeczy żyły osobno: `InputHandler` miał `match`,
 * `browser.hints` był napisem w katalogu, a ekran pomocy — tablicą `KEYS`
 * przepisaną ręcznie. Rozjechanie się ich było kwestią czasu, bo nic ich ze
 * sobą nie wiązało poza uwagą piszącego.
 *
 * Napis pokazywany użytkownikowi (`display()`) składa się z nazw klawiszy, więc
 * **nie idzie przez katalog napisów**: „Enter” i „F10” to napisy na klawiaturze,
 * nie zdania interfejsu. Tłumaczy się wyłącznie opis czynności — i dlatego
 * `descriptionKey` jest kluczem, a nie treścią.
 *
 * Opisów jest od kroku 40 **dwa**, i to jest rozstrzygnięcie nr 5 tamtego kroku.
 * Długi (`descriptionKey`) jest zdaniem i należy do okna pomocy, gdzie stoi sam
 * w wierszu: „zmiana zaznaczenia”, „powrót do listy plików”. Krótki
 * (`shortDescriptionKey`) należy do paska stanu, gdzie pozycji jest kilkanaście
 * naraz i liczy się każda kolumna: „zaznaczenie”, „powrót”. Brak krótkiego
 * znaczy „użyj długiego” — wiązanie, które w stopce nigdy nie stanie, nie musi
 * mieć dwóch napisów.
 */
final class KeyBinding
{
    /**
     * Znaki, które trzeba **nazwać**, bo same z siebie nic nie rysują (krok 43).
     *
     * Nazwa idzie tą samą drogą, co `Esc`, `Tab` i `PgUp` — czyli **nie przez
     * katalog napisów**: to napis z klawiatury, a nie zdanie interfejsu.
     *
     * @var array<string, string>
     */
    private const NAMED_CHARACTERS = [' ' => 'Space'];

    /**
     * @param list<Key> $keys        klawisze uruchamiające czynność
     * @param ?string   $character   znak, gdy czynność wisi na literze albo znaku
     * @param string    $descriptionKey klucz katalogu napisów z opisem czynności
     * @param bool      $ctrl        czy znak wymaga wciśniętego `Ctrl`
     * @param bool      $alt         czy znak wymaga wciśniętego `Alt`
     * @param ?string   $shortDescriptionKey klucz krótkiego opisu — dla paska stanu
     * @param bool      $shift       czy klawisze nazwane wymagają wciśniętego `Shift`
     */
    public function __construct(
        public readonly array $keys,
        public readonly ?string $character,
        public readonly string $descriptionKey,
        public readonly bool $ctrl = false,
        public readonly bool $alt = false,
        public readonly ?string $shortDescriptionKey = null,
        public readonly bool $shift = false,
    ) {
    }

    /** @param list<Key> $keys */
    public static function of(array $keys, string $descriptionKey, ?string $shortKey = null): self
    {
        return new self($keys, null, $descriptionKey, false, false, $shortKey);
    }

    /**
     * Czynność na `Shift`+klawisz nazwany — od kroku 44, gdzie wisi na nim
     * usunięcie drugą drogą i zaznaczanie zakresem. Wiązanie bez `Shift`
     * **nie łapie** naciśnięcia z nim i odwrotnie — ta sama reguła, którą
     * `Ctrl` i `Alt` mają przy literach od kroków 19 i 29.
     *
     * @param list<Key> $keys
     */
    public static function shifted(array $keys, string $descriptionKey, ?string $shortKey = null): self
    {
        return new self($keys, null, $descriptionKey, false, false, $shortKey, true);
    }

    public static function character(string $character, string $descriptionKey, ?string $shortKey = null): self
    {
        return new self([], $character, $descriptionKey, false, false, $shortKey);
    }

    /** Czynność na `Ctrl`+znak — postać, którą od kroku 20 biorą skróty modułów. */
    public static function ctrl(string $character, string $descriptionKey, ?string $shortKey = null): self
    {
        return new self([], $character, $descriptionKey, true, false, $shortKey);
    }

    /** Czynność na `Alt`+znak — od kroku 29, gdzie wisi na nim zawijanie wierszy. */
    public static function alt(string $character, string $descriptionKey, ?string $shortKey = null): self
    {
        return new self([], $character, $descriptionKey, false, true, $shortKey);
    }

    /** Klucz opisu dla paska stanu: krótki, jeśli jest; długi w przeciwnym razie. */
    public function hintKey(): string
    {
        return $this->shortDescriptionKey ?? $this->descriptionKey;
    }

    /**
     * Czy oba wiązania mówią o tym samym: ten sam zestaw klawiszy **i** ten sam
     * klucz opisu (krok 40, rozstrzygnięcie nr 4).
     *
     * Ostrożniejszy z dwóch wariantów: `↑↓ zmiana zaznaczenia` i `↑↓ przewijanie
     * wiersza` zostają w stopce **obiema** pozycjami, bo mówią o różnych
     * czynnościach na różnych poziomach. Przy dzisiejszym kodzie odsiew trafia
     * dokładnie tam, gdzie trzeba, bo ekran składa `bindings()` z wiązań miejsca
     * **plus** własnych — powtórzenie jest więc tym samym obiektem, a nie dwoma
     * podobnymi.
     */
    public function sameAs(self $other): bool
    {
        return $this->keys === $other->keys
            && $this->character === $other->character
            && $this->ctrl === $other->ctrl
            && $this->alt === $other->alt
            && $this->shift === $other->shift
            && $this->descriptionKey === $other->descriptionKey;
    }

    /** Czy wiązanie wisi na tym klawiszu — bez znaku i bez modyfikatorów. */
    public function usesKey(Key $key): bool
    {
        return in_array($key, $this->keys, true);
    }

    public function matches(KeyPress $key): bool
    {
        if ($this->character !== null
            && $key->key === Key::Character
            && $key->raw === $this->character
            && $key->ctrl === $this->ctrl
            && $key->alt === $this->alt
        ) {
            return true;
        }

        // `Shift` porównuje się przy klawiszach nazwanych tą samą regułą, którą
        // litera porównuje `Ctrl` i `Alt`: goły `F8` nie ma prawa złapać
        // `Shift`+`F8`, bo od kroku 44 znaczą dwie różne rzeczy.
        return $key->shift === $this->shift
            && in_array($key->key, $this->keys, true);
    }

    /**
     * Naciśnięcie, którym to wiązanie się uruchamia (krok 55).
     *
     * Istnieje dla **jednej** rzeczy: kliknięcia w podpowiedź paska stanu.
     * Kliknięcie zamienia się z powrotem w `KeyPress` i wraca do
     * `InputHandler::handle()` — czyli wykonuje się tą samą drogą, co klawisz,
     * a nie drugą, równoległą. Bez tego każda czynność miałaby dwa wejścia
     * i rozjechałyby się przy pierwszej poprawce (ta sama reguła, którą krok 32
     * zapisał dla menu kontekstowego).
     *
     * `null` znaczy „wiązanie nie wisi na niczym, co da się nacisnąć” — dziś
     * niemożliwe, bo każde ma albo klawisz, albo znak; sprawdzenie zostaje, bo
     * kontrakt konstruktora tego nie wymusza.
     */
    public function press(): ?KeyPress
    {
        $key = $this->keys[0] ?? null;

        if ($key !== null) {
            // `raw` klawisza nazwanego zostaje puste, tak samo jak w torze
            // okienkowym (krok 34): bajtów, którymi klawisz przyszedł z terminala,
            // nie czyta przy nich nikt.
            return $this->shift ? KeyPress::shifted($key, '') : KeyPress::special($key, '');
        }

        if ($this->character === null) {
            return null;
        }

        return match (true) {
            $this->ctrl => KeyPress::ctrl($this->character),
            $this->alt => KeyPress::alt($this->character),
            default => KeyPress::character($this->character),
        };
    }

    /** Napis dla użytkownika: nazwy klawiszy rozdzielone ukośnikiem. */
    public function display(): string
    {
        $prefix = $this->shift ? 'Shift+' : '';
        $names = array_map(
            fn (Key $key): string => $prefix . self::nameOf($key),
            $this->keys,
        );

        if ($this->character !== null) {
            $names[] = match (true) {
                $this->ctrl => 'Ctrl+' . mb_strtoupper($this->character),
                $this->alt => 'Alt+' . mb_strtoupper($this->character),
                // Klawisz, którego znak **nic nie rysuje**, musi mieć nazwę —
                // inaczej stopka mówi „· ␣ zaznacz” i wygląda jak usterka.
                // Znalezione w kroku 43 na klatce z prawdziwego terminala, bo
                // testy porównywały klucz opisu, a nie to, co widać.
                default => self::NAMED_CHARACTERS[$this->character] ?? $this->character,
            };
        }

        return implode(' / ', $names);
    }

    private static function nameOf(Key $key): string
    {
        return match ($key) {
            Key::ArrowUp => '↑',
            Key::ArrowDown => '↓',
            Key::ArrowLeft => '←',
            Key::ArrowRight => '→',
            Key::Enter => 'Enter',
            Key::Backspace => 'Backspace',
            Key::Escape => 'Esc',
            Key::Tab => 'Tab',
            Key::Home => 'Home',
            Key::End => 'End',
            Key::PageUp => 'PgUp',
            Key::PageDown => 'PgDn',
            Key::Delete => 'Del',
            default => $key->name,
        };
    }
}
