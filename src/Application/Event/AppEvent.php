<?php

declare(strict_types=1);

namespace LightManager\Application\Event;

use LightManager\Domain\ValueObject\MessageTone;

/**
 * Zdarzenia, które publikuje **rdzeń** — zamknięty zbiór pięciu (krok 46, D83).
 *
 * Zdarzenie w rdzeniu jest umową na lata: publikuje je rdzeń, a odbiera moduł,
 * więc każde nowe jest zobowiązaniem, którego nie da się cofnąć bez zmiany
 * w obu miejscach naraz. Stąd reguła nadrzędna, wzorowana wprost na słowniku
 * prymitywów (11k): **słownik jest zamknięty, a jego rozszerzenie wymaga zgody
 * użytkownika**. Kryterium doboru tych pięciu było jedno — wchodzi zdarzenie,
 * które rdzeń **już zna z nazwy**, bo je gdzieś raportuje albo przełącza, a nie
 * takie, które trzeba by najpierw wymyślić.
 *
 * Enum, a nie napisy w miejscu wywołania, ma tu **cel konstrukcyjny, nie
 * estetyczny**: deklaracja katalogu powstaje z `cases()`, więc publikacja i spis
 * pokazywany użytkownikowi nie mają jak się rozjechać. Ta sama sztuczka
 * powtarza się po stronie modułu (`Module\Browser\Presentation\BrowserEvent`)
 * i jest jedyną gwarancją, że mapa przypisań nie zawiera martwych wierszy.
 *
 * Wartość jest **nazwą w przestrzeni `core.`**, dokładnie jak nazwy komend
 * (`CommandRegistry::CORE`): moduł deklaruje swoje pod własnym identyfikatorem,
 * a rejestr odsiewa nazwy spoza przestrzeni deklarującego.
 *
 * Czego tu nie ma i mieć nie będzie bez osobnej decyzji: **koniec pracy**.
 * `Bootstrap::shutdown()` zatrzymuje silnik audio i wątki, więc dźwięk podpięty
 * do zakończenia zostałby ucięty w pół — zdarzenie obiecywałoby coś, czego nie da
 * się dotrzymać.
 */
enum AppEvent: string
{
    /** Komunikat w tonie `Info` — czyli czynność, która się udała. */
    case MessageInfo = 'core.message.info';

    case MessageWarning = 'core.message.warning';

    case MessageError = 'core.message.error';

    /** Okno nakładane stanęło nad ekranem (`OverlayStack::open()`). */
    case OverlayOpened = 'core.overlay.opened';

    /** Komenda wykonała się — z okna komend albo z menu `F9`. */
    case CommandExecuted = 'core.command.executed';

    /**
     * Zdarzenie odpowiadające tonowi komunikatu.
     *
     * Tłumaczenie stoi tutaj, a nie w `LoopState`, bo jest własnością słownika:
     * dołożenie tonu bez zdarzenia zostawiłoby po sobie `match` bez gałęzi,
     * a ten nie skompiluje się przy pierwszym uruchomieniu testów.
     */
    public static function ofTone(MessageTone $tone): self
    {
        return match ($tone) {
            MessageTone::Info => self::MessageInfo,
            MessageTone::Warning => self::MessageWarning,
            MessageTone::Error => self::MessageError,
        };
    }

    /**
     * Klucz katalogu napisów z nazwą zdarzenia widoczną w oknie modułu.
     *
     * Napisy rdzenia nie mają przedrostka modułu, więc klucz brzmi
     * `event.core.message.info` — a nazwa zdarzenia jest jego środkiem, nie
     * osobnym napisem do utrzymywania.
     */
    public function labelKey(): string
    {
        return 'event.' . $this->value;
    }

    /** @return list<EventDeclaration> */
    public static function declarations(): array
    {
        return array_map(
            static fn (self $event): EventDeclaration => new EventDeclaration($event->value, $event->labelKey()),
            self::cases(),
        );
    }
}
