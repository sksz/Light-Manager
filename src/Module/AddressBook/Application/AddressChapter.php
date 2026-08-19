<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application;

/**
 * Rozdział książki — **nazwana grupa pól, niczyja własność** (krok 60, D104
 * nr 1 i 2).
 *
 * To jest cała różnica wobec rozdziału z książki usuniętej w kroku poprzednim:
 * tamten miał `owner` (czyje są wartości) i `query` (skąd wziąć deklarację),
 * więc książka **oddzwaniała** do zakładającego, a rozdział należał do kogoś.
 * Tutaj rozdział nie wie, kto go zadeklarował, i wiedzieć nie ma po co —
 * czytać i pisać po nim wolno każdemu, z samą książką włącznie.
 *
 * **Rozdziały nie są zapisywane na dysk.** Powstają przy każdym uruchomieniu
 * z deklaracji, więc rozdział, którego nikt w tym uruchomieniu nie
 * zadeklarował, po prostu nie ma opisu pól — a jego **wartości we wpisach stoją
 * nietknięte** i wracają, gdy wróci deklarujący. Spis rozdziałów zapisany na
 * dysku musiałby być sprzątany, a nie ma komu (od tego jest
 * `address-book.forget`).
 *
 * Kolejność pól jest **kolejnością deklaracji** i to ona rządzi kolumnami
 * tabeli oraz kolejnością pytań w łańcuchu okien.
 */
final class AddressChapter
{
    /** Rozdział nazywa się jak moduł, bo zwykle jest nim (`ModuleInterface::id()`). */
    public const NAME_PATTERN = '/^[a-z][a-z0-9-]*$/D';

    /** Klucz pola — ten sam kształt, którego pilnuje wpis przy zapisie. */
    public const FIELD_PATTERN = '/^[a-zA-Z][a-zA-Z0-9_-]*$/D';

    /** @var array<string, ChapterField> pola pod kluczami, w kolejności deklaracji */
    private array $fields = [];

    public function __construct(
        public readonly string $id,
        /** Klucz katalogu z tytułem zakładki; zwykle `module.<id>.name`. */
        private string $titleKey,
    ) {
    }

    public static function isValidName(string $chapter): bool
    {
        return preg_match(self::NAME_PATTERN, $chapter) === 1;
    }

    public static function isValidField(string $field): bool
    {
        return preg_match(self::FIELD_PATTERN, $field) === 1;
    }

    public function titleKey(): string
    {
        return $this->titleKey;
    }

    /**
     * Ponowna deklaracja rozdziału zmienia **tylko tytuł** i tylko wtedy, gdy
     * poprzednia go nie miała: pierwszy deklarujący nazywa rozdział, a drugi nie
     * ma prawa przemianować mu zakładki pod ręką.
     */
    public function nameIfUnnamed(string $titleKey): void
    {
        if ($this->titleKey === '' && $titleKey !== '') {
            $this->titleKey = $titleKey;
        }
    }

    /** @return list<ChapterField> */
    public function fields(): array
    {
        return array_values($this->fields);
    }

    public function field(string $key): ?ChapterField
    {
        return $this->fields[$key] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->fields === [];
    }

    public function fieldCount(): int
    {
        return count($this->fields);
    }

    /**
     * Dopisuje deklarację pola i mówi, czy była **sprzeczna** z tym, co już
     * stoi (D104 nr 2).
     *
     * Trzy przypadki i wszystkie trzy są zamierzone: pola nie ma — wchodzi;
     * pole jest i deklaracja mówi to samo — nie dzieje się nic; pole jest
     * i deklaracja mówi co innego — **pierwsza stoi**, a druga wraca zdaniem.
     * Inaczej dwa moduły używające tego samego pola przerzucałyby się jego
     * rodzajem co takt, a użytkownik oglądałby raz liczbę, raz wybór.
     */
    public function declare(ChapterField $field): bool
    {
        $existing = $this->fields[$field->key] ?? null;

        if ($existing === null) {
            $this->fields[$field->key] = $field;

            return true;
        }

        return $existing->equals($field);
    }
}
