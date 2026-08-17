<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * Treść schowka — **trzecia postać zdarzenia wejściowego** (krok 57).
 *
 * Wklejenie nie jest wynikiem funkcji, tylko zdarzeniem, które przychodzi, i to
 * jest cała trudność tego kroku. OSC 52 z pytajnikiem (`\e]52;c;?\e\\`) nie
 * zwraca niczego z wywołania — terminal odpowiada **sekwencją na wejściu
 * aplikacji**, klatkę albo dwie później, albo nie odpowiada wcale. Gdyby port
 * schowka udawał odczyt synchroniczny, musiałby czekać w pętli na coś, co ma
 * prawo nigdy nie przyjść.
 *
 * Zdarzenie wpada przez to do **tej samej kolejki**, co klawisz i wskaźnik,
 * wędruje tą samą drogą i doręcza się tam, gdzie stoi ognisko. Tor okienkowy
 * oddaje treść od razu (`glfwGetClipboardString()`), więc `ClipboardText`
 * powstaje w tym samym takcie — i to jest cały sens portu: różnica jest
 * niewidoczna dla wołającego.
 *
 * Klasa niesie **wyłącznie treść**, bez tożsamości proszącego. Kto ją odbierze,
 * rozstrzyga się przy doręczeniu — pyta się wtedy tego, co na wierzchu, czy
 * deklaruje `Presentation\Ui\AcceptsPaste` (D101 nr 2). Pamiętanie obiektu, który
 * poprosił, było wariantem odrzuconym: żądałoby pierwszej referencji do okna
 * albo ekranu w stanie pętli i trzech miejsc, w których trzeba by ją kasować.
 */
final readonly class ClipboardText implements InputEvent
{
    public function __construct(
        /** Treść schowka — dokładnie to, co przyszło, bez obcinania i bez oceny. */
        public string $text,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->text === '';
    }
}
