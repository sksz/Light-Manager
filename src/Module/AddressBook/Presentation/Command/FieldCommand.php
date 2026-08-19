<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\Addresses;
use LightManager\Module\AddressBook\Application\ChapterField;
use LightManager\Module\AddressBook\Domain\ValueObject\FieldKind;

/**
 * `address-book.field <rozdział> <klucz> <etykieta> <rodzaj> [domyślna]
 * [wybory]` — zapowiedź użycia jednego pola (krok 60, D105 nr 2).
 *
 * Jedna komenda na pole, a nie cała deklaracja w jednym upakowanym napisie —
 * rozstrzygnięcie startowe: napis z własnym formatem byłby **trzecim parserem
 * w aplikacji**, a argumenty typowane widać w oknie komend takimi, jakie są.
 *
 * **Kolejność wywołań jest kolejnością kolumn** na ekranie i kolejnością pytań
 * w łańcuchu okien — to jedyna rzecz poza treścią, którą deklarujący tu
 * rozstrzyga.
 *
 * Deklaracja **sprzeczna** z tą, która już stoi (ten sam klucz, inny rodzaj),
 * niczego nie przestawia i wraca zdaniem: pierwsza stoi. Inaczej dwa moduły
 * używające tego samego pola przerzucałyby się jego rodzajem co takt,
 * a użytkownik oglądałby raz liczbę, raz wybór.
 */
final class FieldCommand implements CommandInterface
{
    public const CHAPTER = 'chapter';

    public const FIELD = 'field';

    public const LABEL = 'label';

    public const KIND = 'kind';

    public const FALLBACK = 'default';

    public const CHOICES = 'choices';

    public function __construct(
        private readonly Addresses $addresses,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.field';
    }

    public function descriptionKey(): string
    {
        return AddressBookSettings::key('command.field');
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(self::CHAPTER, AddressBookSettings::key('argument.chapter')),
            new CommandArgument(self::FIELD, AddressBookSettings::key('argument.field')),
            new CommandArgument(self::LABEL, AddressBookSettings::key('argument.label')),
            new CommandArgument(self::KIND, AddressBookSettings::key('argument.kind')),
            new CommandArgument(self::FALLBACK, AddressBookSettings::key('argument.default'), required: false),
            new CommandArgument(self::CHOICES, AddressBookSettings::key('argument.choices'), required: false),
        ];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $chapter = trim($input->text(self::CHAPTER));
        $key = trim($input->text(self::FIELD));
        $label = trim($input->text(self::LABEL));
        $kind = FieldKind::of(trim($input->text(self::KIND)));

        if ($chapter === '' || $key === '' || $label === '' || $kind === null) {
            // Rodzaj spoza spisu **nie jest awarią deklarującego**, tylko pola,
            // którego ten ekran nie umie pokazać — moduł nowszy od książki ma
            // prawo o taki poprosić i nie ma prawa jej tym zepsuć.
            return CommandOutcome::stay(Message::warning(
                $this->translator->translate(AddressBookSettings::key('field.incomplete'), ['field' => $key]),
            ));
        }

        $declared = $this->addresses->declareField($chapter, new ChapterField(
            $key,
            $label,
            $kind,
            trim($input->text(self::FALLBACK)),
            self::choicesFrom(trim($input->text(self::CHOICES))),
        ));

        return $declared ? CommandOutcome::done() : CommandOutcome::stay(Message::warning(
            $this->translator->translate(AddressBookSettings::key('field.conflict'), [
                'chapter' => $chapter,
                'field' => $key,
            ]),
        ));
    }

    /** @return list<string> */
    private static function choicesFrom(string $value): array
    {
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), explode(',', $value)),
            static fn (string $choice): bool => $choice !== '',
        ));
    }
}
