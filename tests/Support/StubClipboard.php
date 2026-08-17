<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Dto\ClipboardText;
use LightManager\Application\Port\ClipboardPort;

/**
 * Atrapa schowka (krok 57) — i **jedyny** sposób, którym testy wolno o schowku
 * mówić.
 *
 * Zobowiązanie kroku brzmi: *testy nie dotykają schowka osoby, która je
 * uruchamia*. Nie jest to ostrożność — obie prawdziwe implementacje **piszą po
 * cudzym schowku**: terminalowa wysyła `OSC 52` na STDOUT, a okienna woła
 * `glfwSetClipboardString()`. Przebieg testowy wołający port wprost podmieniłby
 * przez to zawartość schowka programisty, i to bez śladu.
 *
 * Atrapa jest przy tym **synchroniczna jak tor okienkowy**, a nie jak
 * terminalowy: `requestText()` oddaje treść od razu, więc przebieg sprawdzający
 * doręczenie nie musi udawać kolejki wejścia. Asynchroniczność ma osobnego
 * strażnika — rozbiór odpowiedzi `OSC 52` sprawdza `KeySequenceParserTest`,
 * a termin prośby `ClipboardFlowTest`.
 */
final class StubClipboard implements ClipboardPort
{
    /** @var list<string> wszystko, co położono w schowku, w kolejności */
    public array $written = [];

    /** Ile razy zapytano o zawartość — miara zobowiązania „czyta się na polecenie”. */
    public int $requests = 0;

    public function __construct(
        /** Zawartość udawanego schowka systemowego. */
        public string $content = '',
        /** Powód odmowy zapisu albo `null`, gdy zapis się udaje. */
        public ?string $refusal = null,
        /** Czy schowek da się w ogóle zapytać (tor bez schowka oddaje `false`). */
        public bool $readable = true,
        /**
         * Czy odpowiedź ma przyjść od razu.
         *
         * `false` udaje terminal, który **nie odpowiada nic** — czyli dokładnie
         * ten przypadek, dla którego prośba ma termin.
         */
        public bool $answers = true,
    ) {
    }

    /** @var list<ClipboardText> odpowiedzi czekające na doręczenie */
    public array $queue = [];

    public function put(string $text): ?string
    {
        if ($this->refusal !== null) {
            return $this->refusal;
        }

        $this->written[] = $text;
        $this->content = $text;

        return null;
    }

    public function requestText(): bool
    {
        ++$this->requests;

        if (!$this->readable) {
            return false;
        }

        if ($this->answers) {
            $this->queue[] = new ClipboardText($this->content);
        }

        return true;
    }

    /** Ostatnia odpowiedź do doręczenia albo `null`, gdy kolejka jest pusta. */
    public function nextAnswer(): ?ClipboardText
    {
        return array_shift($this->queue);
    }
}
