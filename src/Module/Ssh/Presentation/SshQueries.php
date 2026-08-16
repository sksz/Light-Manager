<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation;

use LightManager\Application\Query\QueryRegistry;
use LightManager\Module\Ssh\Application\HostBook;
use LightManager\Module\Ssh\Application\HostBookView;
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
    public function __construct(
        private QueryRegistry $queries,
    ) {
    }

    public function hostBook(): HostBookView
    {
        $payload = $this->ask('hosts');

        return $payload instanceof HostBookView ? $payload : HostBookView::empty();
    }

    public function book(): HostBook
    {
        return $this->hostBook()->book;
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
