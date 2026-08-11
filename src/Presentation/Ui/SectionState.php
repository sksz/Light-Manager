<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

/**
 * Stan listy sekcji: które są zwinięte i na której stoi kursor.
 *
 * Druga w projekcie klasa pamiętająca coś **między klatkami** — pierwszą jest
 * `ScrollWindow` i obie stoją tu z tego samego powodu: komponent powstaje na
 * nowo trzydzieści razy na sekundę i nie zapamiętałby, co użytkownik zrobił
 * przed chwilą. Właścicielem jest ekran, tak samo jak dla okna przewijania.
 *
 * Zwinięcie trzyma się **pod kluczem sekcji, a nie pod jej numerem**. Sekcja,
 * która zniknęła z listy i wróciła — bo moduł został wyłączony i włączony, bo
 * zmienił się opisywany plik — ma wrócić zwinięta, jeśli taką ją zostawiono.
 * Numer po zmianie listy wskazywałby na sąsiada.
 *
 * Kursor jest za to **numerem**, bo znaczy „która z widocznych po kolei”, i przy
 * zmianie listy nie ma czego pamiętać — dlatego `useContext()` sprowadza go na
 * początek dokładnie tak, jak robi to `ScrollWindow`.
 */
final class SectionState
{
    /** @var array<string, bool> klucz sekcji → czy zwinięta */
    private array $collapsed = [];

    private int $cursor = 0;

    private ?string $context = null;

    /** Zmiana kontekstu — inna zakładka, inny opisywany wpis — zaczyna od góry. */
    public function useContext(string $context): void
    {
        if ($this->context !== $context) {
            $this->context = $context;
            $this->cursor = 0;
        }
    }

    public function cursor(): int
    {
        return $this->cursor;
    }

    /**
     * Przesuwa kursor i **przycina go do liczby sekcji**.
     *
     * Przycinanie stoi tutaj, a nie u wołającego, bo liczba sekcji potrafi się
     * zmienić bez ruchu kursora: zakładka modułu znika wraz z modułem. Wołanie
     * z `$delta === 0` jest przez to poprawnym sposobem powiedzenia „lista się
     * zmieniła, ustaw się w jej granicach”.
     */
    public function moveBy(int $delta, int $count): void
    {
        if ($count < 1) {
            $this->cursor = 0;

            return;
        }

        $this->cursor = max(0, min($this->cursor + $delta, $count - 1));
    }

    public function isCollapsed(string $key): bool
    {
        return $this->collapsed[$key] ?? false;
    }

    public function toggle(string $key): void
    {
        $this->collapsed[$key] = !$this->isCollapsed($key);
    }
}
