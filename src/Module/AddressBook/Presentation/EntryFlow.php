<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\Addresses;
use LightManager\Module\AddressBook\Application\ChapterField;
use LightManager\Module\AddressBook\Domain\Exception\InvalidAddressEntryException;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;
use LightManager\Module\AddressBook\Domain\ValueObject\FieldKind;
use LightManager\Presentation\Ui\Overlay\ChoiceOverlay;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * Łańcuch okien, którym powstaje i zmienia się wpis (krok 60).
 *
 * **Osobna klasa, bo wejścia są dwa**: `F7`/`F4` na ekranie książki i komenda
 * `address-book.add` bez argumentów (11n). Stos okien ma jedno piętro (D75), więc
 * ogniwa ustępują sobie przez `OverlayOutcome::replace()`:
 *
 * ```
 * nazwa → adres → **zapis** → [pole rozdziału → zapis] × n
 * ```
 *
 * **Zapis pada po adresie, a potem po każdym polu** — i to jest poprawka, którą
 * wymusiła reguła z kroku 41: `PromptOverlay` na pustym polu **świadomie nic nie
 * robi**, więc łańcuch kończący się zapisem stawał na pierwszym polu, które
 * użytkownik chciał zostawić puste, a wpis nie powstawał wcale. Zapis po każdym
 * ogniwie znaczy zarazem, że **`Esc` w środku zostawia wpis z tym, co już
 * dostał** — a nie kasuje pracy, którą użytkownik zdążył wykonać.
 *
 * **Pola rozdziałów idą w tym samym łańcuchu, co nazwa i adres** — bo dla
 * użytkownika „port" i „użytkownik" są tak samo częścią wpisu, jak jego adres;
 * że jedno pochodzi z książki, a drugie z modułu sesji zdalnej, jest naszą
 * sprawą, nie jego. Rozdziałów bywa zero (nikt nie założył) i wtedy łańcuch
 * kończy się na adresie.
 *
 * Klasa **nie trzyma stanu między oknami**: przenosi go domknięciami, wpis po
 * wpisie. Wpis jest niezmienny, więc każde ogniwo oddaje następnemu jego nową
 * postać — a przerwanie łańcucha `Esc`em zostawia książkę nietkniętą, bo zapis
 * pada dopiero na końcu.
 */
final class EntryFlow
{
    public function __construct(
        private readonly Addresses $addresses,
        private readonly AddressBookQueries $reader,
        private readonly TranslatorPort $translator,
    ) {
    }

    /** Pierwsze ogniwo dodawania: pusty wpis o świeżym identyfikatorze. */
    public function add(): OverlayInterface
    {
        $this->reader->refreshChapters();

        return $this->namePrompt(new AddressEntry($this->addresses->book()->nextId()), fresh: true);
    }

    /** Pierwsze ogniwo zmiany: ten sam łańcuch z wypełnionymi polami. */
    public function edit(AddressEntry $entry): OverlayInterface
    {
        $this->reader->refreshChapters();

        return $this->namePrompt($entry, fresh: false);
    }

    private function namePrompt(AddressEntry $entry, bool $fresh): PromptOverlay
    {
        return new PromptOverlay(
            $this->key('prompt.name'),
            [],
            $entry->name,
            fn (string $name): OverlayOutcome => $this->step(
                fn (): AddressEntry => $entry->withName(trim($name)),
                fn (AddressEntry $next): OverlayOutcome => OverlayOutcome::replace(
                    $this->addressPrompt($next, $fresh),
                ),
            ),
            $this->translator,
            $this->key('prompt.name.field'),
        );
    }

    private function addressPrompt(AddressEntry $entry, bool $fresh): PromptOverlay
    {
        return new PromptOverlay(
            $this->key('prompt.address'),
            ['name' => $entry->label()],
            $entry->address,
            fn (string $address): OverlayOutcome => $this->step(
                fn (): AddressEntry => $entry->withAddress(trim($address)),
                function (AddressEntry $next) use ($fresh): OverlayOutcome {
                    $message = $this->save($next, $fresh);

                    return $this->fieldStep($next, 0, $message);
                },
            ),
            $this->translator,
            $this->key('prompt.address.field'),
        );
    }

    /**
     * Ogniwo `n`-tego pola rozdziałów albo — gdy pól już nie ma — koniec
     * łańcucha wraz ze zdaniem o tym, co powstało.
     *
     * Pola idą **spłaszczoną listą**, bo łańcuch okien jest jeden i nie ma w nim
     * miejsca na zagnieżdżenie; kolejność to kolejność zakładania rozdziałów,
     * a w rozdziale — kolejność deklaracji.
     */
    private function fieldStep(AddressEntry $entry, int $index, Message $message): OverlayOutcome
    {
        $fields = $this->fields();
        $pair = $fields[$index] ?? null;

        if ($pair === null) {
            return OverlayOutcome::close($message);
        }

        [$chapter, $field] = $pair;
        $overlay = $field->kind === FieldKind::Choice || $field->kind === FieldKind::Flag
            ? $this->choicePrompt($entry, $index, $message, $chapter, $field)
            : $this->textPrompt($entry, $index, $message, $chapter, $field);

        return OverlayOutcome::replace($overlay);
    }

    private function textPrompt(
        AddressEntry $entry,
        int $index,
        Message $message,
        string $chapter,
        ChapterField $field,
    ): PromptOverlay {
        return new PromptOverlay(
            $this->key('prompt.field'),
            ['field' => $this->translator->translate($field->labelKey), 'name' => $entry->label()],
            $field->asText($entry->value($chapter, $field->key)),
            fn (string $value): OverlayOutcome => $this->step(
                fn (): AddressEntry => $entry->withValue($chapter, $field->key, $field->fromText(trim($value))),
                function (AddressEntry $next) use ($index, $message): OverlayOutcome {
                    $this->addresses->put($next);

                    return $this->fieldStep($next, $index + 1, $message);
                },
            ),
            $this->translator,
            $field->labelKey,
        );
    }

    /**
     * Pole wyboru — a przy nim jedna rzecz warta uwagi: **ostatnią odpowiedzią
     * jest „zostaw"**, bo `Esc` w `ChoiceOverlay` znaczy odpowiedź ostatnią
     * (krok 42). Bez niej ucieczka z okna zmieniałaby wartość pola.
     */
    private function choicePrompt(
        AddressEntry $entry,
        int $index,
        Message $message,
        string $chapter,
        ChapterField $field,
    ): ChoiceOverlay {
        $options = [];

        foreach ($this->choicesOf($field) as $choice) {
            $options[$choice] = $field->kind === FieldKind::Flag
                ? $this->key('field.flag.' . ($choice === '1' ? 'yes' : 'no'))
                : $field->labelKey . '.' . $choice;
        }

        $options['\\keep'] = $this->key('prompt.keep');

        return new ChoiceOverlay(
            $this->key('prompt.field'),
            ['field' => $this->translator->translate($field->labelKey), 'name' => $entry->label()],
            $options,
            fn (string $choice): OverlayOutcome => $this->step(
                fn (): AddressEntry => $choice === '\\keep'
                    ? $entry
                    : $entry->withValue($chapter, $field->key, $field->fromText($choice)),
                function (AddressEntry $next) use ($index, $message): OverlayOutcome {
                    $this->addresses->put($next);

                    return $this->fieldStep($next, $index + 1, $message);
                },
            ),
            $this->translator,
        );
    }

    /** @return list<string> */
    private function choicesOf(ChapterField $field): array
    {
        return $field->kind === FieldKind::Flag ? ['1', ''] : $field->choices;
    }

    /**
     * Wykonuje jedno ogniwo: składa nową postać wpisu i przekazuje ją dalej.
     *
     * Samowalidacja wpisu stoi **przy każdym ogniwie**, a nie na końcu, i to jest
     * różnica warta zapisania: użytkownik dowiaduje się o adresie ze spacją przy
     * polu, w którym go wpisał, a nie po przejściu przez cztery kolejne okna.
     *
     * @param callable(): AddressEntry                $build
     * @param callable(AddressEntry): OverlayOutcome  $next
     */
    private function step(callable $build, callable $next): OverlayOutcome
    {
        try {
            return $next($build());
        } catch (InvalidAddressEntryException $problem) {
            return OverlayOutcome::close(Message::error(
                $this->translator->translate($problem->problemKey(), $problem->problemParameters()),
            ));
        }
    }

    private function save(AddressEntry $entry, bool $fresh): Message
    {
        $this->addresses->put($entry);

        return Message::info($this->text(
            $fresh ? 'added' : 'changed',
            ['name' => $entry->label(), 'id' => $entry->id],
        ));
    }

    /** @return list<array{string, ChapterField}> pary rozdział–pole, spłaszczone */
    private function fields(): array
    {
        $fields = [];

        foreach ($this->addresses->chapters() as $chapter) {
            foreach ($chapter->fields as $field) {
                $fields[] = [$chapter->owner, $field];
            }
        }

        return $fields;
    }

    private function key(string $suffix): string
    {
        return 'module.' . AddressBookSettings::ID . '.' . $suffix;
    }

    /** @param array<string, string> $parameters */
    private function text(string $suffix, array $parameters = []): string
    {
        return $this->translator->translate($this->key($suffix), $parameters);
    }
}
