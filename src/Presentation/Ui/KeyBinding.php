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
 */
final class KeyBinding
{
    /**
     * @param list<Key> $keys        klawisze uruchamiające czynność
     * @param ?string   $character   znak, gdy czynność wisi na literze albo znaku
     * @param string    $descriptionKey klucz katalogu napisów z opisem czynności
     * @param bool      $ctrl        czy znak wymaga wciśniętego `Ctrl`
     */
    public function __construct(
        public readonly array $keys,
        public readonly ?string $character,
        public readonly string $descriptionKey,
        public readonly bool $ctrl = false,
    ) {
    }

    /** @param list<Key> $keys */
    public static function of(array $keys, string $descriptionKey): self
    {
        return new self($keys, null, $descriptionKey);
    }

    public static function character(string $character, string $descriptionKey): self
    {
        return new self([], $character, $descriptionKey);
    }

    /** Czynność na `Ctrl`+znak — postać, którą od kroku 20 biorą skróty modułów. */
    public static function ctrl(string $character, string $descriptionKey): self
    {
        return new self([], $character, $descriptionKey, true);
    }

    public function matches(KeyPress $key): bool
    {
        if ($this->character !== null
            && $key->key === Key::Character
            && $key->raw === $this->character
            && $key->ctrl === $this->ctrl
        ) {
            return true;
        }

        return in_array($key->key, $this->keys, true);
    }

    /** Napis dla użytkownika: nazwy klawiszy rozdzielone ukośnikiem. */
    public function display(): string
    {
        $names = array_map(self::nameOf(...), $this->keys);

        if ($this->character !== null) {
            $names[] = $this->ctrl
                ? 'Ctrl+' . mb_strtoupper($this->character)
                : $this->character;
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
