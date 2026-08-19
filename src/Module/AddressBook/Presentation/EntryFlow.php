<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Domain\ValueObject\MessageTone;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\ChapterField;
use LightManager\Module\AddressBook\Domain\ValueObject\FieldKind;
use LightManager\Module\AddressBook\Presentation\Command\SetCommand;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Overlay\ChoiceOverlay;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * Łańcuch okien wpisu — pytanie o pola rozdziału, jedno po drugim (krok 60).
 *
 * **Nie ma referencji do modelu i to jest w nim najważniejsze.** Czyta przez
 * fasadę (czyli przez rejestr kwerend), a pisze przez **rejestr komend** — tą
 * samą komendą `address-book.set`, którą wpisałby użytkownik w oknie komend
 * i którą wołają moduły. To była czwarta wada książki usuniętej w kroku
 * poprzednim: jej łańcuch okien pracował wprost na obiekcie modelu, czyli
 * istniała druga droga do tej samej czynności.
 *
 * **Zapis pada po każdym ogniwie**, a nie na końcu — lekcja z kroku 41:
 * `PromptOverlay` na pustym polu świadomie nic nie robi, więc łańcuch
 * odkładający zapis stawał na pierwszym polu, które użytkownik chciał zostawić
 * puste. Skutek uboczny okazał się zaletą: **`Esc` w środku zostawia wpis
 * z tym, co już dostał**.
 *
 * Stos okien ma jedno piętro (D75), więc każde ogniwo ustępuje następnemu przez
 * `OverlayOutcome::replace()`.
 */
final class EntryFlow
{
    /**
     * @param LoopState $state **wyłącznie po rejestr komend, i to w chwili
     *                         użycia** — nie w konstruktorze. Rejestr wchodzi do
     *                         stanu pętli przy składaniu `Bootstrapu`
     *                         (`useCommands()`), a łańcuch powstaje razem
     *                         z komendami, czyli **wcześniej**; zapamiętany
     *                         w konstruktorze byłby pusty na zawsze.
     */
    public function __construct(
        private readonly LoopState $state,
        private readonly AddressBookQueries $reader,
        private readonly TranslatorPort $translator,
    ) {
    }

    /** Pierwsze ogniwo albo `null`, gdy nie ma o co pytać. */
    public function begin(string $entry, string $chapter): ?OverlayInterface
    {
        return $this->step($entry, $chapter, 0);
    }

    /** Czy w rozdziale jest w ogóle o co pytać — ekran sprawdza to przed otwarciem. */
    public function hasFields(string $chapter): bool
    {
        return $this->reader->fields($chapter)->fields !== [];
    }

    private function step(string $entry, string $chapter, int $position): ?OverlayInterface
    {
        $field = $this->reader->fields($chapter)->fields[$position] ?? null;

        if ($field === null) {
            return null;
        }

        $label = $this->reader->entry($entry)?->label() ?? $entry;
        $parameters = ['entry' => $label, 'field' => $this->translator->translate($field->labelKey)];

        return match ($field->kind) {
            FieldKind::Choice => $this->choice($entry, $chapter, $position, $field, $parameters),
            FieldKind::Flag => $this->flag($entry, $chapter, $position, $field, $parameters),
            FieldKind::Entry => $this->reference($entry, $chapter, $position, $field, $parameters),
            default => $this->prompt($entry, $chapter, $position, $field, $parameters),
        };
    }

    /** @param array<string, string> $parameters */
    private function prompt(
        string $entry,
        string $chapter,
        int $position,
        ChapterField $field,
        array $parameters,
    ): OverlayInterface {
        // Wartość pola maskowanego **wchodzi do pola wprost**, a nie zasłonięta:
        // okno służy do jej poprawienia, a poprawianie gwiazdek skasowałoby
        // ścieżkę klucza przy pierwszym `Enter`. Zasłania samo pole (`masked`).
        $current = $this->reader->value($entry, $chapter, $field->key);

        return new PromptOverlay(
            AddressBookSettings::key('prompt.field'),
            $parameters,
            $current === '' ? $field->default : $current,
            fn (string $value): OverlayOutcome => $this->accepted($entry, $chapter, $position, $field, $value),
            $this->translator,
            $field->labelKey,
            $field->kind->isMasked(),
        );
    }

    /** @param array<string, string> $parameters */
    private function choice(
        string $entry,
        string $chapter,
        int $position,
        ChapterField $field,
        array $parameters,
    ): OverlayInterface {
        $options = [];

        foreach ($field->choices as $choice) {
            $options[$choice] = $field->labelKey . '.' . $choice;
        }

        return new ChoiceOverlay(
            AddressBookSettings::key('prompt.field'),
            $parameters,
            $options,
            fn (string $choice): OverlayOutcome => $this->accepted($entry, $chapter, $position, $field, $choice),
            $this->translator,
        );
    }

    /** @param array<string, string> $parameters */
    private function flag(
        string $entry,
        string $chapter,
        int $position,
        ChapterField $field,
        array $parameters,
    ): OverlayInterface {
        // Odpowiedzi są nazwane, a nie `'1'`/`'0'`, i to nie z upodobania:
        // klucz tablicy będący napisem liczbowym staje się w PHP liczbą, więc
        // `['1' => …]` przestaje być mapą napisów, zanim dojdzie do okna.
        return new ChoiceOverlay(
            AddressBookSettings::key('prompt.field'),
            $parameters,
            ['yes' => AddressBookSettings::key('flag.yes'), 'no' => AddressBookSettings::key('flag.no')],
            fn (string $choice): OverlayOutcome => $this->accepted(
                $entry,
                $chapter,
                $position,
                $field,
                $choice === 'yes' ? '1' : '0',
            ),
            $this->translator,
        );
    }

    /**
     * Pole rodzaju `entry` pyta **wyborem z listy wpisów**, a nie polem
     * tekstowym: identyfikatora nikt nie pamięta, a wpisany z palca byłby
     * jedynym miejscem, w którym książka mogłaby wskazać nieistniejący wpis.
     *
     * **Etykiety idą tu nazwami wpisów, a nie kluczami katalogu** — i jest to
     * świadome odstępstwo od zwyczaju `ChoiceOverlay`, nazwane, bo nie da się
     * go uniknąć: nazwa wpisu jest daną użytkownika, więc klucza katalogu mieć
     * nie może. Działa, bo tłumacz oddaje **klucz nieznany bez zmian** (krok
     * 15); ceną jest wpis nazwany dokładnie jak istniejący klucz katalogu,
     * który pokaże się przetłumaczony. Alternatywą był znacznik `raw`
     * w rdzeniowym oknie — czyli zmiana rdzenia dla jednego wołającego,
     * wprost przeciwna kryterium tego kroku.
     *
     * @param array<string, string> $parameters
     */
    private function reference(
        string $entry,
        string $chapter,
        int $position,
        ChapterField $field,
        array $parameters,
    ): OverlayInterface {
        $options = [];

        foreach ($this->reader->book()->entries as $candidate) {
            if ($candidate->id !== $entry) {
                $options[$candidate->id] = $candidate->label();
            }
        }

        if ($options === []) {
            return $this->prompt($entry, $chapter, $position, $field, $parameters);
        }

        return new ChoiceOverlay(
            AddressBookSettings::key('prompt.field'),
            $parameters,
            $options,
            fn (string $choice): OverlayOutcome => $this->accepted($entry, $chapter, $position, $field, $choice),
            $this->translator,
        );
    }

    /** Zapis ogniwa i przejście do następnego — albo koniec łańcucha. */
    private function accepted(
        string $entry,
        string $chapter,
        int $position,
        ChapterField $field,
        string $value,
    ): OverlayOutcome {
        $problem = $this->write($entry, $chapter, $field->key, $value);

        if ($problem !== null) {
            // Wartość odrzucona zatrzymuje łańcuch **na tym polu**, a nie
            // przewija go dalej: pytanie o następne pole zjadłoby zdanie
            // o poprzednim, a użytkownik zobaczyłby wyłącznie skutek.
            return OverlayOutcome::close($problem);
        }

        $next = $this->step($entry, $chapter, $position + 1);

        return $next === null ? OverlayOutcome::close($this->done($entry)) : OverlayOutcome::replace($next);
    }

    private function write(string $entry, string $chapter, string $field, string $value): ?Message
    {
        $command = $this->state->commands()->find(AddressBookSettings::ID . '.set');

        if ($command === null) {
            return Message::warning($this->translator->translate(AddressBookSettings::key('message.noCommand')));
        }

        $outcome = $command->execute(new CommandInput([
            SetCommand::ENTRY => $entry,
            SetCommand::CHAPTER => $chapter,
            SetCommand::FIELD => $field,
            SetCommand::VALUE => $value,
        ]));

        return $outcome->message !== null && $outcome->message->tone !== MessageTone::Info
            ? $outcome->message
            : null;
    }

    private function done(string $entry): Message
    {
        return Message::info($this->translator->translate(AddressBookSettings::key('message.edited'), [
            'entry' => $this->reader->entry($entry)?->label() ?? $entry,
        ]));
    }
}
