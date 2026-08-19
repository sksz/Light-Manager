<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryRegistry;
use LightManager\Module\Ssh\Application\RemoteTransferProgress;
use LightManager\Module\Ssh\Application\RemoteView;
use LightManager\Module\Ssh\Application\SessionState;
use LightManager\Module\Ssh\Application\SshSettings;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntry;
use LightManager\Module\Ssh\Domain\ValueObject\RemotePath;

/**
 * Odczyt danych modułu sesji zdalnej — **przez rejestr kwerend, jak każdy inny**
 * (krok 53, D92 nr 3; ten moduł dostał go w kroku 54).
 *
 * Czwarta fasada modułowa po `BrowserQueries`, `FileInfoQueries` i `AudioQueries`
 * i pierwszy sprawdzian tezy, na której stoi cała miara druga kroku 54:
 * **dopisanie kwerend do gotowego modułu ma kosztować jedną zdolność.** Kosztowało
 * — `Application/Query/` nie zmieniło się ani o linię.
 *
 * Fasada istnieje po to, żeby `payloadFor()` padło **w jednym miejscu**: oddaje
 * `?object`, więc bez niej każde z kilkunastu miejsc odczytu powtarzałoby
 * `instanceof`. Cudzy ładunek wraca stąd jako odpowiedź pusta — nie wyjątek, bo
 * brak odpowiedzi jest zwykłym stanem (reguła 8), a moduł bywa wyłączony,
 * odrzucony albo nieobecny (15g).
 */
final readonly class SshQueries
{
    /** Rozdział, którym ten moduł opisuje wpis książki (krok 60). */
    public const CHAPTER = SshSettings::ID;

    private const BOOK_ENTRIES = 'address-book.entries';

    private const BOOK_ENTRY = 'address-book.entry';

    private const BOOK_VALUE = 'address-book.value';

    private const BOOK_LAST = 'address-book.last';

    private const ARGUMENT_ENTRY = 'entry';

    private const ARGUMENT_CHAPTER = 'chapter';

    private const ARGUMENT_FIELD = 'field';

    public function __construct(
        private QueryRegistry $queries,
    ) {
    }

    /**
     * Wiersze cudzej kwerendy — **napisy i liczby, nigdy typ** (15g).
     *
     * Rozdział dokłada się tu z jednego miejsca, bo wszystkie trzy pytania
     * dotyczą tego samego: „co ten moduł zapisał przy wpisie".
     *
     * @param array<string, string> $arguments
     *
     * @return list<array<string, string|int|bool>>
     */
    private function rowsOf(string $query, array $arguments = []): array
    {
        $arguments[self::ARGUMENT_CHAPTER] ??= self::CHAPTER;

        return $this->queries->ask($query, new CommandInput($arguments))->rows();
    }

    /**
     * Wpisy książki widziane oczami tego modułu — **cudza kwerenda, własne
     * pojęcie** (krok 60).
     *
     * To jest cała droga, którą spis hostów trafia dziś do modułu: pyta się
     * `address-book.entries` o rozdział `ssh` i składa z **wierszy napisów**
     * profile połączenia. Ani jeden typ modułu książki nie przechodzi przez tę
     * granicę (15g), a wpis bez adresu po prostu wypada — książka jest wspólna,
     * więc wpis nieprzydatny tutaj bywa czyimś poprawnym wpisem.
     *
     * @return list<HostProfile>
     */
    public function hosts(): array
    {
        $profiles = [];

        foreach ($this->rowsOf(self::BOOK_ENTRIES) as $row) {
            $profile = HostProfile::fromRow($row);

            if ($profile !== null) {
                $profiles[] = $profile;
            }
        }

        return $profiles;
    }

    /**
     * Jeden wpis wskazany identyfikatorem — **wraz ze ścieżką klucza**;
     * `null`, gdy nie ma go w książce.
     *
     * Ścieżka dokłada się tu, a nie w spisie, bo pole jest maskowane: wiersze
     * niosą `set`/`unset`, a wartość oddaje osobna kwerenda. Pyta się o nią
     * dokładnie tam, gdzie jest potrzebna — przed połączeniem — i tylko o ten
     * jeden wpis.
     */
    public function entry(string $id): ?HostProfile
    {
        foreach ($this->rowsOf(self::BOOK_ENTRY, [self::ARGUMENT_ENTRY => $id]) as $row) {
            $profile = HostProfile::fromRow($row);

            if ($profile !== null) {
                return $profile->withAuth($profile->auth, $this->keyPath($id));
            }
        }

        return null;
    }

    /**
     * Identyfikator wpisu dopisanego przed chwilą — **jedyne, czego migracja
     * nie umie poznać inaczej** (krok 60, D105 nr 6).
     *
     * Komenda oddaje zdanie, nie daną, więc `address-book.add` nie mówi, co
     * właśnie założyła. Pętla jest jednowątkowa, a migracja idzie w jednym
     * takcie, więc odpowiedź jest w tym miejscu jednoznaczna.
     */
    public function lastAddedEntry(): string
    {
        $rows = $this->queries->ask(self::BOOK_LAST)->rows();
        $id = $rows[0]['id'] ?? '';

        return is_string($id) ? $id : '';
    }

    /**
     * Ścieżka klucza prywatnego — **osobnym pytaniem, bo pole jest maskowane**.
     *
     * W wierszach spisu stoi w jej miejscu `set`/`unset`, żeby nie wyświetlała
     * się w każdej tabeli; wartość oddaje kwerenda przeznaczona do tego wprost
     * (krok 60, D104 nr 6). Pyta o nią wyłącznie łączenie, i wyłącznie w chwili,
     * gdy jej potrzebuje.
     */
    public function keyPath(string $id): ?string
    {
        $rows = $this->rowsOf(self::BOOK_VALUE, [
            self::ARGUMENT_ENTRY => $id,
            self::ARGUMENT_CHAPTER => self::CHAPTER,
            self::ARGUMENT_FIELD => 'keyPath',
        ]);

        $value = $rows[0]['value'] ?? '';

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function session(): SessionState
    {
        $payload = $this->ask('session');

        return $payload instanceof SessionState ? $payload : SessionState::idle();
    }

    public function remote(): RemoteView
    {
        $payload = $this->ask('entries');

        return $payload instanceof RemoteView ? $payload : RemoteView::empty();
    }

    public function transfer(): RemoteTransferProgress
    {
        $payload = $this->ask('transfer');

        return $payload instanceof RemoteTransferProgress ? $payload : RemoteTransferProgress::empty();
    }

    /** Host, po którym chodzimy — `null`, gdy sesji nie ma albo nic nie otwarto. */
    public function host(): ?HostProfile
    {
        return $this->remote()->host;
    }

    public function path(): ?RemotePath
    {
        return $this->remote()->path;
    }

    public function selected(): ?RemoteEntry
    {
        return $this->remote()->selected();
    }

    public function hasListing(): bool
    {
        return $this->remote()->hasListing;
    }

    /**
     * Numer wpisu o tej nazwie na liście — `null`, gdy go tam nie ma.
     *
     * Szuka **wśród tego, co widać**, a nie w agregacie, i to jest różnica warta
     * zapisania: kursor liczy się po liście pokazywanej, więc numer z listy
     * pełnej wskazywałby przy nałożonym filtrze zupełnie inny wpis. Jedyny
     * wołający zdejmuje filtr tuż przed pytaniem, więc obie listy są wtedy tą
     * samą — ale zależeć od tego nie musi.
     */
    public function indexOf(string $name): ?int
    {
        foreach ($this->remote()->entries as $index => $entry) {
            if ($entry->name === $name) {
                return $index;
            }
        }

        return null;
    }

    private function ask(string $name): ?object
    {
        return $this->queries->ask(SshSettings::ID . '.' . $name)->payloadFor(SshSettings::ID);
    }
}
