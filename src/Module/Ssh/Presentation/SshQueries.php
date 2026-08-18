<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation;

use LightManager\Application\Command\CommandInput;
use LightManager\Module\Ssh\Application\RemoteTransferProgress;
use LightManager\Module\Ssh\Application\RemoteView;
use LightManager\Module\Ssh\Application\SessionState;
use LightManager\Module\Ssh\Application\SshSession;
use LightManager\Module\Ssh\Application\SshSettings;
use LightManager\Module\Ssh\Domain\Exception\InvalidHostProfileException;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Domain\ValueObject\HostTarget;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntry;
use LightManager\Module\Ssh\Domain\ValueObject\RemotePath;
use LightManager\Presentation\Cli\LoopState;

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
final class SshQueries
{
    /** Nazwy, którymi ten moduł zna książkę adresową — **napisy, ani jednego typu** (15g). */
    private const BOOK_ENTRIES = 'address-book.entries';

    private const BOOK_CHAPTER = 'address-book.chapter';

    /** Czy rozdział `ssh` został już w tym uruchomieniu założony. */
    private bool $chapterDeclared = false;

    /**
     * Rejestry bierze się **ze stanu pętli, a nie w konstruktorze** — i to jest
     * poprawka, którą wymusił krok 60.
     *
     * Rejestr komend wchodzi do stanu pętli **po** złożeniu modułów
     * (`Bootstrap::useCommands()`), a fasada powstaje razem z ekranem, czyli
     * wcześniej. Zapamiętany w konstruktorze byłby przez to rejestrem pustym na
     * zawsze — a rozdział książki adresowej zakładałby się w próżnię. Rejestr
     * kwerend tej wady nie ma (żyje od pierwszej linii `LoopState`), ale idzie
     * tą samą drogą, żeby nie było dwóch zasad w jednej klasie.
     */
    public function __construct(
        private readonly LoopState $state,
        private readonly SshSession $session,
    ) {
    }

    /**
     * Cele połączenia — wpisy książki adresowej złożone z własnymi
     * poświadczeniami (krok 60).
     *
     * **To jest cała droga, którą ten moduł widzi adresy.** Wiersz książki
     * niesie identyfikator, nazwę, adres oraz pola rozdziału `ssh` (port
     * i login); sposób uwierzytelnienia i ścieżkę klucza dokłada sekcja tego
     * modułu, bo do książki czytanej przez wszystkich nie wchodzą (11w).
     *
     * Wpis **bez adresu wypada**: nie da się z niego zrobić celu, a spis
     * hostów, w którym połowa wierszy nie daje się otworzyć, obiecywałby.
     *
     * @return list<HostProfile>
     */
    public function hosts(): array
    {
        $this->declareChapter();
        $hosts = [];

        foreach ($this->state->queries()->ask(self::BOOK_ENTRIES, new CommandInput(['chapter' => SshSettings::ID]))->rows() as $row) {
            $host = $this->hostFrom($row);

            if ($host !== null) {
                $hosts[] = $host;
            }
        }

        return $hosts;
    }

    /**
     * Cel wskazany identyfikatorem wpisu albo jego nazwą; `null`, gdy takiego
     * nie ma.
     *
     * Nazwa jest tu **drogą drugą, nie pierwszą** (D105 nr 4): przyjmuje ją
     * komenda `ssh.connect`, bo identyfikatora nikt nie pamięta, ale wpis
     * o powtórzonej nazwie znajdzie się wtedy pierwszy — i to jest cena, którą
     * płaci się za wygodę wpisywania nazwy.
     */
    public function hostFor(string $reference): ?HostProfile
    {
        foreach ($this->hosts() as $host) {
            if ($host->id === $reference || ($host->name !== '' && $host->name === $reference)) {
                return $host;
            }
        }

        return null;
    }

    /**
     * Zakłada rozdział `ssh` w książce — **raz na uruchomienie, komendą**
     * (krok 60, D105 nr 3).
     *
     * Wołane z **taktu modułu**, a nie tylko przy pierwszym odczycie hostów,
     * i to nie jest ostrożność: użytkownik, który zaczyna od książki, otwierałby
     * inaczej wpis **bez pól rozdziału** — bo moduł sesji zdalnej nie zdążyłby
     * się jeszcze przedstawić. Takt biegnie niezależnie od tego, na co
     * użytkownik patrzy (11o'), więc jest jedynym miejscem, które tej kolejności
     * nie ma.
     *
     * Zamówienie idzie rejestrem komend ze stanu pętli (11x) i trzema napisami:
     * identyfikatorem tego modułu, nazwą własnej kwerendy deklarującej pola
     * i kluczem napisu z tytułem rozdziału. Książka wyłączona albo nieobecna
     * odpowiada odmową z powodem — i to jest zwykły stan, bo moduł pytający musi
     * umieć żyć bez odpowiedzi (15g). Bez książki spis hostów będzie pusty
     * i powie o tym zdaniem.
     */
    public function declareChapter(): void
    {
        if ($this->chapterDeclared) {
            return;
        }

        $this->chapterDeclared = true;
        $command = $this->state->commands()->find(self::BOOK_CHAPTER);

        // Książki nie ma — moduł wyłączony, odrzucony albo nieobecny. To jest
        // **zwykły stan**, nie awaria: spis hostów będzie wtedy pusty i powie
        // o tym zdaniem, a moduł pytający musi umieć żyć bez odpowiedzi (15g).
        $command?->execute(new CommandInput([
            'module' => SshSettings::ID,
            'query' => SshSettings::ID . '.address-fields',
            'label' => 'module.' . SshSettings::ID . '.name',
        ]));
    }

    /**
     * @param array<string, string|int|bool> $row
     */
    private function hostFrom(array $row): ?HostProfile
    {
        $id = $row['id'] ?? '';
        $name = $row['name'] ?? '';
        $address = $row['address'] ?? '';

        if (!is_string($id) || $id === '' || !is_string($name) || !is_string($address) || $address === '') {
            return null;
        }

        $port = $row[SshSettings::ID . '.port'] ?? null;
        $user = $row[SshSettings::ID . '.user'] ?? null;

        try {
            return HostTarget::of(
                $id,
                $name,
                $address,
                $this->session->credentials($id, $name),
                is_int($port) && $port > 0 ? $port : null,
                is_string($user) ? $user : null,
            );
        } catch (InvalidHostProfileException) {
            // Wpis nie do przyjęcia **wypada, a spis zostaje** — ta sama reguła,
            // co przy wierszu książki hostów przed tym krokiem.
            return null;
        }
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
        return $this->state->queries()->ask(SshSettings::ID . '.' . $name)->payloadFor(SshSettings::ID);
    }
}
