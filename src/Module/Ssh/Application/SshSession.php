<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application;

use LightManager\Application\Event\EventRegistry;
use LightManager\Application\Port\SettingsPort;
use LightManager\Module\Ssh\Application\Port\HostBookPort;
use LightManager\Module\Ssh\Application\Port\SshSessionPort;
use LightManager\Module\Ssh\Domain\ValueObject\AuthMethod;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;

/**
 * Sesja i książka hostów widziane jako jedna rzecz — **jedna na moduł** (krok 48).
 *
 * Wzorowane wprost na `PlaylistPlayer` z kroku 45 i z tego samego powodu: takt
 * pilnuje jednego stanu, ekran pokazuje drugi, komenda przestawia trzeci —
 * a przy trzech obiektach byłyby to trzy prawdy. Ekran, obie komendy i takt
 * dostają **ten sam** obiekt.
 *
 * **Warstwy `UseCase` moduł nie dostał, choć plan ją przewidywał.** Powód jest
 * ten sam, dla którego nie ma jej moduł dźwięku: `ConnectUseCase`
 * i `DisconnectUseCase` byłyby przepuszczeniem wywołania do portu, a jedyna
 * czynność, która czegoś tu naprawdę dowodzi — rozwiązanie **nazwy** wpisu na
 * profil wraz z rozstrzygnięciem, czy trzeba zapytać o hasło — mieszka tutaj,
 * bo potrzebuje książki, ustawień i portu naraz.
 *
 * Książkę czyta **leniwie**: uruchomienie aplikacji z modułem, którego nikt nie
 * otworzył, nie kosztuje ani jednego odczytu z dysku.
 */
final class SshSession
{
    private ?HostBook $book = null;

    private ?string $bookProblem = null;

    /**
     * Etap, o którym już ogłoszono.
     *
     * Zdarzenie idzie **z porównania stanu w takcie**, a nie z każdego miejsca,
     * które stan zmienia — bo zmieniają go trzy (ekran, obie komendy) i trzy
     * publikacje rozjechałyby się przy pierwszej poprawce (11n). Ta jedna
     * zmienna jest ceną i jest niższa niż tamto.
     */
    private SessionStage $announced = SessionStage::Idle;

    public function __construct(
        private readonly SshSessionPort $sessions,
        private readonly HostBookPort $storage,
        private readonly SettingsPort $settings,
        private readonly EventRegistry $events,
    ) {
    }

    public function state(): SessionState
    {
        return $this->sessions->state();
    }

    public function book(): HostBook
    {
        if ($this->book === null) {
            $loaded = $this->storage->load();
            $this->book = $loaded->book;
            $this->bookProblem = $loaded->problemKey;
        }

        return $this->book;
    }

    /** Klucz powodu, gdy pliku książki nie dało się przeczytać; `null`, gdy dobrze. */
    public function bookProblem(): ?string
    {
        $this->book();

        return $this->bookProblem;
    }

    public function location(): string
    {
        return $this->storage->location();
    }

    /**
     * Czy przed połączeniem trzeba zapytać o hasło.
     *
     * Pytanie stoi tutaj, a nie w ekranie, bo zadaje je **dwóch wołających**
     * (ekran i komenda), a dwie odpowiedzi rozjechałyby się przy pierwszej
     * zmianie sposobów uwierzytelnienia (11n: czynność o dwóch wejściach
     * mieszka w jednym miejscu).
     */
    public function needsPassword(HostProfile $profile): bool
    {
        return $profile->auth === AuthMethod::Password;
    }

    public function connect(HostProfile $profile, ?string $password = null): void
    {
        $settings = $this->settings->current();
        $this->sessions->useOptions(
            SshSettings::timeoutFrom($settings),
            SshSettings::remembersFrom($settings),
        );
        $this->sessions->connect($profile, $password);
    }

    /** `F5` na ekranie: pyta gniazdo mistrza, czy sesja jeszcze stoi. */
    public function refresh(): void
    {
        $this->sessions->refresh();
    }

    public function approve(): void
    {
        $this->sessions->approve();
    }

    public function disconnect(): void
    {
        $this->sessions->disconnect();
    }

    /** Sposób uwierzytelnienia dla **nowego** wpisu — z zakładki ustawień. */
    public function defaultAuth(): AuthMethod
    {
        return SshSettings::authFrom($this->settings->current());
    }

    public function add(HostProfile $profile): void
    {
        $this->book()->add($profile);
        $this->persist();
    }

    public function remove(string $name): bool
    {
        if (!$this->book()->remove($name)) {
            return false;
        }

        $this->persist();

        return true;
    }

    /**
     * Takt modułu — **jedno posunięcie pracy i nic więcej**.
     *
     * Trzy reguły taktu z 11o' są tu spełnione wprost: jest tani (`poll()`
     * z definicji nie blokuje), niczego nie wymusza (nie prosi o przerysowanie)
     * i nie rzuca — bo port nie rzuca przez granicę.
     */
    public function tick(): void
    {
        $this->sessions->advance();
        $this->announce();
    }

    public function shutdown(): void
    {
        $this->sessions->shutdown();
    }

    /**
     * Ogłasza to, co się właśnie stało — **z porównania etapów, w jednym miejscu**.
     *
     * Zdarzenie niesie wyłącznie tożsamość (11o''), więc nie ma tu czego
     * przekazywać poza nazwą. Trzy przejścia, o których warto powiedzieć,
     * i wszystkie są **rzeczą, która się stała**, a nie fazą, która trwa:
     * połączono, rozłączono, nie udało się.
     *
     * Rozłączenie rozpoznaje się po **poprzednim** etapie, a nie po bieżącym:
     * `Idle` jest zarazem stanem początkowym aplikacji, a ogłaszanie
     * „rozłączono" przy starcie byłoby zdaniem o czymś, co się nie wydarzyło.
     */
    private function announce(): void
    {
        $stage = $this->sessions->state()->stage;

        if ($stage === $this->announced) {
            return;
        }

        $previous = $this->announced;
        $this->announced = $stage;

        $event = match (true) {
            $stage === SessionStage::Connected => SshEvent::Connected,
            $stage === SessionStage::Failed => SshEvent::Failed,
            $stage === SessionStage::Idle && $previous === SessionStage::Connected => SshEvent::Disconnected,
            default => null,
        };

        if ($event !== null) {
            $this->events->publish($event->value);
        }
    }

    private function persist(): void
    {
        if ($this->book !== null) {
            $this->storage->save($this->book);
        }
    }
}
