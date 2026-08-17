<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

use LightManager\Application\Command\CommandRegistry;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Event\AppEvent;
use LightManager\Application\Event\EventRegistry;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Query\QueryRegistry;
use LightManager\Application\Ui\FrameText;
use LightManager\Application\Ui\Rect;
use LightManager\Domain\ValueObject\Message;
use LightManager\Presentation\Ui\Component\StatusBar;
use LightManager\Presentation\Ui\HintTarget;
use LightManager\Presentation\Ui\SelectionState;
use LightManager\Presentation\Ui\StatusHints;

/**
 * Dane przenoszone między iteracjami pętli: ustawienia, komunikat, okna
 * nakładane, czas bieżącej klatki i kontekst sesji.
 *
 * Czyli **stan powłoki**, a nie stan menadżera plików. Do kroku 20 leżał tu
 * jeszcze bieżący katalog wraz z zaznaczeniem — i to on kazał rdzeniowi znać
 * pojęcie pliku. Krok 21 wyprowadził go do `BrowserState` w module przeglądarki,
 * bo katalog jest stanem jednej konkretnej funkcji, a nie czymś, co przeżywa
 * klatkę niezależnie od tego, kto ją rysuje.
 *
 * Który ekran jest aktywny — **nie** jest częścią tego stanu. Ekrany są od
 * kroku 18 obiektami, a te muszą powstać po `LoopState`, bo z niego czytają;
 * trzymanie ich tutaj wymagałoby ekranu pustego na czas budowy. Aktywnym
 * ekranem zarządza `ScreenStack`.
 */
final class LoopState
{
    /** Ile komunikat musi wisieć, zanim klawisz zdoła go zgasić. */
    private const MINIMUM_MESSAGE_SECONDS = 3.0;

    private const SECONDS_PER_MESSAGE_WORD = 0.5;

    /**
     * Ile czekamy na odpowiedź terminala o schowku (krok 57).
     *
     * Termin istnieje z jednego powodu: **terminal, który odczytu nie obsługuje,
     * nie odpowiada nic.** Ani błędu, ani pustej odpowiedzi, ani sygnału — cisza
     * nieodróżnialna od ciszy terminala, który jeszcze nie zdążył. Bez terminu
     * pole czekałoby w nieskończoność na coś, co nigdy nie przyjdzie, a jedyne, co
     * użytkownik by zobaczył, to klawisz, po którym nic się nie stało.
     *
     * Ćwierć sekundy to osiem klatek przy trzydziestu na sekundę — z zapasem
     * ponad „klatkę albo dwie”, o które chodzi w torze terminalowym, i wciąż
     * poniżej progu, przy którym człowiek uznaje program za zawieszony.
     */
    private const CLIPBOARD_REQUEST_SECONDS = 0.25;

    private ?Message $message = null;

    private float $messageDismissableAt = 0.0;

    private readonly OverlayStack $overlays;

    /** Czas rozpoczęcia bieżącej klatki — dla tego, co się zmienia samo z siebie. */
    private float $now = 0.0;

    /** Prostokąt paska stanu z ostatniej klatki — bez niego nie ma czego trafić. */
    private ?Rect $hintBounds = null;

    private string $hintMessage = '';

    private ?StatusHints $hintSource = null;

    /**
     * Co jest zaznaczone na klatce (krok 56).
     *
     * Właścicielem jest **rdzeń**, a nie ekran, i to jest różnica wobec ogniska:
     * zaznaczenie przecina panele, ekrany i okna nakładane, bo dotyczy klatki,
     * a nie treści któregokolwiek z nich. Mieszka tu z tego samego rachunku, co
     * trzy rejestry i mapa stopki: `LoopState` dostają wszyscy, którzy go
     * potrzebują — składający klatkę (rysuje i kasuje) oraz rozdzielający
     * wejście (zaznacza) — więc `Bootstrap` nie rośnie o argument.
     */
    private readonly SelectionState $selection;

    /**
     * Warstwa tekstowa **ostatnio złożonej klatki** — tylko wtedy, gdy jest
     * zaznaczenie.
     *
     * Klatka bez zaznaczenia nie liczy jej wcale i to jest cały rachunek tego
     * kroku na ścieżce rysowania: drugie przejście po prymitywach pada
     * **wyłącznie** wtedy, gdy jest co z niego wziąć. Trzyma ją stan, a nie
     * składanie klatki, bo pytanie „co jest zaznaczone” pada poza rysowaniem —
     * dziś w pasku stanu, od kroku 57 przy kopiowaniu do schowka.
     */
    private ?FrameText $frameText = null;

    /** Do kiedy prośba o schowek jest ważna; `0.0` znaczy „nikt nie prosił”. */
    private float $clipboardDeadline = 0.0;

    /**
     * Kontekst sesji dla modułów: gdzie użytkownik stoi i co ma zaznaczone.
     *
     * Stan go **trzyma**, ale nie **wypełnia**: publikuje go ten, kto zna bieżące
     * miejsce — od kroku 21 `BrowserState` modułu przeglądarki. Brak wydawcy daje
     * kontekst pusty, nie `null`: odbiorca ma czytać, a nie sprawdzać istnienie.
     */
    private ModuleContext $context;

    /**
     * Słownik zdarzeń wraz z odbiorcami (krok 46).
     *
     * Stoi tutaj z tego samego powodu, co `ModuleContext` kilka linii wyżej:
     * **stan trzyma, ale nie wypełnia**. Rdzeń publikuje przez niego swoje pięć
     * momentów, moduł publikuje swoje, a kto słucha — wie wyłącznie rejestr.
     * Miejsce jest przy tym rachunkiem, a nie upodobaniem: `LoopState` dostaje
     * **każdy** moduł, więc publikacja nie kosztuje ani jednego argumentu więcej
     * w `Bootstrapie`.
     */
    private readonly EventRegistry $events;

    /**
     * Rejestr kwerend (krok 53) — **jedyna droga odczytu w tej aplikacji**.
     *
     * Miejsce jest tym samym rachunkiem, co przy rejestrze zdarzeń o kilka linii
     * wyżej: `LoopState` dostaje **każdy** moduł i każdy ekran, więc pytanie
     * o dane nie kosztuje ani jednego argumentu więcej w `Bootstrapie`. Stan go
     * **trzyma, ale nie wypełnia** — wpisują się do niego rdzeń i moduły.
     */
    private readonly QueryRegistry $queries;

    /**
     * Rejestr komend (krok 54) — **jedyna droga do cudzej czynności**.
     *
     * Trzeci rejestr w tym miejscu i **z tego samego rachunku**, co dwa poprzednie:
     * `LoopState` dostaje każdy moduł, więc zamówienie cudzej czynności nie
     * kosztuje ani jednego argumentu więcej w `Bootstrapie`.
     *
     * **Powód, dla którego tu trafił, jest luką w planie, a nie nową funkcją.**
     * Reguła 15g mówi od kroku 53: *moduł zna nazwę cudzej komendy (napis), nigdy
     * jej typ* — ale sama nazwa jest bezużyteczna, dopóki nie ma czego o nią
     * zapytać. Do kroku 54 **żaden moduł nie sięgał po rejestr komend** i nikt
     * tego nie zauważył, bo pierwszy odbiorca — `k8s.deploy-image` — powstał
     * dopiero teraz. Zamówienie cudzej czynności było przez to zapisane w regułach
     * i niewykonalne w kodzie.
     *
     * Rejestr **wchodzi tu wypełniony** (inaczej niż dwa poprzednie): składa go
     * `Bootstrap` z komend rdzenia i modułów, a stan wyłącznie go podaje.
     */
    private ?CommandRegistry $commands = null;

    public function __construct(
        private Settings $settings = new Settings(),
        ?EventRegistry $events = null,
        ?QueryRegistry $queries = null,
    ) {
        $this->events = $events ?? new EventRegistry();
        $this->queries = $queries ?? new QueryRegistry();
        $this->overlays = new OverlayStack($this->events);
        $this->context = new ModuleContext();
        $this->selection = new SelectionState();
    }

    public function selection(): SelectionState
    {
        return $this->selection;
    }

    /**
     * Warstwa tekstowa złożonej właśnie klatki — podaje ją `FrameComposer`,
     * i tylko wtedy, gdy jest zaznaczenie.
     */
    public function useFrameText(?FrameText $text): void
    {
        $this->frameText = $text;
    }

    /**
     * **Co pisze pod zaznaczeniem** — miara tego kroku i jedyne, czego krok 57
     * będzie od niego potrzebował.
     *
     * Pusta lista znaczy „nie ma zaznaczenia albo nie było jeszcze klatki”:
     * odczyt idzie z klatki **ostatnio złożonej**, bo tylko ona wie, co gdzie
     * narysowano. Wołający ma przez to zawsze to samo, co widzi użytkownik —
     * a nie to, co aplikacja narysowałaby, gdyby ją o to teraz poprosić.
     *
     * @return list<string>
     */
    public function selectionText(): array
    {
        $bounds = $this->selection->bounds();

        if ($bounds === null || $this->frameText === null) {
            return [];
        }

        return $this->frameText->textIn($bounds);
    }

    /**
     * Prośba o zawartość schowka — **znacznik z terminem, a nie proszący**
     * (krok 57, D101 nr 2).
     *
     * Stan pamięta, **że** ktoś poprosił i do kiedy odpowiedź ma sens; **kto**
     * poprosił, nie jest tu zapisane. Wariant z referencją do okna albo ekranu
     * rozważano i odrzucono: byłby pierwszą taką referencją w stanie pętli
     * i trzema nowymi miejscami, w których trzeba by ją kasować — przy zamknięciu
     * okna, przy zmianie ekranu i przy `reset()`. Zapomniane kasowanie znaczyłoby
     * wtedy treść schowka wstawioną do pola, którego użytkownik już nie widzi,
     * czyli dokładnie to, przed czym broni zobowiązanie „jedno miejsce docelowe”.
     *
     * Odbiorcę pyta się przez to **na nowo przy doręczeniu** (`AcceptsPaste`).
     * Cena jest nazwana i przyjęta: gdyby w tej samej ćwiartce sekundy jedno pole
     * się zamknęło, a drugie otworzyło, treść trafi do drugiego.
     */
    public function requestClipboard(float $now): void
    {
        $this->clipboardDeadline = $now + self::CLIPBOARD_REQUEST_SECONDS;
    }

    /**
     * Zdejmuje prośbę i mówi, czy w ogóle wisiała — wołane przy **doręczeniu**
     * treści schowka.
     *
     * `false` znaczy „nikt o to nie prosił albo prośba już wygasła”, czyli treść
     * do porzucenia. Aplikacja nie czyta schowka inaczej niż na polecenie
     * użytkownika (pierwsze z trzech zobowiązań D95 nr 5), więc odpowiedź, której
     * nikt nie zamówił, nie ma prawa nigdzie wejść — także wtedy, gdy jest
     * poprawną odpowiedzią poprawnego terminala.
     */
    public function takeClipboardRequest(): bool
    {
        $pending = $this->clipboardDeadline > 0.0;
        $this->clipboardDeadline = 0.0;

        return $pending;
    }

    /**
     * Czy prośba właśnie wygasła — pytanie zadawane **raz na takt**, bo tylko
     * takt zna czas.
     *
     * Zdejmuje prośbę przy odpowiedzi twierdzącej, więc zdanie „schowek
     * nieosiągalny” pada dokładnie raz, a nie trzydzieści razy na sekundę.
     */
    public function clipboardRequestExpired(float $now): bool
    {
        if ($this->clipboardDeadline === 0.0 || $now < $this->clipboardDeadline) {
            return false;
        }

        $this->clipboardDeadline = 0.0;

        return true;
    }

    public function events(): EventRegistry
    {
        return $this->events;
    }

    public function queries(): QueryRegistry
    {
        return $this->queries;
    }

    /**
     * Rejestr komend — patrz opis pola.
     *
     * Oddaje **rejestr pusty**, dopóki `Bootstrap` go nie poda, a nie `null`:
     * moduł ma pytać o komendę, a nie sprawdzać, czy jest o co pytać. Nieznana
     * nazwa oddaje wtedy `null` z `find()` i wołający mówi zdaniem — dokładnie
     * tak samo, jak przy module wyłączonym.
     */
    public function commands(): CommandRegistry
    {
        return $this->commands ??= new CommandRegistry();
    }

    /** Podaje rejestr złożony w `Bootstrapie` — wołane **raz**, przy starcie. */
    public function useCommands(CommandRegistry $commands): void
    {
        $this->commands = $commands;
    }

    public function context(): ModuleContext
    {
        return $this->context;
    }

    public function publishContext(ModuleContext $context): void
    {
        $this->context = $context;
    }

    public function settings(): Settings
    {
        return $this->settings;
    }

    public function applySettings(Settings $settings): void
    {
        $this->settings = $settings;
    }

    public function message(): ?Message
    {
        return $this->message;
    }

    /**
     * Komunikat wraz z tonem — i **jedyne miejsce, przez które przechodzą
     * wszystkie** komunikaty aplikacji, więc zarazem najtańsze źródło trzech
     * zdarzeń rdzenia (krok 46).
     *
     * Publikacja stoi za nadaniem komunikatu, nie przed nim: odbiorca ma prawo
     * zapytać stan o to, co właśnie zostało powiedziane, a stan pokazujący jeszcze
     * poprzednie zdanie byłby kłamstwem trwającym jedno wywołanie.
     */
    public function report(Message $message, float $now): void
    {
        $this->message = $message;
        $this->messageDismissableAt = $now + self::MINIMUM_MESSAGE_SECONDS
            + self::SECONDS_PER_MESSAGE_WORD * self::countWords($message->text);

        $this->events->publish(AppEvent::ofTone($message->tone)->value);
    }

    public function reportProblem(string $message, float $now): void
    {
        $this->report(Message::error($message), $now);
    }

    /**
     * Komunikat gasi dopiero naciśnięcie klawisza — ale nie wcześniej, niż
     * minie czas potrzebny na jego przeczytanie. Dzięki temu klawisz naciśnięty
     * odruchowo tuż po błędzie nie zdąży schować informacji, której użytkownik
     * jeszcze nie zobaczył.
     */
    public function dismissMessageIfDue(float $now): void
    {
        if ($this->message !== null && $now >= $this->messageDismissableAt) {
            $this->message = null;
        }
    }

    private static function countWords(string $message): int
    {
        $words = preg_split('/\s+/u', trim($message), -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? 1 : max(1, count($words));
    }

    public function overlays(): OverlayStack
    {
        return $this->overlays;
    }

    /**
     * Pasek stanu z **ostatnio złożonej klatki** — składniki, nie gotowa mapa
     * (krok 55).
     *
     * Stoi tu z tego samego rachunku, co rejestry zdarzeń, kwerend i komend:
     * `LoopState` dostają wszyscy, którzy go potrzebują — tu składający klatkę
     * (pisze) i rozdzielający wejście (czyta) — więc `Bootstrap` nie rośnie
     * o argument.
     *
     * **Prostokąty liczą się leniwie**, dopiero przy pytaniu, i to nie jest
     * ostrożność: klatka powstaje trzydzieści razy na sekundę, a kliknięcie —
     * kilka razy na minutę, więc mapa budowana co klatkę byłaby kilkunastoma
     * obiektami na wyrzucenie w każdej z nich. Pomiar `--loop` pokazał ten koszt
     * wprost, zanim ktokolwiek zdążył kliknąć. Ta sama reguła, którą rejestr
     * kwerend stosuje do wierszy wyniku (11w).
     */
    public function useStatusBar(Rect $bounds, string $message, StatusHints $hints): void
    {
        $this->hintBounds = $bounds;
        $this->hintMessage = $message;
        $this->hintSource = $hints;
    }

    /**
     * Prostokąty podpowiedzi stopki — **jedyna mapa trafień w rdzeniu**
     * i dotycząca wyłącznie tego, co rdzeń rysuje sam; treść stref pamięta ekran
     * (`AcceptsPointer`).
     *
     * @return list<HintTarget>
     */
    public function hintTargets(): array
    {
        if ($this->hintSource === null || $this->hintBounds === null) {
            return [];
        }

        return StatusBar::hintTargets($this->hintBounds, $this->hintMessage, $this->hintSource);
    }

    /**
     * Czas rozpoczęcia klatki, podawany raz na takt przez pętlę.
     *
     * Zegar stoi tutaj, a nie w komponentach, bo do kroku 19 był tu już jeden
     * jego użytkownik — gaszenie komunikatu — a karetka w polu tekstowym jest
     * drugim. Dwa źródła czasu w jednej klatce potrafiłyby się rozjechać
     * o takt, a komponent z własnym `microtime()` przestaje być testowalny.
     */
    public function tick(float $now): void
    {
        $this->now = $now;
    }

    public function now(): float
    {
        return $this->now;
    }
}
